<?php
namespace App\Utils;

use DateTime;
use DateTimeZone;

class DateUtils
{
    public static function utcToBa(DateTime $utcDate): DateTime
    {
        return (clone $utcDate)->setTimezone(new DateTimeZone('Europe/Bratislava'));
    }

    public static function baToUtc(DateTime $baDate): DateTime
    {
        return (clone $baDate)->setTimezone(new DateTimeZone('UTC'));
    }

    public static function newBaDate(string $baDatetime): DateTime
    {
        return new DateTime($baDatetime, new DateTimeZone('Europe/Bratislava'));
    }
}