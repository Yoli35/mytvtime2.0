<?php

namespace App\Service;

use App\DTO\SeriesAdvancedDbSearchDTO;
use App\Entity\User;
use App\Repository\SettingsRepository;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SettingsAdvancedDbSearchService
{
    public function __construct(
        private DateService         $dateService,
        private KeywordService      $keywordService,
        private SettingsRepository  $settingsRepository,
        private TranslatorInterface $translator
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
                'language' => $user->getPreferredLanguage() ?? "fr",
                'timezone' => $user->getTimezone() ?? "Europe/Paris",
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
        $seriesSearch->setLanguage($settings['language']);
        $seriesSearch->setTimezone($settings['timezone']);
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
            'language' => $seriesSearch->getLanguage(),
            'timezone' => $seriesSearch->getTimezone(),
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

    public function getSearchDetails(SeriesAdvancedDbSearchDTO $seriesSearch): string
    {
        $details = "";
        $allKeywords = $seriesSearch->getKeywords();
        $sortStrings = [
            'us.added_at|desc' => 'Date added descending',
            'us.added_at|asc' => 'Date added ascending',
            's.first_air_date|desc' => 'First air date descending',
            's.first_air_date|asc' => 'First air date ascending',
            'display_name|desc' => 'Name descending',
            'display_name|asc' => 'Name ascending',
            's.original_name|desc' => 'Original name descending',
            's.original_name|asc' => 'Original name ascending',
        ];

        if ($seriesSearch->getFirstAirDateYear()) {
            $details .= "<div class='search-details'>" . $this->translator->trans('Year') . ' ' . $seriesSearch->getFirstAirDateYear() . "</div>";
        }
        if ($seriesSearch->getFirstAirDateGTE()) {
            $details .= "<div class='search-details'>" . $this->translator->trans('After') . ' ' . $this->dateService->formatDate($seriesSearch->getFirstAirDateGTE()->format('Y-m-d'), $seriesSearch->getTimezone(), $seriesSearch->getLanguage()) . "</div>";
        }
        if ($seriesSearch->getFirstAirDateLTE()) {
            $details .= "<div class='search-details'>" . $this->translator->trans('Before') . ' ' . $this->dateService->formatDate($seriesSearch->getFirstAirDateLTE()->format('Y-m-d'), $seriesSearch->getTimezone(), $seriesSearch->getLanguage()) . "</div>";
        }
        if ($seriesSearch->getWithOriginCountry()) {
            $code = $seriesSearch->getWithOriginCountry();
            $details .= "<div class='search-details'>" . $this->translator->trans('Country') . ' ' . $this->getEmojiFlag($code) . ' ' . Countries::getName($code) . '</div>';
        }
        if ($seriesSearch->getWithOriginalLanguage()) {
            $code = $seriesSearch->getWithOriginalLanguage();
            $details .= "<div class='search-details'>" . $this->translator->trans('Language') . ' ' . Languages::getName($code) . '</div>';
        }
        if ($seriesSearch->getWithKeywords()) {
            $keywords = explode($seriesSearch->getKeywordSeparator(), $seriesSearch->getWithKeywords());
            $details .= "<div class='search-details'>" . $this->translator->trans('Keywords') . ' ' . $this->keywordList($keywords, $allKeywords) . '</div>';
        }
        if ($seriesSearch->getWithStatus()) {
            $details .= "<div class='search-details'>" . $this->translator->trans('Status') . ' ' . $this->translator->trans($seriesSearch->getWithStatus()) . '</div>';
        }
        if ($seriesSearch->getSortBy()) {
            $details .= "<div class='search-details'>" . $this->translator->trans('Sort by') . ' ' . lcfirst($this->translator->trans($sortStrings[$seriesSearch->getSortBy()])) . '</div>';
        }

        return $details;
    }

    public function getEmojiFlag(string $countryCode): string
    {
        $regionalOffset = 0x1F1A5;

        return mb_chr($regionalOffset + mb_ord($countryCode[0], 'UTF-8'), 'UTF-8')
            . mb_chr($regionalOffset + mb_ord($countryCode[1], 'UTF-8'), 'UTF-8');
    }

    private function keywordList(array $keywords, array $allKeywords): string
    {
        $keywordList = '';
        foreach ($keywords as $kCode) {
            $keywordList .= $this->getKeyword($allKeywords, $kCode);
        }
        return $keywordList;
    }

    private function getKeyword(array $keywords, int $code): string
    {
        foreach ($keywords as $keyword => $kCode) {
            if ($kCode === $code) {
                return '<div class="keyword">' . $keyword . '</div>';
            }
        }
        return '';
    }
}