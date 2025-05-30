<?php

namespace App\Service;


class KeywordService
{
    public function __construct()
    {
    }

    public function keywordsCleaning(array $keywords): array
    {
        $keywords = array_map(function ($keyword) {
            return ['id' => $keyword['id'], 'name' => trim($keyword['name'], " \n\r\t\v\0\u{A0}")];
        }, $keywords);
        $keywords = array_map(function ($keyword) {
            return ['id' => $keyword['id'], 'name' => preg_replace('/\s+/', ' ', $keyword['name'])];
        }, $keywords);
        /*$keywords = array_map(function ($keyword) {
            return ['name' => preg_replace('/[^\p{L}\p{N}\s]/u', '', $keyword['name'])];
        }, $keywords);*/
        return $keywords;
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
}