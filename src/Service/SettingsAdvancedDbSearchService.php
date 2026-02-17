<?php

namespace App\Service;

use App\DTO\SeriesAdvancedDbSearchDTO;
use App\Entity\User;
use App\Repository\SettingsRepository;

readonly class SettingsAdvancedDbSearchService
{
    public function __construct(
        private DateService        $dateService,
        private KeywordService     $keywordService,
        private SettingsRepository $settingsRepository,
    )
    {
    }

    public function get(User $user): SeriesAdvancedDbSearchDTO
    {
        $seriesSearch = new SeriesAdvancedDbSearchDTO(1);
        $keywords = $this->keywordService->getKeywords();
        $seriesSearch->setKeywords($keywords);
        $advancedSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'advanced db search']);
        if (!$advancedSettings) {
            $settings = [
                'page' => 1,
                'language' => "fr",
                'first air date year' => null,
                'first air date GTE' => null,
                'first air date LTE' => null,
                'with origin country' => null,
                'with origin language' => null,
                'with keywords' => "",
                'keyword separator' => ",",
                'with status' => null,
                'sort by' => "s.first_air_date|desc",
            ];
            $advancedSettings->setData($settings);
            $this->settingsRepository->save($advancedSettings, true);
        }
        $settings = $advancedSettings->getData();
        dump($settings);
        $seriesSearch->setPage($settings['page']);
        $seriesSearch->setFirstAirDateYear($settings['first air date year']);
        $seriesSearch->setFirstAirDateGTE($settings['first air date GTE'] ? $this->dateService->newDateImmutable($settings['first air date GTE'], 'Europe/Paris', true) : null);
        $seriesSearch->setFirstAirDateLTE($settings['first air date LTE'] ? $this->dateService->newDateImmutable($settings['first air date LTE'], 'Europe/Paris', true) : null);
        $seriesSearch->setWithOriginCountry($settings['with origin country']);
        $seriesSearch->setWithOriginalLanguage($settings['with origin language']);
        $seriesSearch->setWithKeywords($settings['with keywords']);
        $seriesSearch->setKeywordSeparator($settings['keyword separator']);
        $seriesSearch->setWithStatus($settings['with status']);
        $seriesSearch->setSortBy($settings['sort by']);
        return $seriesSearch;
    }

    public function update(User $user, SeriesAdvancedDbSearchDTO $formData): SeriesAdvancedDbSearchDTO
    {
        $seriesSearch = $formData;
        $advancedSettings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'advanced db search']);

        $gte = $seriesSearch->getFirstAirDateGTE()?->format('Y-m-d');
        $lte = $seriesSearch->getFirstAirDateLTE()?->format('Y-m-d');

        $settings = [
            'page' => $seriesSearch->getPage(),
            'first air date year' => $seriesSearch->getFirstAirDateYear(),
            'first air date GTE' => $gte,
            'first air date LTE' => $lte,
            'with origin country' => $seriesSearch->getWithOriginCountry(),
            'with origin language' => $seriesSearch->getWithOriginalLanguage(),
            'with keywords' => $seriesSearch->getWithKeywords(),
            'keyword separator' => $seriesSearch->getKeywordSeparator(),
            'with status' => $seriesSearch->getWithStatus(),
            'sort by' => $seriesSearch->getSortBy(),
        ];
        $advancedSettings->setData($settings);

        $this->settingsRepository->save($advancedSettings, true);

        return $seriesSearch;
    }
}