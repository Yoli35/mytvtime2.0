<?php

namespace App\Api;

use App\Controller\PeopleController;
use App\Entity\SeriesCast;
use App\Entity\User;
use App\Repository\PeopleRepository;
use App\Repository\PeopleUserPreferredNameRepository;
use App\Repository\SeriesCastRepository;
use App\Repository\SeriesRepository;
use App\Service\ImageConfiguration;
use App\Service\TMDBService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/api/series/cast', name: 'api_series_cast_')]
readonly class ApiSeriesCast
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure                           $renderView,
        private ImageConfiguration                $imageConfiguration,
        private PeopleController                  $peopleController,
        private PeopleRepository                  $peopleRepository,
        private PeopleUserPreferredNameRepository $peopleUserPreferredNameRepository,
        private SeriesCastRepository              $seriesCastRepository,
        private SeriesRepository                  $seriesRepository,
        private TMDBService                       $tmdbService,
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $inputBag = $request->getPayload();
        $id = $inputBag->get('id');
        $seasonNumber = $inputBag->get('seasonNumber');
        $peopleId = $inputBag->get('peopleId');
        $characterName = $inputBag->get('characterName');
        $series = $this->seriesRepository->findOneBy(['id' => $id]);
        $seriesLocalizedName = $series->getLocalizedName($request->getLocale());
        $seriesName = $seriesLocalizedName->getName() ?? $series->getName();

        // TODO: implement cast editing
        $people = json_decode($this->tmdbService->getPerson($peopleId, 'en-US'), true);
        $peopleDb = $this->peopleRepository->findOneBy(['tmdbId' => $peopleId]);
        if (!$peopleDb) {
            $peopleDb = $this->peopleController->savePeople($people);
        }
        $seriesCast = $this->seriesCastRepository->findOneBy(['series' => $series, 'people' => $peopleDb]);
        if (!$seriesCast) {
            $seriesCast = new SeriesCast($series, $peopleDb, $seasonNumber, $characterName);
            $this->seriesCastRepository->save($seriesCast, true);
            $message = $people['name'] . ($characterName ? ' as ' . $characterName : '') . ' has been added to ';
        } else {
            if ($characterName && $characterName != $seriesCast->getCharacterName()) {
                $seriesCast->setCharacterName($characterName);
                $this->seriesCastRepository->save($seriesCast, true);
                $message = $people['name'] . ' as ' . $characterName . ' has been updated to ';
            } else {
                $message = $people['name'] . ' is already in ';
            }
        }
        $slugger = new AsciiSlugger();
        $userPreferredName = $this->peopleUserPreferredNameRepository->findOneBy(['user' => $user, 'tmdbId' => $peopleId]);
        if ($userPreferredName) {
            $slug = $slugger->slug($userPreferredName->getName())->lower()->toString();
        } else {
            $slug = $slugger->slug($people['name'])->lower()->toString();
        }
        $profileUrl = $this->imageConfiguration->getUrl('profile_sizes', 2);

        // '_blocks/series/_cast.html.twig', {people: people, role: people.character}
        return new JsonResponse([
            'ok' => true,
            'success' => true,
            'block' => ($this->renderView)('_blocks/series/_cast.html.twig', [
                'people' => [
                    'id' => $peopleId,
                    'name' => $peopleDb->getName(),
                    'order' => -1, // Means user added
                    'preferred_name' => $userPreferredName?->getName(),
                    'profile_path' => $peopleDb->getProfilePath() ? $profileUrl . $peopleDb->getProfilePath() : null,
                    'slug' => $slug,
                    'tmdb_id' => $peopleDb->getTmdbId(),
                ],
                'role' => $seriesCast->getCharacterName(),
            ]),
            'message' => $message . $seriesName . '.',
        ]);
    }
}