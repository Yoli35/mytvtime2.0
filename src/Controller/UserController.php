<?php

namespace App\Controller;

use App\Entity\Provider;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\EpisodeNotificationRepository;
use App\Repository\ProviderRepository;
use App\Repository\UserEpisodeNotificationRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_USER')]
#[Route('/{_locale}/user', name: 'app_user_', requirements: ['locale' => 'fr|en|de|es'])]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface            $entityManager,
        private readonly UserEpisodeNotificationRepository $userEpisodeNotificationRepository,
        private readonly DateService                       $dateService,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly ProviderRepository                $providerRepository,
//        private readonly TMDBService                       $tmdbService,
        private readonly WatchProviderRepository           $watchProviderRepository,
    )
    {
    }

    #[Route('/profile', name: 'profile')]
    public function profile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('user/profile.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/providers', name: 'providers')]
    public function providers(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $providers = $this->getProviders($user);
        $userProviders = $user->getProviders();
        $userProviderIds = array_map(function ($p) {
            return $p->getProviderId();
        }, $userProviders->toArray());

        return $this->render('user/providers.html.twig', [
            'providers' => $providers,
            'user' => $user,
            'userProviderIds' => $userProviderIds,
        ]);
    }

    #[Route('/provider/toggle/{id}', name: 'provider_toggle')]
    public function providerToggle($id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $provider = $this->providerRepository->findOneBy(['providerId' => $id]);

        if ($provider) {
            if ($user->getProviders()->contains($provider)) {
                $user->removeProvider($provider);
            } else {
                $user->addProvider($provider);
            }
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            return $this->json(['status' => 'ok']);
        }
        return $this->json(['status' => 'error']);
    }

    #[Route('/is-connected', name: 'is_connected')]
    public function isStillConnected(): Response
    {
        return $this->json([
            'ok' => true,
            'body' => ['connected' => $this->getUser() !== null],
        ]);
    }

    #[Route('/notifications/mark-as-read', name: 'notifications-mark-as-read', methods: ['GET'])]
    public function markNotificationsAsRead(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notifications = $this->userEpisodeNotificationRepository->findBy(['user' => $user, 'validatedAt' => null]);
        $now = $this->dateService->getNowImmutable($user->getTimeZone() ?? 'UTC');
        foreach ($notifications as $notification) {
            $notification->setValidatedAt($now);
        }
        $this->entityManager->flush();
        return $this->json(['ok' => true]);
    }

    public function getProviders($user): array
    {
//        $language = $user->getPreferredLanguage() ?? 'fr' . '-' . $user->getCountry() ?? 'FR';
        $country = $user->getCountry() ?? 'FR';
//        $tmdbProviders = json_decode($this->tmdbService->getTvWatchProviderList($language, $country), true);
//        $localProviders = $this->providerRepository->findAll();
//        $newLocalProvider = false;
//
//        foreach ($tmdbProviders['results'] as $provider) {
//            if (!$this->isLocalProvider($provider, $localProviders)) {
//                $localProvider = new Provider();
//                $localProvider->setProviderId($provider['provider_id']);
//                $localProvider->setName($provider['provider_name']);
//                $localProvider->setLogoPath($provider['logo_path']);
//                $this->entityManager->persist($localProvider);
//                $newLocalProvider = true;
//            }
//        }
//        if ($newLocalProvider) {
//            $this->entityManager->flush();
//        }

        $providers = $this->watchProviderRepository->getWatchProviderList($country);
        $arr = array_map(function ($provider) {
            return [
                'id' => $provider['provider_id'],
                'name' => $provider['provider_name'],
                'logo' => $this->imageConfiguration->getCompleteUrl($provider['logo_path'], 'logo_sizes', 2)
            ];
//        }, $tmdbProviders['results'] ?? []);
        }, $providers);
        usort($arr, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $arr;
    }

    public function isLocalProvider($provider, $localProviders): bool
    {
        foreach ($localProviders as $localProvider) {
            if ($provider['provider_id'] === $localProvider->getProviderId()) {
                return true;
            }
        }
        return false;
    }
}
