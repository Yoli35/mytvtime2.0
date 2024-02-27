<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/user')]
class UserController extends AbstractController
{
    #[Route('/profile', name: 'app_user_profile')]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
//            dd($form->getData());
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('user/profile.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
