<?php
namespace App\Utils;

use DateTime;
use DateTimeZone;

class DateUtils
{
    public static function utcToBa(?DateTime $utcDate): ?DateTime
    {
        return $utcDate ? (clone $utcDate)->setTimezone(new DateTimeZone('Europe/Bratislava')) : null;
    }

    public static function baToUtc(?DateTime $baDate): ?DateTime
    {
        return $baDate ? (clone $baDate)->setTimezone(new DateTimeZone('UTC')) : null;
    }

    // new DateTime() leaves the timezon as UTC. So for printing the date this is better.
    public static function nowBaDate(): DateTime
    {
        return self::newBaDate('now');
    }

    public static function newBaDate(string $baDatetime): DateTime
    {
        return new DateTime($baDatetime, new DateTimeZone('Europe/Bratislava'));
    }
}