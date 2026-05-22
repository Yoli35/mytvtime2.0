<?php

namespace App\Service;

use App\Entity\Settings;
use App\Repository\SettingsRepository;

class ContactBlocklistService
{
    private const string NAME_SETTING = 'contact_blocked_name_needles';
    private const string EMAIL_SETTING = 'contact_blocked_email_needles';

    private const array DEFAULT_NAME_NEEDLES = [];
    private const array DEFAULT_EMAIL_NEEDLES = [
        'jesnakt',
        'newenergybrokers',
    ];

    public function __construct(private readonly SettingsRepository $settingsRepository)
    {
    }

    public function isBlockedName(string $value): bool
    {
        return $this->matchesNeedles($value, $this->getNameNeedles());
    }

    public function isBlockedEmail(string $value): bool
    {
        return $this->matchesNeedles($value, $this->getEmailNeedles());
    }

    public function getNameNeedles(): array
    {
        return $this->getNeedles(self::NAME_SETTING, self::DEFAULT_NAME_NEEDLES);
    }

    public function getEmailNeedles(): array
    {
        return $this->getNeedles(self::EMAIL_SETTING, self::DEFAULT_EMAIL_NEEDLES);
    }

    public function addNameNeedle(string $needle): bool
    {
        return $this->addNeedle(self::NAME_SETTING, $needle, self::DEFAULT_NAME_NEEDLES);
    }

    public function removeNameNeedle(string $needle): bool
    {
        return $this->removeNeedle(self::NAME_SETTING, $needle);
    }

    public function addEmailNeedle(string $needle): bool
    {
        return $this->addNeedle(self::EMAIL_SETTING, $needle, self::DEFAULT_EMAIL_NEEDLES);
    }

    public function removeEmailNeedle(string $needle): bool
    {
        return $this->removeNeedle(self::EMAIL_SETTING, $needle);
    }

    private function getNeedles(string $settingName, array $defaultNeedles): array
    {
        $settings = $this->settingsRepository->findOneBy([
            'user' => null,
            'name' => $settingName,
        ]);

        if (!$settings) {
            $settings = new Settings(null, $settingName, $this->normalizeNeedles($defaultNeedles));
            $this->settingsRepository->save($settings, true);

            return $settings->getData();
        }

        $needles = $this->normalizeNeedles($settings->getData());
        if ($needles !== $settings->getData()) {
            $settings->setData($needles);
            $this->settingsRepository->save($settings, true);
        }

        return $needles;
    }

    private function addNeedle(string $settingName, string $needle, array $defaultNeedles): bool
    {
        $needle = trim($needle);
        if ($needle === '') {
            return false;
        }

        $settings = $this->settingsRepository->findOneBy([
            'user' => null,
            'name' => $settingName,
        ]);

        if (!$settings) {
            $settings = new Settings(null, $settingName, $this->normalizeNeedles($defaultNeedles));
        }

        $needles = $this->normalizeNeedles($settings->getData());
        if ($this->needleExists($needles, $needle)) {
            return false;
        }

        $needles[] = $needle;
        $settings->setData($needles);
        $this->settingsRepository->save($settings, true);

        return true;
    }

    private function removeNeedle(string $settingName, string $needle): bool
    {
        $needle = trim($needle);
        if ($needle === '') {
            return false;
        }
        $settings = $this->settingsRepository->findOneBy([
            'user' => null,
            'name' => $settingName,
        ]);
        if (!$settings) {
            return false;
        }

        $needles = $this->normalizeNeedles($settings->getData());
        if (!$this->needleExists($needles, $needle)) {
            return false;
        }

        $needles = array_filter($needles, static fn (string $existingNeedle): bool => mb_strtolower($existingNeedle) !== mb_strtolower($needle));
        $settings->setData($needles);
        $this->settingsRepository->save($settings, true);

        return true;
    }

    private function matchesNeedles(string $value, array $needles): bool
    {
        $normalizedValue = mb_strtolower($value);

        return array_any(
            $needles,
            static fn (string $needle): bool => $needle !== '' && str_contains($normalizedValue, mb_strtolower($needle)),
        );
    }

    private function needleExists(array $needles, string $needle): bool
    {
        $normalizedNeedle = mb_strtolower($needle);

        return array_any(
            $needles,
            static fn (string $existingNeedle): bool => mb_strtolower($existingNeedle) === $normalizedNeedle,
        );
    }

    private function normalizeNeedles(array $needles): array
    {
        $normalizedNeedles = [];
        foreach ($needles as $needle) {
            if (!is_string($needle)) {
                continue;
            }

            $needle = trim($needle);
            if ($needle === '' || $this->needleExists($normalizedNeedles, $needle)) {
                continue;
            }

            $normalizedNeedles[] = $needle;
        }

        return $normalizedNeedles;
    }
}
