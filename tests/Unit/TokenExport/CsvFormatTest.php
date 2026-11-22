<?php

use App\Support\TokenExport\CsvFormat;

it('schreibt Kosten mit Dezimalkomma, voller Genauigkeit und ohne Nullen am Ende (AK-6, EC-7)', function (int|float|string|null $stored, string $expected): void {
    expect(CsvFormat::cost($stored))->toBe($expected);
})->with([
    [0.01234567, '0,01234567'],
    [1.5, '1,5'],
    [0, '0'],
    [0.0, '0'],
    [1.0E-10, '0,0000000001'],
    [0.0123456789, '0,0123456789'],
    [12345678901.5, '12345678901,5'],
    [1.0E+15, '1000000000000000'],
    [-0.5, '-0,5'],
    ['0.0123000000', '0,0123'],
    ['2', '2'],
    [null, ''],
]);

it('schreibt ganze Zahlen ohne Tausenderpunkt und fehlende als leere Zelle (AK-5, AK-7)', function (): void {
    expect(CsvFormat::integer(1234567))->toBe('1234567')
        ->and(CsvFormat::integer(10_000_000_000))->toBe('10000000000')
        ->and(CsvFormat::integer(-5))->toBe('-5')
        ->and(CsvFormat::integer(null))->toBe('');
});

it('setzt vor Textwerte mit Formelzeichen ein Hochkomma (AK-9)', function (string $value, string $expected): void {
    expect(CsvFormat::text($value))->toBe($expected);
})->with([
    ['=SUMME(1;2)', "'=SUMME(1;2)"],
    ['+abc', "'+abc"],
    ['-abc', "'-abc"],
    ['@abc', "'@abc"],
    ['abc=1', 'abc=1'],
    ['modell-ä', 'modell-ä'],
]);

it('setzt Zellen nur bei Semikolon, Anführungszeichen oder Zeilenumbruch in Anführungszeichen (AK-8)', function (): void {
    expect(CsvFormat::quote('modell;mit "Zeichen"'))->toBe('"modell;mit ""Zeichen"""')
        ->and(CsvFormat::quote("a\nb"))->toBe("\"a\nb\"")
        ->and(CsvFormat::quote("a\rb"))->toBe("\"a\rb\"")
        ->and(CsvFormat::quote('mit Leerzeichen, und Komma'))->toBe('mit Leerzeichen, und Komma')
        ->and(CsvFormat::line(['a', 'b;c', '']))->toBe("a;\"b;c\";\r\n");
});

it('rechnet Zeitpunkte in deutsche Zeit um, auch bei Zeitumstellung (AK-4, EC-1)', function (): void {
    expect(CsvFormat::dateTime('2026-07-01 12:00:00'))->toBe('01.07.2026 14:00:00')
        ->and(CsvFormat::dateTime('2026-01-15 12:00:00'))->toBe('15.01.2026 13:00:00')
        ->and(CsvFormat::dateTime('2026-11-21 10:15:30.250'))->toBe('21.11.2026 11:15:30')
        ->and(CsvFormat::dateTime('2026-10-25 00:30:00'))->toBe('25.10.2026 02:30:00')
        ->and(CsvFormat::dateTime('2026-10-25 01:30:00'))->toBe('25.10.2026 02:30:00');
});
