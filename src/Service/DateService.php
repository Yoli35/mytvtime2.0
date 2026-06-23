<?php

namespace App\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class DateService
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function newDate(string $dateString, string $timeZone, bool $allDay = false): DateTime
    {
        try {
            $date = new DateTime($dateString, new DateTimeZone($timeZone));
        } catch (Exception) {
            $date = new DateTime("now");
        }
        if ($allDay) $date = $date->setTime(0, 0);

        return $date;
    }

    public function getNow(string $timezone, bool $allDay = false): DateTime
    {
        try {
            $date = new DateTime('now', new DateTimeZone($timezone));
        } catch (Exception) {
            $date = new DateTime("now");
        }
        if ($allDay) $date = $date->setTime(0, 0);

        return $date;
    }

    public function getNowImmutable(string $timezone, bool $allDay = false): DateTimeImmutable
    {
        try {
            $date = new DateTimeImmutable('now', new DateTimeZone($timezone));
        } catch (Exception) {
            $date = new DateTimeImmutable("now");
        }
        if ($allDay) $date = $date->setTime(0, 0);

        return $date;
    }

    public function newDateFromUTC(string $dateString, bool $allDay = false): DateTime
    {
        try {
            $date = new DateTime($dateString, new DateTimeZone('UTC'));
        } catch (Exception) {
            $date = new DateTime("now");
        }
        if ($allDay) $date = $date->setTime(0, 0);

        return $date;
    }

    public function newDateImmutable(string $dateString, ?string $timezone, bool $allDay = false): DateTimeImmutable
    {
        try {
            $date = new DateTimeImmutable($dateString, $timezone ? new DateTimeZone($timezone) : null);
        } catch (Exception) {
            $date = new DateTimeImmutable("now");
        }
        if ($allDay) $date = $date->setTime(0, 0);

        return $date;
    }

    public function newDateFromTimestamp(int $timestamp, string $timeZone, bool $allDay = false): DateTime
    {
        try {
            $date = new DateTime("now");
            $date->setTimestamp($timestamp);
            $date->setTimezone(new DateTimeZone($timeZone));
        } catch (Exception) {
            $date = new DateTime("now");
        }
        if ($allDay) $date = $date->setTime(0, 0);

        return $date;
    }

    public function newDateImmutableFromTimestamp(int $timestamp, string $timeZone, bool $allDay = false): DateTimeImmutable
    {
        try {
            $date = new DateTimeImmutable("now");
            $date = $date->setTimestamp($timestamp);
            $date = $date->setTimezone(new DateTimeZone($timeZone));
        } catch (Exception) {
            $date = new DateTimeImmutable("now");
        }
        if ($allDay) $date = $date->setTime(0, 0);

        return $date;
    }

    public function formatDate(string $dateString, string $timeZone, string $locale): string
    {
        $format = datefmt_create($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $dateString);
    }

    public function formatDateLong(string $dateString, string $timeZone, string $locale): string
    {
        $timestamp = strtotime($dateString);
        $format = datefmt_create($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $timestamp);
    }

    public function formatDateRelativeLong(string $dateString, ?string $timeZone, string $locale): string
    {
        $at = ['en' => ', at ', 'fr' => ', à ', 'ko' => ' '];
        $timezone = $timeZone ?? date_default_timezone_get();
        $date = $this->newDateImmutable($dateString, $timeZone);
        $now = $this->getNowImmutable($timezone);
        $daysDiff = (int)$now->setTime(0, 0)->diff($date->setTime(0, 0))->format('%r%a');

        if ($daysDiff < -10 || $daysDiff > 10) {
            return $this->formatShortDate($date, $timezone, $locale);
        }

        if ($locale === 'en') $range = 1; else $range = 2;
        if (abs($daysDiff) <= $range) {
            return $this->formatIntlRelativeLong($date, $timeZone, $locale, strlen($dateString) == 10 ? IntlDateFormatter::NONE : IntlDateFormatter::SHORT);
        }

        $count = abs($daysDiff);
        $time = strlen($dateString) == 10 ? null : substr($dateString, 11, 5);

        if ($daysDiff < 0) {
            return ucfirst($this->translator->trans(
                $count === 1 ? '%count% day ago' : '%count% days ago',
                ['%count%' => $count],
                null,
                $locale,
            )) . ($time ? $at[$locale] . $time : '');
        }

        return ucfirst($this->translator->trans(
            $count === 1 ? 'in %count% day' : 'in %count% days',
            ['%count%' => $count],
            null,
            $locale,
        )) . ($time ? $at[$locale] . $time : '');
    }

    public function formatDateRelativeMedium(string $dateString, string $timeZone, string $locale): string
    {
        $timestamp = strtotime($dateString);
        $format = datefmt_create($locale, IntlDateFormatter::RELATIVE_MEDIUM, IntlDateFormatter::NONE, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $timestamp);
    }

    public function formatDateRelativeShort(string $dateString, string $timeZone, string $locale): string
    {
        $timestamp = strtotime($dateString);
        $format = datefmt_create($locale, IntlDateFormatter::RELATIVE_SHORT, IntlDateFormatter::NONE, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $timestamp);
    }

    private function formatIntlRelativeLong(DateTimeImmutable $date, ?string $timeZone, string $locale, int $timeType): string
    {
        $timestamp = $date->getTimestamp();
        $format = datefmt_create($locale, IntlDateFormatter::RELATIVE_LONG, $timeType, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $timestamp);
    }

    private function formatShortDate(DateTimeImmutable $date, string $timeZone, string $locale): string
    {
        $format = datefmt_create($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $date->getTimestamp());
    }

    public function interval($interval): DateInterval|false
    {
        try {
            return new DateInterval($interval);
        } catch (Exception) {
        }
        return false;
    }

    public function getDurationString(int $duration, array $units): string
    {
        if ($duration < 60) {
            return sprintf('%d %s', $duration, $units[$duration < 2 ? 'second' : 'seconds']);
        } elseif ($duration < 3600) {
            $minutes = floor($duration / 60);
            $seconds = $duration % 60;
            return sprintf('%d %s %d %s', $minutes, $units[$minutes < 2 ? 'minute' : 'minutes'], $seconds, $units[$seconds < 2 ? 'second' : 'seconds']);
        } else {
            $hours = floor($duration / 3600);
            $minutes = floor(($duration % 3600) / 60);
            $seconds = $duration % 60;
            return sprintf('%d %s %d %s %d %s', $hours, $units[$hours < 2 ? 'hour' : 'hours'], $minutes, $units[$minutes < 2 ? 'minute' : 'minutes'], $seconds, $units[$seconds < 2 ? 'second' : 'seconds']);
        }
    }
}
