<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\NetworkRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserEpisodeNotificationRepository;
use App\Repository\UserRepository;
use App\Repository\WatchProviderRepository;
use App\Service\DateService;
use App\Service\ImageConfiguration;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extra\Intl\IntlExtension;


/** @method User|null getUser() */
#[IsGranted('ROLE_USER')]
#[Route('/{_locale}/user', name: 'app_user_', requirements: ['locale' => 'fr|en|ko'])]
class UserController extends AbstractController
{
    public function __construct(
        private readonly DateService                       $dateService,
        private readonly EntityManagerInterface            $entityManager,
        private readonly ImageConfiguration                $imageConfiguration,
        private readonly ImageService                      $imageService,
        private readonly NetworkRepository                 $networkRepository,
        private readonly SettingsRepository                $settingsRepository,
        private readonly TranslatorInterface               $translator,
        private readonly UserEpisodeNotificationRepository $userEpisodeNotificationRepository,
        private readonly UserRepository                    $userRepository,
        private readonly WatchProviderRepository           $watchProviderRepository,
    )
    {
    }

    #[Route('/profile', name: 'profile')]
    public function profile(Request $request): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $files = $request->files->all();

            if ($avatarFile = $files['user']['avatarFile']) {
                if ($newAvatarFileName = $this->imageService->userFiles2Webp($avatarFile, 'avatars', $user->getUsername())) {
                    $user->setAvatar($newAvatarFileName);
                }
            }
            if ($bannerFile = $files['user']['bannerFile']) {
                if ($newBannerFileName = $this->imageService->userFiles2Webp($bannerFile, 'banners', $user->getUsername())) {
                    $user->setBanner($newBannerFileName);
                }
            }
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_home');
        }
        $translationSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'translations']);
        $translationSettings = $translationSettings ? $translationSettings->getData() : [];
        $languages = (new IntlExtension)->getLanguageNames('fr');
        foreach ($languages as $code => $name) {
            if (str_starts_with($code, 'x-') || str_starts_with($code, 'und-')) {
                unset($languages[$code]); // Remove private use and undetermined languages
            }
            $languages[$code] = ucfirst($name); // Capitalize language names
        }
        $languageSelectHTML = $this->createHTMLLanguageSelect($request->getLocale() ?? 'fr');

        $translations = [
            'Not a valid file type. Update your selection' => $this->translator->trans('Not a valid file type. Update your selection'),
        ];

        return $this->render('user/profile.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'translationSettings' => $translationSettings,
            'languageSelectHTML' => $languageSelectHTML,
            'languages' => $languages,
            'translations' => $translations,
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

    #[Route('/networks', name: 'networks')]
    public function networks(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $networks = $this->getNetworks();
        $userNetworks = $user->getNetworks();
        $userNetworkIds = array_map(function ($p) {
            return $p->getNetworkId();
        }, $userNetworks->toArray());

        return $this->render('user/networks.html.twig', [
            'networks' => $networks,
            'user' => $user,
            'userNetworkIds' => $userNetworkIds,
        ]);
    }

    #[Route('/provider/toggle/{id}', name: 'provider_toggle')]
    public function providerToggle($id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $provider = $this->watchProviderRepository->findOneBy(['providerId' => $id]);

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

    #[Route('/network/toggle/{id}', name: 'network_toggle')]
    public function networkToggle($id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $network = $this->networkRepository->findOneBy(['networkId' => $id]);

        if ($network) {
            if ($user->getNetworks()->contains($network)) {
                $user->removeNetwork($network);
            } else {
                $user->addNetwork($network);
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

    #[Route('/language-settings', name: 'language_settings', methods: ['POST'])]
    public function languageSettings(Request $request): JsonResponse
    { // https://localhost:8000/fr/user/language-settings/
        if ($request->isMethod('POST')) {
            // action: delete, add
            // languageType: targeted, preferred
            // languageId: fr,en,..

            $payload = $request->getPayload()->all();
            $action = $payload['action'] ?? null;
            $languageType = $payload['languageType'] . '_languages' ?? null;
            $languageId = $payload['languageId'] ?? null;

            $languageSettings = $this->settingsRepository->findOneBy(['name' => 'translations']);

            if ($languageSettings) {
                $data = $languageSettings->getData();
                if ($action === 'delete') {
                    if (isset($data[$languageType]) && in_array($languageId, $data[$languageType], true)) {
                        $data[$languageType] = array_diff($data[$languageType], [$languageId]);
                        $languageSettings->setData($data);
                        $this->settingsRepository->save($languageSettings, true);
                    }
                } elseif ($action === 'add') {
                    if (!isset($data[$languageType])) {
                        $data[$languageType] = [];
                    }
                    if (!in_array($languageId, $data[$languageType], true)) {
                        $data[$languageType][] = $languageId;
                        $languageSettings->setData($data);
                        $this->settingsRepository->save($languageSettings, true);
                    }
                }
            }

            return new JsonResponse([
                'ok' => true,
                'body' => [
                    'action' => $action,
                    'languageType' => $languageType,
                    'message' => $action === 'delete' ? ('Language removed from ' . $languageType) : ('Language added to ' . $languageType),
                ]
            ]);
        } else {
            return new JsonResponse(['ok' => false, 'message' => 'Invalid request method'], Response::HTTP_BAD_REQUEST);
        }
    }

    public function getProviders($user): array

    {
        $country = $user->getCountry() ?? 'FR';

        $providers = $this->watchProviderRepository->getWatchProviderList($country);
        $arr = array_map(function ($provider) {
            return [
                'id' => $provider['provider_id'],
                'name' => $provider['provider_name'],
                'logo' => $this->getProviderLogoFullPath($provider['logo_path']), //$this->imageConfiguration->getCompleteUrl($provider['logo_path'], 'logo_sizes', 2)
            ];
        }, $providers);
        usort($arr, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $arr;
    }

    public function getNetworks(): array
    {
        $networks = $this->networkRepository->getNetworkList();
        $arr = array_map(function ($network) {
            return [
                'id' => $network['network_id'],
                'name' => $network['name'],
                'logo' => $network['logo_path'] ? $this->imageConfiguration->getCompleteUrl($network['logo_path'], 'logo_sizes', 3) : null
            ];
        }, $networks);
        usort($arr, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $arr;
    }

    public function getProviderLogoFullPath(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, '/')) {
            return $this->imageConfiguration->getCompleteUrl($path, 'logo_sizes', 2);
        }
        return '/images/providers' . substr($path, 1);
    }

    private function createHTMLLanguageSelect(string $locale): string
    {
        $languages = (new IntlExtension)->getLanguageNames($locale);
        $seriesLanguages = $this->userRepository->getSeriesLanguages($locale);
        $moviesLanguages = $this->userRepository->getMoviesLanguages($locale);
        $codes = array_merge($seriesLanguages, $moviesLanguages);
        $codes = array_unique(array_map(function ($lang) {
            return $lang['original_language'];
        }, $codes));
        $preferredLanguages = [];
        foreach ($codes as $code) {
            if (str_starts_with($code, 'x-')) {
                continue; // Skip private use languages
            }
            if (str_starts_with($code, 'und-')) {
                continue; // Skip undetermined languages
            }
            $preferredLanguages[$code] = $languages[$code] ?? $code;
        }
        uasort($preferredLanguages, function ($a, $b) {
            return $a <=> $b;
        });

        $html = '<option value="" selected disabled></option>';
        foreach ($preferredLanguages as $code => $name) {
            $name = ucfirst($name);
            $html .= "<option value=\"$code\">$name</option>";
        }
        $html .= '<hl/>';
        foreach ($languages as $code => $name) {
            if (key_exists($code, $preferredLanguages)) {
                continue; // Skip languages already in preferred languages
            }
            $name = ucfirst($name);
            $html .= "<option value=\"$code\">$name</option>";
        }
        $html .= '';
        return $html;
    }
}
