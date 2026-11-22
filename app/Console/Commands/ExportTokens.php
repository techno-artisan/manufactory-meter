<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\TokenExport\CsvFormat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

#[Signature('tokens:export
    {path : Zieldatei (relativ zum aktuellen Verzeichnis oder absolut)}
    {--from= : Erster Tag in deutscher Zeit, inklusive (JJJJ-MM-TT)}
    {--to= : Letzter Tag in deutscher Zeit, inklusive (JJJJ-MM-TT)}
    {--model=* : Modellname, exakt; mehrfach angebbar}
    {--session= : Session-ID, exakt}
    {--force : Vorhandene Datei überschreiben}')]
#[Description('Exportiert die gespeicherten API-Aufrufe als CSV-Datei für deutsches Excel')]
class ExportTokens extends Command
{
    /** Format, in dem der Import die Zeitpunkte (UTC) speichert. */
    private const string STORED_FORMAT = 'Y-m-d H:i:s';

    /** Kopfzeile der Exportdatei, in dieser Reihenfolge. */
    private const array HEADER = [
        'ID', 'Zeitpunkt', 'Modell', 'Input-Token', 'Output-Token', 'Cache-Lese-Token',
        'Cache-Schreib-Token', 'Kosten (USD)', 'Dauer (ms)', 'Session', 'Prompt', 'Claude-Code-Version',
    ];

    public function handle(): int
    {
        try {
            $from = $this->dayOption('from');
            $to = $this->dayOption('to');

            if ($from !== null && $to !== null && $to->lessThan($from)) {
                throw new RuntimeException('--to ('.$to->format('Y-m-d').') liegt vor --from ('.$from->format('Y-m-d').').');
            }

            $models = $this->modelOption();
            $session = $this->sessionOption();
            $path = $this->targetPath();
        } catch (RuntimeException $e) {
            $this->error('Abbruch: '.$e->getMessage());

            return self::FAILURE;
        }

        $query = $this->query($from, $to, $models, $session);

        try {
            $count = $this->write($path, $query);
        } catch (Throwable $e) {
            $this->error("Abbruch: Die Datei {$path} konnte nicht geschrieben werden: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Exportiert: {$count} Aufrufe");

        return self::SUCCESS;
    }

    /**
     * Ein Tag im Format JJJJ-MM-TT als Beginn dieses Tages in deutscher Zeit.
     */
    private function dayOption(string $name): ?CarbonImmutable
    {
        if (! $this->input->hasParameterOption('--'.$name)) {
            return null;
        }

        $value = $this->option($name);
        $value = is_string($value) ? $value : '';

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new RuntimeException("--{$name}=\"{$value}\" ist kein gültiges Datum. Erwartet wird das Format JJJJ-MM-TT, z. B. 2026-11-21.");
        }

        return CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3], 0, 0, 0, CsvFormat::TIMEZONE);
    }

    /**
     * @return list<string>
     */
    private function modelOption(): array
    {
        $models = [];

        foreach ((array) $this->option('model') as $model) {
            if (! is_string($model) || trim($model) === '') {
                throw new RuntimeException('--model braucht einen Modellnamen, z. B. --model=claude-sonnet-5-5.');
            }

            $models[$model] = $model;
        }

        return array_values($models);
    }

    private function sessionOption(): ?string
    {
        if (! $this->input->hasParameterOption('--session')) {
            return null;
        }

        $session = $this->option('session');

        if (! is_string($session) || trim($session) === '') {
            throw new RuntimeException('--session braucht eine Session-ID, z. B. --session=<session-id>.');
        }

        return $session;
    }

    /**
     * Prüft die Zieldatei, bevor irgendetwas geschrieben wird.
     */
    private function targetPath(): string
    {
        $path = (string) $this->argument('path');

        if (trim($path) === '') {
            throw new RuntimeException('Bitte einen Pfad für die Exportdatei angeben.');
        }

        if (! $this->isAbsolute($path)) {
            $path = getcwd().DIRECTORY_SEPARATOR.$path;
        }

        if (is_dir($path) || str_ends_with($path, '/') || str_ends_with($path, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("{$path} ist ein Ordner, keine Datei.");
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new RuntimeException("Der Ordner {$directory} existiert nicht.");
        }

        if (! is_writable($directory)) {
            throw new RuntimeException("Der Ordner {$directory} ist nicht beschreibbar.");
        }

        if (file_exists($path) && ! $this->option('force')) {
            throw new RuntimeException("Die Datei {$path} existiert bereits. Mit --force wird sie überschrieben.");
        }

        return $path;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * Passende Aufrufe, ältester zuerst, bei gleichem Zeitpunkt kleinere ID zuerst. Nur lesend.
     *
     * @param  list<string>  $models
     */
    private function query(?CarbonImmutable $from, ?CarbonImmutable $to, array $models, ?string $session): Builder
    {
        return DB::table('api_requests')
            ->select([
                'id', 'occurred_at', 'model', 'input_tokens', 'output_tokens', 'cache_read_tokens',
                'cache_creation_tokens', 'cost_usd', 'duration_ms', 'session_id', 'prompt_id', 'app_version',
            ])
            ->when($from, fn (Builder $query, CarbonImmutable $from): Builder => $query
                ->where('occurred_at', '>=', $from->utc()->format(self::STORED_FORMAT)))
            ->when($to, fn (Builder $query, CarbonImmutable $to): Builder => $query
                ->where('occurred_at', '<', $to->addDay()->utc()->format(self::STORED_FORMAT)))
            ->when($models !== [], fn (Builder $query): Builder => $query->whereIn('model', $models))
            ->when($session !== null, fn (Builder $query): Builder => $query->where('session_id', $session))
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * Schreibt in eine temporäre Datei im Zielordner und benennt sie erst am Ende um,
     * damit am Pfad nie eine halbe Datei liegt.
     *
     * @return int Anzahl der exportierten Aufrufe
     */
    private function write(string $path, Builder $query): int
    {
        $temp = tempnam(dirname($path), '.tokens-export-');

        if ($temp === false) {
            throw new RuntimeException('Temporäre Datei konnte nicht angelegt werden.');
        }

        $handle = null;
        $count = 0;

        try {
            $handle = fopen($temp, 'wb');

            if ($handle === false) {
                throw new RuntimeException('Temporäre Datei konnte nicht geöffnet werden.');
            }

            $this->put($handle, CsvFormat::BOM.CsvFormat::line(self::HEADER));

            foreach ($query->cursor() as $row) {
                /** @var object{id: int, occurred_at: string, model: string, input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_creation_tokens: int, cost_usd: int|float|string|null, duration_ms: int|null, session_id: string|null, prompt_id: string|null, app_version: string|null} $row */
                $this->put($handle, CsvFormat::line([
                    CsvFormat::integer($row->id),
                    CsvFormat::dateTime($row->occurred_at),
                    CsvFormat::text($row->model),
                    CsvFormat::integer($row->input_tokens),
                    CsvFormat::integer($row->output_tokens),
                    CsvFormat::integer($row->cache_read_tokens),
                    CsvFormat::integer($row->cache_creation_tokens),
                    CsvFormat::cost($row->cost_usd),
                    CsvFormat::integer($row->duration_ms),
                    CsvFormat::text($row->session_id),
                    CsvFormat::text($row->prompt_id),
                    CsvFormat::text($row->app_version),
                ]));
                $count++;
            }

            if (! fflush($handle) || ! fclose($handle)) {
                $handle = null;

                throw new RuntimeException('Datei konnte nicht abgeschlossen werden.');
            }

            $handle = null;
            @chmod($temp, 0666 & ~umask());

            if (! @rename($temp, $path)) {
                throw new RuntimeException('Datei konnte nicht an den Zielpfad verschoben werden.');
            }
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            @unlink($temp);

            throw $e;
        }

        return $count;
    }

    /**
     * @param  resource  $handle
     */
    private function put($handle, string $data): void
    {
        if (fwrite($handle, $data) !== strlen($data)) {
            throw new RuntimeException('Schreiben fehlgeschlagen (z. B. Platte voll).');
        }
    }
}
