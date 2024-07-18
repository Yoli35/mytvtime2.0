<?php

namespace App\Service;


class KeywordService
{
    public function __construct()
    {
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
        $diff = array_diff($keywordsList, $keywordsOk);
        $values = array_values($diff);
        dump(['keywords' => $keywordsList, 'ok' => $keywordsOk, 'diff' => $diff, 'values' => $values]);
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
}