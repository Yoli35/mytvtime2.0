<?php

namespace App\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use IntlDateFormatter;

class DateService
{
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

    public function newDateImmutable(string $dateString, string $timeZone, bool $allDay = false): DateTimeImmutable
    {
        try {
            $date = new DateTimeImmutable($dateString, new DateTimeZone($timeZone));
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
            $date->setTimestamp($timestamp);
            $date->setTimezone(new DateTimeZone($timeZone));
        } catch (Exception) {
            $date = new DateTimeImmutable("now");
        }
        if ($allDay) $date = $date->setTime(0, 0);

        return $date;
    }

    public function formatDate(string $dateSting, string $timeZone, string $locale): string
    {
        $format = datefmt_create($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $dateSting);
    }

    public function formatDateLong(string $dateSting, string $timeZone, string $locale): string
    {
        $timestamp = strtotime($dateSting);
        $format = datefmt_create($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $timestamp);
    }

    public function formatDateRelativeLong(string $dateSting, string $timeZone, string $locale): string
    {
        $timestamp = strtotime($dateSting);
        $format = datefmt_create($locale, IntlDateFormatter::RELATIVE_LONG, IntlDateFormatter::SHORT, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $timestamp);
    }

    public function formatDateRelativeMedium(string $dateSting, string $timeZone, string $locale): string
    {
        $timestamp = strtotime($dateSting);
        $format = datefmt_create($locale, IntlDateFormatter::RELATIVE_MEDIUM, IntlDateFormatter::NONE, $timeZone, IntlDateFormatter::GREGORIAN);
        return datefmt_format($format, $timestamp);
    }

    public function interval($interval): DateInterval|false
    {
        try {
            return new DateInterval($interval);
        } catch (Exception $e) {
        }
        return false;
    }
}