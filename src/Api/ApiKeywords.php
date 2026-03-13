<?php

namespace App\Api;

use App\Repository\KeywordRepository;
use App\Service\KeywordService;
use App\Service\TMDBService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/keywords', name: 'keywords_')]
class ApiKeywords extends AbstractController
{
    public function __construct(
        private readonly KeywordRepository $keywordRepository,
        private readonly KeywordService    $keywordService,
        private readonly TmdbService       $tmdbService,
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
        $noUpdate = $data['noUpdate'] ?? false;

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

        if ($noUpdate) {
            return $this->json([
                'success' => true,
                'message' => 'Keywords updated successfully',
                'keywords' => implode(", ", array_column($keywords, 'original')),
            ]);
        }

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
            'success' => true,
            'keywords' => $keywordBlock,
        ]);
    }

    #[Route('/check', name: 'check', methods: ['POST'])]
    public function check(Request $request): Response
    {
        $keys = ['other', '0-9', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];

        $keywords = $this->keywordRepository->getAll();
        $missingTranslations = $this->keywordService->keywordsTranslation($keywords, $request->getLocale());

        $firstLetterArray = [];
        foreach ($keys as $k) {
            if (!key_exists($k, $firstLetterArray)) {
                $firstLetterArray[$k] = 0;
            }
        }
        foreach ($missingTranslations as $missingTranslation) {
            $firstChar = mb_substr($missingTranslation, 0, 1);
            if (is_numeric($firstChar)) {
                $firstLetterArray['0-9']++;
                continue;
            }
            if ($firstChar < 'a' || $firstChar > 'z') {
                $firstLetterArray['other']++;
                continue;
            }
            if (!key_exists($firstChar, $firstLetterArray)) {
                $firstLetterArray[$firstChar] = 0;
            }
            $firstLetterArray[$firstChar]++;
        }

        return $this->json([
            'ok' => true,
            'firstArray' => $firstLetterArray,
        ]);
    }
}