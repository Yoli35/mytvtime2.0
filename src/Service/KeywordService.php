<?php

namespace App\Service;


use App\Entity\Keyword;
use App\Entity\Series;
use App\Repository\KeywordRepository;
use App\Repository\SeriesRepository;
use Closure;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;

readonly class KeywordService
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)]
        private Closure           $addFlash,
        private KeywordRepository $keywordRepository,
        private SeriesRepository  $seriesRepository,
    )
    {
    }

    public function keywordsCleaning(array $keywords): array
    {
        return array_map(function ($keyword) {
            $name = trim($keyword['name'], " \n\r\t\v\0\u{A0}");
            return ['id' => $keyword['id'], 'name' => preg_replace('/\s+/', ' ', $name)];
        }, $keywords);
    }

    public function keywordsTranslation($keywords, $locale): array
    {
        $translatedKeywords = $this->getTranslations($locale);
        $keywordsList = [];
        $keywordsOk = [];

        foreach ($keywords as $keyword) {
            $keywordsList[] = $keyword['name'];
            foreach ($translatedKeywords as $value) {
                if (!strcmp(trim($keyword['name']), trim($value[0]))) {
                    $keywordsOk[] = $keyword['name'];
                    break;
                }
            }
        }
        return array_values(array_diff($keywordsList, $keywordsOk));
    }

    public function getTranslations($locale): array
    {
        $filename = '../translations/keywords.' . $locale . '.yaml';
        $res = fopen($filename, 'a+');
        $ks = [];

        while (!feof($res)) {
            $line = fgets($res);
            $ks[] = explode(": ", $line);
        }
        fclose($res);
        return $ks;
    }

    public function getTranslationLines($locale): array
    {
        $filename = '../translations/keywords.' . $locale . '.yaml';
        $res = fopen($filename, 'a+');
        $ks = [];

        while (!feof($res)) {
            $ks[] = fgets($res);
        }
        fclose($res);
        return $ks;
    }

    public function saveKeywords(array $results, string $source): array
    {
        $messages = [];
        if (!count($results)) {
            return [];
        }
        $results = $this->keywordsCleaning($results);
        $ids = array_column($results, 'id');

        $dbIds = array_map(function ($keyword) {
            return $keyword->getKeywordId();
        }, $this->keywordRepository->findBy(['keywordId' => $ids]));

        $missingKeywords = array_map(function ($id) use ($results) {
            return array_find($results, function (array $value) use ($id) {
                return $id == $value['id'];
            });
        }, array_diff($ids, $dbIds));

        if (count($missingKeywords) > 0) {
            foreach ($missingKeywords as $keyword) {
                $newDbKeyword = new Keyword($keyword['name'], $keyword['id']);
                $this->keywordRepository->save($newDbKeyword);
                if ('controller' === $source) {
                    ($this->addFlash)('success', 'Keyword "' . $keyword['name'] . '" added');
                }
                if ('api' === $source) {
                    $messages[] = 'Keyword "' . $keyword['name'] . '" added';
                }
            }
            $this->keywordRepository->flush();
        }
        return in_array($source,  ['controller', 'command']) ? $results : $messages;
    }

    public function addKeywords(Series $series, array $results): string
    {
        $ids = array_column($results, 'id');
        $seriesKeywords = $series->getKeywords();
        $sIds = array_map(function ($keyword) {
            return $keyword->getKeywordId();
        }, $seriesKeywords->toArray());
        $newIds = array_diff($ids, $sIds);
        if (count($newIds) == 0) {
            return '';
        }
        $dbKeywords = $this->keywordRepository->findBy(['keywordId' => $newIds]);
        $newKeywords = [];
        foreach ($newIds as $newId) {
            $newKeyword = array_find($dbKeywords, function (Keyword $k) use ($newId) { return $k->getKeywordId() == $newId; });
            $series->addKeyword($newKeyword);
            $newKeywords[] = $newKeyword->getName();
        }
        $this->seriesRepository->flush();

        return implode(', ', $newKeywords);
    }

    public function getKeywords(): array
    {
        $keywords = $this->keywordRepository->findby([], ['name' => 'ASC']);

        $keywordArray = [];
        foreach ($keywords as $keyword) {
            $keywordArray[$keyword->getName()] = $keyword->getKeywordId();
        }
        return $keywordArray;
    }
}