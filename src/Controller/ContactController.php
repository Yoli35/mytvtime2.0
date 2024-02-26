<?php

namespace App\Controller;

use App\DTO\ContactDTO;
use App\Entity\User;
use App\Form\ContactType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface     $mailer,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = new ContactDTO();
        if ($user) {
            $data->setName($user->getUsername());
            $data->setEmail($user->getEmail());
        }
        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Send mail
            $mail = (new TemplatedEmail())
                ->from($data->getEmail())
                ->to('contact@mytvtime.fr')
                ->subject($this->translator->trans('Contact form') . ' - ' . $data->getSubject())
                ->htmlTemplate('emails/contact.html.twig')
                ->context([
                    'data' => $data,
                ])
                ->locale($user?->getPreferredLanguage() ?? $request->getLocale());
            try {
                $this->mailer->send($mail);
            } catch (TransportExceptionInterface $e) {
                dump($e);
                $this->addFlash('error', $this->translator->trans('An error occurred, please try again later'));
            }
            $this->addFlash('success', $this->translator->trans('Your message has been sent'));
            return $this->redirectToRoute('app_home');
        }
        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
