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
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = new ContactMessage($user?->getTimezone() ?? 'Europe/Paris');
        if ($user) {
            $data->setName($user->getUsername());
            $data->setEmail($user->getEmail());
        }
        $form = $this->createForm(ContactType::class, $data);
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
            return $this->redirectToRoute('app_home_index');
        }
        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
            'bgImage' => $this->imageService->getRandomBlurredPosters(),
        ]);
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
