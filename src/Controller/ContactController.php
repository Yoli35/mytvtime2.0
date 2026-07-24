<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Entity\User;
use App\Form\ContactType;
use App\Repository\ContactMessageRepository;
use App\Service\ContactBlocklistService;
use App\Service\ImageService;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}/contact', name: 'app_contact_', requirements: ['_locale' => 'fr|en|ko'])]
class ContactController extends AbstractController
{
    private const array BLOCKED_REDIRECT_URLS = [
        'https://www.netflix.com/',
        'https://www.primevideo.com/',
        'https://www.apple.com/apple-tv-plus/',
        'https://www.disneyplus.com/',
    ];

    public function __construct(
        private readonly ContactMessageRepository $contactMessageRepository,
        private readonly ContactBlocklistService  $contactBlocklistService,
        private readonly ImageService             $imageService,
        private readonly MailerInterface          $mailer,
        private readonly LoggerInterface          $logger,
        private readonly TranslatorInterface      $translator,
    )
    {
    }

    #[Route('/', name: 'form', methods: ['GET', 'POST'])]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $data = new ContactMessage($user?->getTimezone() ?? 'Europe/Paris');
        if ($user) {
            $data->setName($user->getUsername());
            $data->setEmail($user->getEmail());
        }
        $form = $this->createForm(ContactType::class, $data);
        // On initial GET, remember the page the user came from so we can
        // redirect back to it after the form is submitted.
        if (!$request->isMethod('POST')) {
            $form->get('referer')->setData($request->headers->get('referer'));
        }
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $submittedName = $form->get('name')->getData();
            $submittedEmail = $form->get('email')->getData();
            if (
                (is_string($submittedName) && $this->contactBlocklistService->isBlockedName($submittedName))
                || (is_string($submittedEmail) && $this->contactBlocklistService->isBlockedEmail($submittedEmail))
            ) {
                return $this->redirect($this->getRandomBlockedRedirectUrl());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->contactMessageRepository->save($data, true);
            // Send mail
            $mail = new TemplatedEmail()
                ->from($data->getEmail())
                ->to('contact@mytvtime.fr')
                ->subject($this->translator->trans('Contact form') . ' - ' . $data->getSubject())
                ->htmlTemplate('emails/contact.html.twig')
                ->context([
                    'title' => 'Contact message',
                    'data' => $data,
                ])
                ->locale($user?->getPreferredLanguage() ?? $request->getLocale());
            try {
                $this->mailer->send($mail);
            } catch (TransportExceptionInterface) {
                $this->addFlash('error', $this->translator->trans('An error occurred, please try again later'));
            }
            $this->addFlash('success', $this->translator->trans('Your message has been sent'));
            // Redirect to the page the user came from (captured on initial GET),
            // falling back to the request referer, then the homepage. The URL is
            // validated to point to this site to prevent open redirects.
            $referer = $this->getSafeRedirectUrl($form->get('referer')->getData(), $request)
                ?? $this->getSafeRedirectUrl($request->headers->get('referer'), $request);
            if ($referer === null) {
                return $this->redirectToRoute('app_home_index', ['_locale' => $request->getLocale()]);
            }
            return $this->redirect($referer);
        }
        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
            'bgImage' => $this->imageService->getRandomBlurredPosters(),
        ]);
    }

    /**
     * Returns the given URL only if it safely points to this site, otherwise null.
     * Accepts relative paths (e.g. "/fr/series") and absolute URLs whose host
     * matches the current request host, to prevent open redirects.
     */
    private function getSafeRedirectUrl(mixed $url, Request $request): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }

        // Relative path targeting the same site.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && strcasecmp($host, $request->getHost()) === 0) {
            return $url;
        }

        return null;
    }

    private function getRandomBlockedRedirectUrl(): string
    {
        if (self::BLOCKED_REDIRECT_URLS === []) {
            return 'https://www.disneyplus.com/';
        }

        try {
            return self::BLOCKED_REDIRECT_URLS[random_int(0, count(self::BLOCKED_REDIRECT_URLS) - 1)];
        } catch (RandomException $e) {
            $this->logger->error($e->getMessage());
            return self::BLOCKED_REDIRECT_URLS[0];
        }
    }
}
