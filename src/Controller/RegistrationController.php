<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Security\UserChecker;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    /** A human needs at least a few seconds to fill in the form */
    private const int MIN_FILL_SECONDS = 5;
    /** Beyond that, the token is considered harvested and replayed */
    private const int MAX_FILL_SECONDS = 86400;

    public function __construct(
        private readonly EmailVerifier       $emailVerifier,
        private readonly ImageService        $imageService,
        private readonly LoggerInterface     $logger,
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.secret%')]
        private readonly string              $secret,
    )
    {
    }

    #[Route('/{_locale}/register', name: 'app_register', requirements: ['_locale' => 'fr|en|ko'])]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->get('startedAt')->setData($this->signTimestamp(time()));
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $botReason = $this->detectBot($form);
            if ($botReason !== null) {
                $this->logger->warning('Registration rejected (bot suspected)', [
                    'reason' => $botReason,
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'email' => $form->get('email')->getData(),
                    'username' => $form->get('username')->getData(),
                ]);
                // Pretend everything went fine so the bot learns nothing
                $this->addFlash('success', $this->translator->trans('An email has been sent to you. Please check your inbox to verify your email address.'));
                return $this->redirectToRoute('app_home_index');
            }

            if ($form->get('plainPassword')->getData() !== $form->get('confirmPassword')->getData()) {
                $form->get('confirmPassword')->addError(new FormError($this->translator->trans('The passwords do not match')));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->sendVerificationEmail($user);
            $this->addFlash('success', $this->translator->trans('An email has been sent to you. Please check your inbox to verify your email address.'));

            return $this->redirectToRoute('app_home_index');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'bgImage' => $this->imageService->getRandomBlurredPosters(),
        ]);
    }

    /**
     * Resends the verification email to the account that just failed to log in
     * because it is not verified (see UserChecker). The user id is only put in the
     * session once the password has been validated, and it is consumed here: each
     * resend requires a new login attempt with the right password.
     */
    #[Route('/{_locale}/verify/resend', name: 'app_verify_resend', requirements: ['_locale' => 'fr|en|ko'], methods: ['POST'])]
    public function resendVerificationEmail(Request $request, UserRepository $userRepository): Response
    {
        if (!$this->isCsrfTokenValid('verify_resend', (string)$request->request->get('_token'))) {
            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();
        $userId = $session->get(UserChecker::UNVERIFIED_USER_ID);
        $session->remove(UserChecker::UNVERIFIED_USER_ID);

        $user = $userId ? $userRepository->find($userId) : null;
        if ($user === null || $user->isVerified()) {
            return $this->redirectToRoute('app_login');
        }

        $this->sendVerificationEmail($user);
        $this->addFlash('success', $this->translator->trans('A new verification email has been sent to you. Please check your inbox (and your spam folder).'));

        return $this->redirectToRoute('app_login');
    }

    /**
     * Generates a signed url and emails it to the user
     */
    private function sendVerificationEmail(User $user): void
    {
        $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
            new TemplatedEmail()
                ->from(new Address('ojm16@free.fr', 'Contact my TvTime'))
                ->to($user->getEmail())
                ->subject('Please Confirm your Email')
                ->htmlTemplate('registration/confirmation_email.html.twig')
        );
    }

    /**
     * Returns the reason why the submission looks like a bot, or null if it looks human.
     */
    private function detectBot(FormInterface $form): ?string
    {
        // 1. Honeypot: invisible field that only bots fill in
        if (trim((string)$form->get('website')->getData()) !== '') {
            return 'honeypot';
        }

        // 2. Signed timestamp: missing, forged, too fast or too old
        $token = (string)$form->get('startedAt')->getData();
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return 'missing timestamp';
        }
        $timestamp = (int)$parts[0];
        if (!hash_equals($this->signTimestamp($timestamp), $token)) {
            return 'invalid timestamp signature';
        }
        $elapsed = time() - $timestamp;
        if ($elapsed < self::MIN_FILL_SECONDS) {
            return sprintf('too fast (%ds)', $elapsed);
        }
        if ($elapsed > self::MAX_FILL_SECONDS) {
            return sprintf('form expired (%ds)', $elapsed);
        }

        return null;
    }

    private function signTimestamp(int $timestamp): string
    {
        return $timestamp . '.' . hash_hmac('sha256', 'register|' . $timestamp, $this->secret);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_home_index');
    }
}
