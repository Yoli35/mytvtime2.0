<?php

namespace App\Api;

use App\Entity\Movie;
use App\Entity\MovieLocalizedName;
use App\Entity\User;
use App\Entity\UserMovie;
use App\Repository\MovieLocalizedNameRepository;
use App\Repository\MovieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** @method User|null getUser() */
#[Route('/api/movie/search', name: 'api_movie_search_')]
class ApiMovieSearch extends AbstractController
{
    public function __construct(
        private readonly MovieLocalizedNameRepository $movieLocalizedNameRepository,
        private readonly MovieRepository $movieRepository,
    )
    {}

    #[Route('/advanced', name: 'advanced', methods: ['POST'])]
    public function get(Request $request, UserMovie $userMovie): Response
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'];
        $movie = $this->movieRepository->findOneBy(['id' => $userMovie->getMovie()->getId()]);
        $slugger = new AsciiSlugger();

        $localizedName = $this->movieLocalizedNameRepository->findOneBy(['movie' => $movie, 'locale' => $request->getLocale()]);
        if ($localizedName) {
            $localizedName->setName($name);
            $localizedName->setSlug($slugger->slug($name));
        } else {
            $slug = $slugger->slug($name)->lower()->toString();
            $localizedName = new MovieLocalizedName($movie, $name, $slug, $request->getLocale());
        }
        $this->movieLocalizedNameRepository->save($localizedName, true);

        return $this->json([
            'ok' => true,
        ]);
    }
}

// certification                     string      use in conjunction with region
// certification.gte                 string      use in conjunction with region
// certification.lte                 string      use in conjunction with region
// certification_country             string      use in conjunction with the certification, certification.gte and certification.lte filters
// include_adult                     boolean     Defaults to false
// include_video                     boolean     Defaults to false
// language                          string      Defaults to en-US
// page                              int32       Defaults to 1
// primary_release_year              int32
// primary_release_date.gte          date
// primary_release_date.lte          date
// region                            string
// release_date.gte                  date
// release_date.lte                  date
// sort_by                           string      enum (original_title.asc,original_title.desc,popularity.asc,popularity.desc,revenue.asc,revenue.desc,
//                                               primary_release_date.asc,title.asc,title.desc,primary_release_date.desc,vote_average.asc,vote_average.desc,
//                                               vote_count.asc,vote_count.desc)
//                                               Defaults to popularity.desc
// vote_average.gte                  float
// vote_average.lte                  float
// vote_count.gte                    float
// vote_count.lte                    float
// watch_region                      string
//                                   use in conjunction with with_watch_monetization_types or with_watch_providers
// with_cast                         string can be a comma (AND) or pipe (OR) separated query
// with_companies                    string can be a comma (AND) or pipe (OR) separated query
// with_crew                         string can be a comma (AND) or pipe (OR) separated query
// with_genres                    *  string can be a comma (AND) or pipe (OR) separated query
// with_keywords                  *  string can be a comma (AND) or pipe (OR) separated query
// with_origin_country            *  string
// with_original_language         *  string
// with_people                       string can be a comma (AND) or pipe (OR) separated query
// with_release_type                 int32
//                                   possible values are: [1, 2, 3, 4, 5, 6] can be a comma (AND) or pipe (OR) separated query, can be used in conjunction with region
// with_runtime.gte                  int32
// with_runtime.lte                  int32
// with_watch_monetization_types     string
//                                   possible values are: [flatrate, free, ads, rent, buy] use in conjunction with watch_region, can be a comma (AND) or pipe (OR) separated query
// with_watch_providers              string use in conjunction with watch_region, can be a comma (AND) or pipe (OR) separated query
// without_companies                 string
// without_genres                    string
// without_keywords                  string
// without_watch_providers           string
// year                              int32