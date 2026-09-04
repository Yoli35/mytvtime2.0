<?php

namespace App\Service;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;

readonly class TvTimeService
{
    public function __construct(
        private SettingsRepository $settingsRepository,
    )
    {
    }

    public function getTvTimeData(User $user): array
    {
        $settings = $this->getSettings($user);
        $settings['count']++;
        $this->setSettings($user, $settings);
        return $settings;
    }

    public function setTvTimeLayout(User $user, int $layout): void
    {
        $data = $this->getSettings($user);
        $data['list'] = $layout;
        $this->setSettings($user, $data);
    }

    private function getSettings(User $user): array
    {
        $s =  $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'tv time']);
        if ($s === null) {
            return ['count' => 0, 'list' => 0];
        }
        return $s->getData();
    }

    private function setSettings(User $user, array $data): void
    {
        $s = $this->settingsRepository->findOneBy(['user' => $user, 'name' => 'tv time']);
        if ($s === null) {
            $s = new Settings($user, 'tv time', $data);
        } else {
            $s->setData($data);
        }
        $this->settingsRepository->save($s, true);
    }
}