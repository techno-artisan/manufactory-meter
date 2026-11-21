<?php

namespace App\Console\Commands;

use App\Support\TokenImport\OtlpLineParser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tokens:import {path : Pfad zur OTLP-JSON-Datei des OpenTelemetry Collectors}')]
#[Description('Liest claude_code.api_request-Einträge aus einer OTLP-JSON-Datei und speichert jeden Aufruf genau einmal')]
class ImportTokens extends Command
{
    /** Aufrufe je Speicherpaket (eine Transaktion). */
    private const BATCH_SIZE = 500;

    public function handle(OtlpLineParser $parser): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error(is_dir($path)
                ? "Abbruch: {$path} ist ein Verzeichnis, keine Datei."
                : "Abbruch: Die Datei {$path} existiert nicht oder ist nicht lesbar.");

            return self::FAILURE;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            $this->error("Abbruch: Die Datei {$path} ist nicht lesbar.");

            return self::FAILURE;
        }

        $created = 0;
        $broken = 0;
        $parsedRows = 0;
        $batch = [];
        $lineNumber = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if (trim($line) === '') {
                    continue;
                }

                $parsed = $parser->parse($line);

                foreach ($parsed->errors as $reason) {
                    $broken++;
                    $this->warn("Zeile {$lineNumber}: {$reason}");
                }

                foreach ($parsed->rows as $row) {
                    $batch[] = $row;
                    $parsedRows++;
                }

                if (count($batch) >= self::BATCH_SIZE) {
                    $created += $this->store($batch);
                    $batch = [];
                }
            }

            $created += $this->store($batch);
        } finally {
            fclose($handle);
        }

        $existing = $parsedRows - $created;

        $this->info("Neu gespeichert: {$created}");
        $this->info("Schon vorhanden: {$existing}");
        $broken > 0 ? $this->error("Kaputt: {$broken}") : $this->info('Kaputt: 0');

        return $broken > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Speichert ein Paket; Aufrufe mit bekanntem Fingerabdruck ignoriert die Datenbank.
     *
     * @param  list<array<string, int|float|string|null>>  $rows
     * @return int Anzahl der neu gespeicherten Aufrufe
     */
    private function store(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now()->utc()->format('Y-m-d H:i:s');

        foreach ($rows as $i => $row) {
            $rows[$i]['created_at'] = $now;
        }

        return DB::transaction(fn (): int => DB::table('api_requests')->insertOrIgnore($rows));
    }
}
