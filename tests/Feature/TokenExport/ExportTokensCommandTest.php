<?php

use App\Models\ApiRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const EXPORT_HEADER = 'ID;Zeitpunkt;Modell;Input-Token;Output-Token;Cache-Lese-Token;Cache-Schreib-Token;Kosten (USD);Dauer (ms);Session;Prompt;Claude-Code-Version';

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/tokens-export-test-'.Str::random(12);
    mkdir($this->dir);
    $this->path = $this->dir.'/export.csv';
});

afterEach(function (): void {
    if (is_dir($this->dir)) {
        @chmod($this->dir, 0777);
        foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($this->dir.'/sub');
        rmdir($this->dir);
    }
});

/**
 * Datenzeilen der Exportdatei (ohne BOM, ohne Kopfzeile), je Zeile die Zellen.
 *
 * @return list<list<string>>
 */
function exportRows(string $path): array
{
    $content = substr((string) file_get_contents($path), 3);
    $lines = explode("\r\n", rtrim($content, "\r\n"));
    array_shift($lines);

    return array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), $lines);
}

/**
 * @return list<int>
 */
function exportedIds(string $path): array
{
    return array_map(fn (array $row): int => (int) $row[0], exportRows($path));
}

function call(array $attributes = []): ApiRequest
{
    return ApiRequest::factory()->create($attributes);
}

it('exportiert ohne Filter alle Aufrufe mit Kopfzeile und Meldung (AK-1)', function (): void {
    ApiRequest::factory()->count(3)->create();

    $this->artisan('tokens:export', ['path' => $this->path])
        ->expectsOutput('Exportiert: 3 Aufrufe')
        ->assertExitCode(0);

    $lines = explode("\r\n", substr((string) file_get_contents($this->path), 3));
    expect($lines[0])->toBe(EXPORT_HEADER)
        ->and(exportRows($this->path))->toHaveCount(3);
});

it('schreibt UTF-8 mit BOM, Semikolon und CRLF (AK-2)', function (): void {
    call(['model' => 'modell-ä']);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);
    $bytes = (string) file_get_contents($this->path);

    expect(substr($bytes, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and(substr_count($bytes, "\r\n"))->toBe(2)
        ->and(preg_match("/(?<!\r)\n/", $bytes))->toBe(0)
        ->and(str_ends_with($bytes, "\r\n"))->toBeTrue()
        ->and(substr_count(explode("\r\n", $bytes)[1], ';'))->toBe(11)
        ->and(mb_check_encoding($bytes, 'UTF-8'))->toBeTrue()
        ->and($bytes)->toContain('modell-ä');
});

it('schreibt alle 12 Werte in der festen Reihenfolge (AK-3, AK-5, AK-10)', function (): void {
    $call = call([
        'occurred_at' => '2026-11-21 10:15:30',
        'model' => 'modell-ä',
        'input_tokens' => 12345,
        'output_tokens' => 678,
        'cache_read_tokens' => 1234567,
        'cache_creation_tokens' => 9012,
        'cost_usd' => '0.01234567',
        'duration_ms' => 4321,
        'session_id' => 'sess-1',
        'prompt_id' => 'prompt-1',
        'app_version' => '2.1.0',
    ]);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);

    expect(exportRows($this->path)[0])->toBe([
        (string) $call->id, '21.11.2026 11:15:30', 'modell-ä', '12345', '678', '1234567', '9012',
        '0,01234567', '4321', 'sess-1', 'prompt-1', '2.1.0',
    ]);
});

it('schreibt Zeitpunkte in deutscher Zeit (AK-4)', function (): void {
    call(['occurred_at' => '2026-07-01 12:00:00']);
    call(['occurred_at' => '2026-01-15 12:00:00']);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);

    expect(array_column(exportRows($this->path), 1))->toBe(['15.01.2026 13:00:00', '01.07.2026 14:00:00']);
});

it('schreibt Kosten mit Dezimalkomma ohne $ (AK-6, EC-7)', function (): void {
    call(['occurred_at' => '2026-11-01 10:00:00', 'cost_usd' => '0.01234567']);
    call(['occurred_at' => '2026-11-02 10:00:00', 'cost_usd' => '1.5']);
    call(['occurred_at' => '2026-11-03 10:00:00', 'cost_usd' => '0']);
    call(['occurred_at' => '2026-11-04 10:00:00', 'cost_usd' => '0.0000000001', 'input_tokens' => 10_000_000_000]);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);
    $rows = exportRows($this->path);

    expect(array_column($rows, 7))->toBe(['0,01234567', '1,5', '0', '0,0000000001'])
        ->and($rows[3][3])->toBe('10000000000')
        ->and((string) file_get_contents($this->path))->not->toContain('$');
});

it('lässt fehlende Werte leer (AK-7, AK-19)', function (): void {
    call(['cost_usd' => null, 'duration_ms' => null, 'session_id' => null, 'prompt_id' => null, 'app_version' => null]);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);
    $row = exportRows($this->path)[0];

    expect([$row[7], $row[8], $row[9], $row[10], $row[11]])->toBe(['', '', '', '', '']);
});

it('setzt Werte mit Semikolon und Anführungszeichen nach RFC 4180 in Anführungszeichen (AK-8)', function (): void {
    call(['model' => 'modell;mit "Zeichen"']);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);

    expect((string) file_get_contents($this->path))->toContain(';"modell;mit ""Zeichen""";')
        ->and(exportRows($this->path)[0])->toHaveCount(12)
        ->and(exportRows($this->path)[0][2])->toBe('modell;mit "Zeichen"');
});

it('schützt Textwerte gegen Excel-Formeln (AK-9, EC-6)', function (): void {
    call(['model' => '=SUMME(1;2)', 'session_id' => '+abc', 'prompt_id' => '-abc', 'app_version' => '@abc']);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);
    $content = (string) file_get_contents($this->path);

    expect($content)->toContain(";\"'=SUMME(1;2)\";")
        ->toContain(";'+abc;'-abc;'@abc\r\n")
        ->and(exportRows($this->path)[0][0])->not->toStartWith("'");
});

it('schreibt den Original-Eintrag nicht in die Datei (AK-11)', function (): void {
    call(['raw' => ['attributes' => ['user.email' => 'nutzer@example.test']]]);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);

    expect((string) file_get_contents($this->path))->not->toContain('nutzer@example.test');
});

it('sortiert ältesten Aufruf zuerst, bei Gleichstand kleinere ID zuerst (AK-12)', function (): void {
    $newest = call(['occurred_at' => '2026-11-22 10:00:00']);
    $sameA = call(['occurred_at' => '2026-11-21 10:00:00']);
    $oldest = call(['occurred_at' => '2026-11-01 10:00:00']);
    $sameB = call(['occurred_at' => '2026-11-21 10:00:00']);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);

    expect(exportedIds($this->path))->toBe([$oldest->id, $sameA->id, $sameB->id, $newest->id]);
});

describe('Zeitraum', function (): void {
    beforeEach(function (): void {
        // deutsche Zeit (Winterzeit, UTC+1)
        $this->day20 = call(['occurred_at' => '2026-11-20 11:00:00']);
        $this->day21start = call(['occurred_at' => '2026-11-20 23:00:00']); // 21.11. 00:00:00
        $this->day21end = call(['occurred_at' => '2026-11-21 22:59:59']);   // 21.11. 23:59:59
        $this->day22 = call(['occurred_at' => '2026-11-21 23:00:00']);      // 22.11. 00:00:00
    });

    it('grenzt mit --from und --to auf ganze deutsche Tage ein (AK-13)', function (): void {
        $this->artisan('tokens:export', ['path' => $this->path, '--from' => '2026-11-21', '--to' => '2026-11-21'])->assertExitCode(0);

        expect(exportedIds($this->path))->toBe([$this->day21start->id, $this->day21end->id]);
    });

    it('grenzt nur mit --from ein (AK-14)', function (): void {
        $this->artisan('tokens:export', ['path' => $this->path, '--from' => '2026-11-21'])->assertExitCode(0);

        expect(exportedIds($this->path))->toBe([$this->day21start->id, $this->day21end->id, $this->day22->id]);
    });

    it('grenzt nur mit --to ein (AK-15)', function (): void {
        $this->artisan('tokens:export', ['path' => $this->path, '--to' => '2026-11-21'])->assertExitCode(0);

        expect(exportedIds($this->path))->toBe([$this->day20->id, $this->day21start->id, $this->day21end->id]);
    });
});

it('ordnet Aufrufe nach deutschem Kalendertag zu (AK-16)', function (): void {
    $call = call(['occurred_at' => '2026-11-21 23:30:00']);

    $this->artisan('tokens:export', ['path' => $this->path, '--from' => '2026-11-22', '--to' => '2026-11-22'])->assertExitCode(0);

    expect(exportedIds($this->path))->toBe([$call->id])
        ->and(exportRows($this->path)[0][1])->toBe('22.11.2026 00:30:00');
});

it('umfasst am Tag der Zeitumstellung alle 25 Stunden (EC-1)', function (): void {
    $before = call(['occurred_at' => '2026-10-24 21:59:59']);
    $first = call(['occurred_at' => '2026-10-24 22:00:00']);  // 25.10. 00:00 MESZ
    $twiceA = call(['occurred_at' => '2026-10-25 00:30:00']); // 02:30 MESZ
    $twiceB = call(['occurred_at' => '2026-10-25 01:30:00']); // 02:30 MEZ
    $last = call(['occurred_at' => '2026-10-25 22:59:59']);   // 23:59:59 MEZ
    call(['occurred_at' => '2026-10-25 23:00:00']);

    $this->artisan('tokens:export', ['path' => $this->path, '--from' => '2026-10-25', '--to' => '2026-10-25'])->assertExitCode(0);

    expect(exportedIds($this->path))->toBe([$first->id, $twiceA->id, $twiceB->id, $last->id])
        ->and(exportRows($this->path)[1][1])->toBe('25.10.2026 02:30:00')
        ->and(exportRows($this->path)[2][1])->toBe('25.10.2026 02:30:00')
        ->and($before->id)->toBeInt();
});

it('filtert nach mehreren Modellen exakt (AK-17, EC-4, EC-5)', function (): void {
    $a = call(['model' => 'A', 'occurred_at' => '2026-11-01 10:00:00']);
    $b = call(['model' => 'B', 'occurred_at' => '2026-11-02 10:00:00']);
    call(['model' => 'C']);
    call(['model' => 'a']);

    $this->artisan('tokens:export', ['path' => $this->path, '--model' => ['A', 'B', 'A']])->assertExitCode(0);

    expect(exportedIds($this->path))->toBe([$a->id, $b->id]);
});

it('filtert nach einer Session (AK-18)', function (): void {
    $s1 = call(['session_id' => 'S1']);
    call(['session_id' => 'S2']);
    call(['session_id' => null]);

    $this->artisan('tokens:export', ['path' => $this->path, '--session' => 'S1'])->assertExitCode(0);

    expect(exportedIds($this->path))->toBe([$s1->id]);
});

it('exportiert ohne --session auch Aufrufe ohne Session (AK-19)', function (): void {
    $without = call(['session_id' => null]);

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);

    expect(exportedIds($this->path))->toBe([$without->id])
        ->and(exportRows($this->path)[0][9])->toBe('');
});

it('verknüpft Zeitraum, Modell und Session mit UND (AK-20)', function (): void {
    $match = call(['occurred_at' => '2026-11-21 10:00:00', 'model' => 'A', 'session_id' => 'S1']);
    call(['occurred_at' => '2026-11-22 10:00:00', 'model' => 'A', 'session_id' => 'S1']);
    call(['occurred_at' => '2026-11-21 10:00:00', 'model' => 'B', 'session_id' => 'S1']);
    call(['occurred_at' => '2026-11-21 10:00:00', 'model' => 'A', 'session_id' => 'S2']);

    $this->artisan('tokens:export', [
        'path' => $this->path, '--from' => '2026-11-21', '--to' => '2026-11-21', '--model' => ['A'], '--session' => 'S1',
    ])->assertExitCode(0);

    expect(exportedIds($this->path))->toBe([$match->id]);
});

it('schreibt ohne Treffer nur die Kopfzeile (AK-21)', function (array $options): void {
    call(['model' => 'A', 'session_id' => 'S1', 'occurred_at' => '2026-11-21 10:00:00']);

    $this->artisan('tokens:export', ['path' => $this->path, ...$options])
        ->expectsOutput('Exportiert: 0 Aufrufe')
        ->assertExitCode(0);

    expect((string) file_get_contents($this->path))->toBe("\xEF\xBB\xBF".EXPORT_HEADER."\r\n");
})->with([
    'unbekanntes Modell' => [['--model' => ['unbekannt']]],
    'unbekannte Session' => [['--session' => 'unbekannt']],
    'anderer Zeitraum' => [['--from' => '2025-01-01', '--to' => '2025-01-31']],
]);

it('schreibt ohne gespeicherte Aufrufe nur die Kopfzeile (AK-22)', function (): void {
    $this->artisan('tokens:export', ['path' => $this->path])
        ->expectsOutput('Exportiert: 0 Aufrufe')
        ->assertExitCode(0);

    expect((string) file_get_contents($this->path))->toBe("\xEF\xBB\xBF".EXPORT_HEADER."\r\n");
});

it('überschreibt eine vorhandene Datei nicht ohne --force (AK-23)', function (): void {
    call();
    file_put_contents($this->path, 'alt');

    $exitCode = Artisan::call('tokens:export', ['path' => $this->path]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain($this->path)->toContain('--force')
        ->and(file_get_contents($this->path))->toBe('alt');
});

it('überschreibt eine vorhandene Datei mit --force (AK-24)', function (): void {
    call();
    file_put_contents($this->path, 'alt');

    $this->artisan('tokens:export', ['path' => $this->path, '--force' => true])->assertExitCode(0);

    expect(exportRows($this->path))->toHaveCount(1)
        ->and(glob($this->dir.'/.tokens-export-*'))->toBe([]);
});

it('bricht bei fehlendem Ordner oder Ordner als Pfad ab, auch mit --force (AK-25)', function (string $relative): void {
    mkdir($this->dir.'/sub');
    $path = $this->dir.'/'.$relative;

    $this->artisan('tokens:export', ['path' => $path, '--force' => true])->assertExitCode(1);

    expect(is_dir($this->dir.'/fehlt'))->toBeFalse()
        ->and(array_values(array_diff(scandir($this->dir) ?: [], ['.', '..'])))->toBe(['sub'])
        ->and(scandir($this->dir.'/sub'))->toBe(['.', '..']);
})->with([
    'fehlender Ordner' => ['fehlt/export.csv'],
    'Pfad ist ein Ordner' => ['sub'],
]);

it('bricht bei nicht beschreibbarem Ordner ab (AK-25)', function (): void {
    chmod($this->dir, 0555);

    $this->artisan('tokens:export', ['path' => $this->path, '--force' => true])
        ->expectsOutputToContain('nicht beschreibbar')
        ->assertExitCode(1);

    chmod($this->dir, 0777);
    expect(file_exists($this->path))->toBeFalse();
})->skip(fn (): bool => function_exists('posix_geteuid') && posix_geteuid() === 0, 'als root ist jeder Ordner beschreibbar');

it('legt die Datei bei relativem Pfad im aktuellen Verzeichnis an (AK-26, EC-8)', function (): void {
    $cwd = getcwd();
    chdir($this->dir);

    try {
        $this->artisan('tokens:export', ['path' => 'Export März 2026.csv'])->assertExitCode(0);
    } finally {
        chdir((string) $cwd);
    }

    expect(file_exists($this->dir.'/Export März 2026.csv'))->toBeTrue();
});

it('bricht bei ungültigem Datum ab und nennt Option und Format (AK-27)', function (string $option, string $value): void {
    $exitCode = Artisan::call('tokens:export', ['path' => $this->path, $option => $value]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain($option)->toContain('JJJJ-MM-TT')
        ->and(file_exists($this->path))->toBeFalse();
})->with([
    ['--from', '21.11.2026'],
    ['--from', '2026-02-30'],
    ['--to', 'morgen'],
    ['--to', ''],
    ['--from', '2026-1-05'],
]);

it('bricht ab, wenn --to vor --from liegt (AK-28)', function (): void {
    $this->artisan('tokens:export', ['path' => $this->path, '--from' => '2026-11-22', '--to' => '2026-11-21'])
        ->assertExitCode(1);

    expect(file_exists($this->path))->toBeFalse();
});

it('bricht bei leerem --model oder --session ab (AK-29)', function (array $options): void {
    $this->artisan('tokens:export', ['path' => $this->path, ...$options])->assertExitCode(1);

    expect(file_exists($this->path))->toBeFalse();
})->with([
    'leeres Modell' => [['--model' => ['']]],
    'leeres Modell neben gültigem' => [['--model' => ['A', '']]],
    'leere Session' => [['--session' => '']],
]);

it('bricht ohne Pfad ab (AK-30)', function (): void {
    expect(fn () => $this->artisan('tokens:export'))->toThrow(RuntimeException::class, 'path');
});

it('exportiert auch in production ohne Rückfrage (AK-31)', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    call();

    $this->artisan('tokens:export', ['path' => $this->path])
        ->expectsOutput('Exportiert: 1 Aufrufe')
        ->assertExitCode(0);
});

it('ändert die gespeicherten Aufrufe nicht (AK-33)', function (): void {
    ApiRequest::factory()->count(5)->create();
    $before = DB::table('api_requests')->orderBy('id')->get()->toJson();

    $this->artisan('tokens:export', ['path' => $this->path])->assertExitCode(0);

    expect(DB::table('api_requests')->orderBy('id')->get()->toJson())->toBe($before);
});

it('exportiert 100.000 Aufrufe in höchstens 10 Sekunden mit 128 MB (AK-32)', function (): void {
    $now = now()->utc();
    $rows = [];

    for ($i = 1; $i <= 100_000; $i++) {
        $rows[] = [
            'occurred_at' => $now->subSeconds($i)->format('Y-m-d H:i:s'),
            'model' => 'claude-sonnet-5-5',
            'input_tokens' => $i,
            'output_tokens' => $i,
            'cache_read_tokens' => $i,
            'cache_creation_tokens' => $i,
            'cost_usd' => $i / 1_000_000,
            'duration_ms' => $i,
            'session_id' => 'session-'.($i % 500),
            'prompt_id' => 'prompt-'.$i,
            'app_version' => '2.1.0',
            'raw' => str_repeat('x', 300),
            'fingerprint' => hash('sha256', (string) $i),
        ];

        if (count($rows) === 1000) {
            DB::table('api_requests')->insert($rows);
            $rows = [];
        }
    }

    $memoryBefore = memory_get_usage();
    memory_reset_peak_usage();
    $start = microtime(true);

    $this->artisan('tokens:export', ['path' => $this->path])
        ->expectsOutput('Exportiert: 100000 Aufrufe')
        ->assertExitCode(0);

    $seconds = microtime(true) - $start;
    $extraMemory = memory_get_peak_usage() - $memoryBefore;

    $lines = 0;
    $handle = fopen($this->path, 'rb');
    while (fgets($handle) !== false) {
        $lines++;
    }
    fclose($handle);

    expect($lines)->toBe(100_001)
        ->and($seconds)->toBeLessThan(10.0)
        ->and($extraMemory)->toBeLessThan(32 * 1024 * 1024);
});
