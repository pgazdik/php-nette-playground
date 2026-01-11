<?php
namespace App\Utils;

use DateTime;
use DateTimeZone;
use Exception;
use Tracy\Debugger;

class DebuggerUtils
{

    public static function logException(Exception $e, ?string $message = null): void
    {
        self::logCustom("exception", $e, $message);
    }

    public static function logCustom(string $customLogFile, Exception $e, ?string $message = null): void
    {
        $level = "exceptions/{$customLogFile}";
        if ($message !== null) {
            Debugger::log("{$message}: {$e->getMessage()}", $level);
        }
        Debugger::log($e, $level);
    }

}