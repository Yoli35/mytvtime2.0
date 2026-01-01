<?php

namespace App\Controller;

//use App\DTO\ContactDTO;
use App\Entity\ContactMessage;
use App\Entity\User;
use App\Form\ContactType;
use App\Repository\ContactMessageRepository;
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
    public function __construct(
        private readonly ContactMessageRepository $contactMessageRepository,
        private readonly MailerInterface     $mailer,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    #[Route('/', name: 'form', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = new ContactMessage($user->getTimezone() ?? 'Europe/Paris');
        if ($user) {
            $data->setName($user->getUsername());
            $data->setEmail($user->getEmail());
        }
        $form = $this->createForm(ContactType::class, $data);
        $form->handleRequest($request);

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
        ]);
    }
}
