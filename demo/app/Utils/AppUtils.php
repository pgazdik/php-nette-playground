<?php
namespace App\Utils;


class AppUtils
{

    public static function toEnumValues(array $enums): array
    {
        return array_map(fn($enum) => $enum->value, $enums);
    }

}