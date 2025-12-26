<?php

namespace App\Api;

use App\Entity\PeopleLocalizedBiography;
use App\Entity\User;
use App\Repository\PeopleLocalizedBiographyRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/** @method User|null getUser() */
#[Route('/api/people/biography', name: 'api_people_biography_')]
readonly class ApiPeopleBiography
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure $json,
        private PeopleLocalizedBiographyRepository $repo,
    )
    {}

    #[Route('/{id}', name: 'add', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function add(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $bio = $data['bio'];

        $localizedBio = $this->repo->findOneBy(['tmdbId' => $id, 'locale' => $request->getLocale()]);
        if (!$localizedBio) {
            $localizedBio = new PeopleLocalizedBiography($id, $bio, $request->getLocale());
        } else {
            $localizedBio->setBiography($bio);
        }
        $this->repo->save($localizedBio, true);

        return ($this->json)([
            'success' => true,
        ]);
    }
}