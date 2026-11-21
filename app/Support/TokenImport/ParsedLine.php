<?php

namespace App\Support\TokenImport;

/**
 * Ergebnis einer eingelesenen Zeile: gefundene Aufrufe und kaputte Stellen.
 */
final readonly class ParsedLine
{
    /**
     * @param  list<array<string, int|float|string|null>>  $rows  Speicherfertige Aufrufe (Spalten der Tabelle api_requests)
     * @param  list<string>  $errors  Gründe für kaputte Einträge bzw. die kaputte Zeile
     */
    public function __construct(
        public array $rows = [],
        public array $errors = [],
    ) {}

    public static function broken(string $reason): self
    {
        return new self([], [$reason]);
    }
}
