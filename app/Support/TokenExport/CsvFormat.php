<?php

declare(strict_types=1);

namespace App\Support\TokenExport;

use DateTimeImmutable;
use DateTimeZone;

/**
 * CSV-Format für deutsches Excel (PROJ-5): Semikolon, CRLF, Dezimalkomma, deutsche Zeit.
 *
 * Fehlende Werte sind leere Zellen, nie `–` und nie `0`.
 */
final class CsvFormat
{
    public const string BOM = "\xEF\xBB\xBF";

    public const string DELIMITER = ';';

    public const string LINE_END = "\r\n";

    public const string TIMEZONE = 'Europe/Berlin';

    private static ?DateTimeZone $utc = null;

    private static ?DateTimeZone $local = null;

    /**
     * Eine Zeile mit Zeilenende; Zellen werden nach RFC 4180 in Anführungszeichen gesetzt, wenn nötig.
     *
     * @param  list<string>  $cells
     */
    public static function line(array $cells): string
    {
        return implode(self::DELIMITER, array_map(self::quote(...), $cells)).self::LINE_END;
    }

    public static function quote(string $cell): string
    {
        if (strpbrk($cell, self::DELIMITER."\"\r\n") === false) {
            return $cell;
        }

        return '"'.str_replace('"', '""', $cell).'"';
    }

    /**
     * Textwert; beginnt er mit `=`, `+`, `-` oder `@`, wird `'` vorangestellt (Schutz gegen Formeln).
     */
    public static function text(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return str_contains('=+-@', $value[0]) ? "'".$value : $value;
    }

    /**
     * Ganze Zahl ohne Tausenderpunkt.
     */
    public static function integer(int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    /**
     * Gespeicherter UTC-Zeitpunkt in deutscher Zeit, `TT.MM.JJJJ HH:MM:SS`.
     */
    public static function dateTime(string $storedUtc): string
    {
        self::$utc ??= new DateTimeZone('UTC');
        self::$local ??= new DateTimeZone(self::TIMEZONE);

        return (new DateTimeImmutable($storedUtc, self::$utc))->setTimezone(self::$local)->format('d.m.Y H:i:s');
    }

    /**
     * Kosten mit Dezimalkomma, ohne `$`, mit allen gespeicherten Nachkommastellen, ohne Nullen am Ende
     * und ohne wissenschaftliche Schreibweise.
     */
    public static function cost(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        // var_export liefert die kürzeste Darstellung, die genau den gespeicherten Wert ergibt.
        $repr = is_float($value) ? var_export($value, true) : trim($value);

        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $repr, $m) !== 1 || $m[2].($m[3] ?? '') === '') {
            return str_replace('.', ',', $repr);
        }

        $digits = $m[2].($m[3] ?? '');
        $point = strlen($m[2]) + (int) ($m[4] ?? 0);

        if ($point <= 0) {
            $digits = str_repeat('0', 1 - $point).$digits;
            $point = 1;
        } elseif ($point > strlen($digits)) {
            $digits = str_pad($digits, $point, '0');
        }

        $whole = ltrim(substr($digits, 0, $point), '0');
        $fraction = rtrim(substr($digits, $point), '0');
        $whole = $whole === '' ? '0' : $whole;

        if ($whole === '0' && $fraction === '') {
            return '0';
        }

        return ($m[1] === '-' ? '-' : '').$whole.($fraction !== '' ? ','.$fraction : '');
    }
}
