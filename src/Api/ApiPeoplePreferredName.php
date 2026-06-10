<?php

namespace App\Api;

use App\Entity\PeopleUserPreferredName;
use App\Entity\User;
use App\Repository\PeopleUserPreferredNameRepository;
use App\Service\TMDBService;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/people/preferred-name', name: 'api_people_preferred_name_')]
readonly class ApiPeoplePreferredName
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure $json,
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure $renderView,
        private PeopleUserPreferredNameRepository $peopleUserPreferredNameRepository,
        private TMDBService $tmdbService,
    )
    {}

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(#[CurrentUser] User $user, Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $id = $data['id'];
        $name = $data['name'] ? trim($data['name']) : false;
        $newName = $data['new'] ? trim($data['new']) : false;
        $name = $newName ?: $name;

        $peopleUserPreferredName = $this->peopleUserPreferredNameRepository->findOneBy(['user' => $user, 'tmdbId' => $id]);
        if (!$peopleUserPreferredName) {
            $peopleUserPreferredName = new PeopleUserPreferredName($user, $id, $name);
        } else {
            $peopleUserPreferredName->setName($name);
        }
        $this->peopleUserPreferredNameRepository->save($peopleUserPreferredName, true);
        $peopleUserPreferredName = $this->peopleUserPreferredNameRepository->findOneBy(['user' => $user, 'tmdbId' => $id]);
        $name = $peopleUserPreferredName->getName() ?? null;

        $standing = $this->tmdbService->getPerson($id, $request->getLocale());
        $people = json_decode($standing, true);
        $nameBlock = ($this->renderView)('_blocks/people/_preferred_name.html.twig', [
            'people' => $people,
            'preferredName' => $name,
        ]);
        return ($this->json)([
            'ok' => true,
            'block' => $nameBlock,
            'preferred-name' => $name,
        ]);
    }
}