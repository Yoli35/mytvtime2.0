<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserOAuthAccount;
use App\Repository\UserOAuthAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class OAuthController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/{_locale}/account/connections', name: 'app_account_connections', requirements: ['_locale' => 'fr|en|ko'], methods: ['GET'])]
    public function connections(
        #[CurrentUser] User $user,
        UserOAuthAccountRepository $accounts
    ): Response {
        $linked = [
            'google' => (bool) $accounts->findOneByUserAndProvider((int) $user->getId(), 'google'),
            'github' => (bool) $accounts->findOneByUserAndProvider((int) $user->getId(), 'github'),
            'apple'  => (bool) $accounts->findOneByUserAndProvider((int) $user->getId(), 'apple'),
        ];

        return $this->render('o_auth/connections.html.twig', [
            'linked' => $linked,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{_locale}/connect/{provider}', name: 'app_oauth_connect', requirements: ['_locale' => 'fr|en|ko', 'provider' => 'google|github|apple'], methods: ['GET'])]
    public function connect(Request $request, string $provider, ClientRegistry $clientRegistry): RedirectResponse
    {
        $locale = $request->getLocale();

        $clientName = $provider;
        if ($provider === 'github') {
            $clientName = match ($locale) {
                'en' => 'github_en',
                'ko' => 'github_ko',
                default => 'github_fr',
            };
        }

        return $clientRegistry
            ->getClient($clientName)
            ->redirect();
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{_locale}/connect/{provider}/check', name: 'app_oauth_check', requirements: ['_locale' => 'fr|en|ko', 'provider' => 'google|github|apple'], methods: ['GET'])]
    public function check(
        Request $request,
        string $provider,
        #[CurrentUser] User $user,
        ClientRegistry $clientRegistry,
        UserOAuthAccountRepository $accounts,
        EntityManagerInterface $em
    ): RedirectResponse {
        $locale = $request->getLocale();
        $clientName = $provider;
        if ($provider === 'github') {
            $clientName = match ($locale) {
                'en' => 'github_en',
                'ko' => 'github_ko',
                default => 'github_fr',
            };
        }
        $client = $clientRegistry->getClient($clientName);

        // Récupère le "resource owner" (utilisateur côté provider)
        $accessToken = $client->getAccessToken();
        $resourceOwner = $client->fetchUserFromToken($accessToken);

        $providerUserId = (string) $resourceOwner->getId();
        if ($providerUserId === '') {
            $this->addFlash('error', 'Impossible de récupérer votre identifiant chez le fournisseur.');
            return $this->redirectToRoute('app_account_connections', ['_locale' => $request->getLocale()]);
        }

        // 1) Empêcher qu’un même compte Google/GitHub/Apple soit lié à 2 users
        $alreadyLinked = $accounts->findOneByProviderAndProviderUserId($provider, $providerUserId);
        if ($alreadyLinked && $alreadyLinked->getUser()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Ce compte est déjà lié à un autre utilisateur.');
            return $this->redirectToRoute('app_account_connections', ['_locale' => $request->getLocale()]);
        }

        // 2) Empêcher de lier 2x le même provider au même user
        $existingForUser = $accounts->findOneByUserAndProvider((int) $user->getId(), $provider);
        if ($existingForUser) {
            $this->addFlash('info', 'Ce fournisseur est déjà lié à votre compte.');
            return $this->redirectToRoute('app_account_connections', ['_locale' => $request->getLocale()]);
        }

        $link = (new UserOAuthAccount())
            ->setUser($user)
            ->setProvider($provider)
            ->setProviderUserId($providerUserId);

        $em->persist($link);
        $em->flush();

        $this->addFlash('success', ucfirst($provider) . ' a été lié à votre compte.');

        return $this->redirectToRoute('app_account_connections', ['_locale' => $request->getLocale()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{_locale}/account/connections/{provider}/unlink', name: 'app_oauth_unlink', requirements: ['_locale' => 'fr|en|ko', 'provider' => 'google|github|apple'], methods: ['POST'])]
    public function unlink(
        Request $request,
        string $provider,
        #[CurrentUser] User $user,
        UserOAuthAccountRepository $accounts,
        EntityManagerInterface $em
    ): RedirectResponse {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('unlink_oauth_' . $provider, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_account_connections', ['_locale' => $request->getLocale()]);
        }

        $existingForUser = $accounts->findOneByUserAndProvider((int) $user->getId(), $provider);
        if (!$existingForUser) {
            $this->addFlash('info', 'Aucune liaison à supprimer.');
            return $this->redirectToRoute('app_account_connections', ['_locale' => $request->getLocale()]);
        }

        $em->remove($existingForUser);
        $em->flush();

        $this->addFlash('success', ucfirst($provider) . ' a été délié.');

        return $this->redirectToRoute('app_account_connections', ['_locale' => $request->getLocale()]);
    }
}