<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Anzeigeformate für die Oberfläche: deutsches Zahlenformat und deutsche Zeit.
 *
 * Fehlende Werte werden als Gedankenstrich dargestellt, nie als 0.
 */
final class GermanFormat
{
    public const MISSING = '–';

    public const TIMEZONE = 'Europe/Berlin';

    public static function integer(int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return self::MISSING;
        }

        return number_format((int) $value, 0, ',', '.');
    }

    /**
     * Kosten mit fester Zahl an Nachkommastellen, z. B. `0,0123 $`.
     */
    public static function cost(int|float|string|null $value, int $decimals = 4): string
    {
        if ($value === null || $value === '') {
            return self::MISSING;
        }

        return number_format((float) $value, $decimals, ',', '.').' $';
    }

    /**
     * Kosten mit voller gespeicherter Genauigkeit, ohne Umweg über Gleitkommazahlen.
     */
    public static function exactCost(?string $value): string
    {
        if ($value === null || $value === '') {
            return self::MISSING;
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = number_format((int) $whole, 0, ',', '.');

        return ($negative ? '-' : '').$whole.($fraction !== '' ? ','.$fraction : '').' $';
    }

    public static function dateTime(?DateTimeInterface $value): string
    {
        if ($value === null) {
            return self::MISSING;
        }

        return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE)->format('d.m.Y H:i:s');
    }

    public static function text(?string $value): string
    {
        return $value === null || $value === '' ? self::MISSING : $value;
    }
}
