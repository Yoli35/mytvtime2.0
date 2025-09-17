<?php

namespace App\Api;

use App\Service\KeywordService;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/keywords', name: 'keywords_')]
class Keywords extends AbstractController
{
    public function __construct(
        private readonly KeywordService $keywordService,
        private readonly TmdbService    $tmdbService,
    )
    {
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $tmdbId = $data['id'];
        $type = $data['type'];
        $keywords = $data['keywords'];
        $language = $data['language'];

        $keywordYaml = $this->keywordService->getTranslationLines($language);

        $n = count($keywords);
        for ($i = 0; $i < $n; $i++) {
            $line = $keywords[$i]['original'] . ': ' . str_replace(':', '→', $keywords[$i]['translated']) . "\n";
            $keywordYaml[] = $line;
        }
        usort($keywordYaml, fn($a, $b) => $a <=> $b);

        $filename = '../translations/keywords.' . $language . '.yaml';
        $res = fopen($filename, 'w');

        foreach ($keywordYaml as $line) {
            fputs($res, $line);
        }
        fclose($res);

        if ($type == 'movie') {
            $keywords = json_decode($this->tmdbService->getMovieKeywords($tmdbId), true);
            $keywords = $keywords['keywords'];
        }
        if ($type == 'series') {
            $keywords = json_decode($this->tmdbService->getTvKeywords($tmdbId), true);
            $keywords = $keywords['results'];
        }
        $missingKeywords = $this->keywordService->keywordsTranslation($keywords, $language);
        $keywordBlock = $this->renderView('_blocks/_keywords.html.twig', [
            'id' => $tmdbId,
            'keywords' => $keywords,
            'missing' => $missingKeywords,
        ]);

        // fetch response
        return $this->json([
            'ok' => true,
            'keywords' => $keywordBlock,
        ]);
    }
}