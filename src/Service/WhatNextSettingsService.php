<?php

namespace App\Service;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;

readonly class WhatNextSettingsService
{
    public function __construct(
        private SettingsRepository $settingsRepository,
    )
    {
    }

    public function getSettings(User $user): array
    {
        $settings = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'seriesWhatNext']);
        if (!$settings) {
            $settings = new Settings($user, 'seriesWhatNext', [
                'default_limit' => 20,
                'default_order' => 'DESC',
                'default_sort' => 'lastWatched',
                'default_link_to' => 'series',
                'limit' => 20,
                'order' => 'DESC',
                'sort' => 'lastWatched',
                'link_to' => 'series',
            ]);
            $this->settingsRepository->save($settings, true);
        }
        return $settings->getData();
    }
}