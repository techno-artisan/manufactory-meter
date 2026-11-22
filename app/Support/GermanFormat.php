<?php

declare(strict_types=1);

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
    public const string MISSING = '–';

    public const string TIMEZONE = 'Europe/Berlin';

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

    /**
     * Kostensumme aus ganzzahligen Zehnmilliardstel-Dollar, auf 4 Stellen gerundet, ohne Gleitkomma.
     *
     * Fehlen bei allen Aufrufen die Kosten, steht `–`; fehlen sie bei einem Teil, folgt `(n ohne Kosten)`.
     * Ohne Aufrufe sind die Kosten wirklich 0 (`0,0000 $`).
     */
    public static function costSum(int $costUnits, int $calls, int $callsWithoutCost): string
    {
        if ($calls > 0 && $callsWithoutCost >= $calls) {
            return self::MISSING;
        }

        $negative = $costUnits < 0;
        $tenThousandths = intdiv(abs($costUnits) + 500_000, 1_000_000);
        $formatted = ($negative && $tenThousandths > 0 ? '-' : '')
            .number_format(intdiv($tenThousandths, 10_000), 0, ',', '.')
            .','.str_pad((string) ($tenThousandths % 10_000), 4, '0', STR_PAD_LEFT).' $';

        return $callsWithoutCost > 0 ? $formatted.' ('.$callsWithoutCost.' ohne Kosten)' : $formatted;
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
