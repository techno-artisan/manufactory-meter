<?php

use App\Models\ApiRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

it('speichert einen gültigen api_request mit allen Werten (AK-1, AK-16)', function () {
    $record = apiRequestRecord(['event.timestamp' => '2026-11-21T11:15:30.250+01:00']);
    $path = otlpFile(otlpLine($record));

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Neu gespeichert: 1')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(1);
    $call = ApiRequest::firstOrFail();

    expect($call->occurred_at->format('Y-m-d H:i:s.v'))->toBe('2026-11-21 10:15:30.250')
        ->and($call->model)->toBe('claude-sonnet-5-5')
        ->and($call->input_tokens)->toBe(1200)
        ->and($call->output_tokens)->toBe(340)
        ->and($call->cache_read_tokens)->toBe(5000)
        ->and($call->cache_creation_tokens)->toBe(800)
        ->and((float) $call->cost_usd)->toBe(0.0421)
        ->and($call->duration_ms)->toBe(2875)
        ->and($call->session_id)->toBe('f3b1c2d4-5e6f-4a7b-8c9d-0e1f2a3b4c5d')
        ->and($call->prompt_id)->toBe('a1b2c3d4-e5f6-4789-abcd-ef0123456789')
        ->and($call->app_version)->toBe('2.1.0')
        ->and($call->fingerprint)->toMatch('/^[0-9a-f]{64}$/')
        ->and($call->raw)->toBe($record);

    $keys = array_column($call->raw['attributes'], 'key');
    expect($keys)->toContain('user.email', 'user.account_uuid', 'organization.id', 'event.sequence');
});

it('speichert mehrere api_requests einer Zeile einzeln (AK-2)', function () {
    $path = otlpFile(otlpLine(
        apiRequestRecord(['input_tokens' => '1']),
        apiRequestRecord(['input_tokens' => '2']),
        apiRequestRecord(['input_tokens' => '3']),
    ));

    $this->artisan('tokens:import', ['path' => $path])->assertExitCode(0);

    expect(ApiRequest::orderBy('input_tokens')->pluck('input_tokens')->all())->toBe([1, 2, 3]);
});

it('speichert nur api_requests und meldet andere Ereignisse nicht als Fehler (AK-3)', function () {
    $path = otlpFile(
        otlpLine(otherEventRecord('user_prompt'), apiRequestRecord(), otherEventRecord('tool_result')),
        otlpLine(otherEventRecord('api_error')),
    );

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Neu gespeichert: 1')
        ->expectsOutputToContain('Kaputt: 0')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(1);
});

it('erkennt api_request auch nur am Text des Eintrags oder nur am Attribut event.name', function () {
    $onlyBody = apiRequestRecord(['event.name' => null]);
    $onlyAttribute = apiRequestRecord(['input_tokens' => '9']);
    $onlyAttribute['body'] = ['stringValue' => 'irgendwas'];

    $this->artisan('tokens:import', ['path' => otlpFile(otlpLine($onlyBody, $onlyAttribute))])->assertExitCode(0);

    expect(ApiRequest::count())->toBe(2);
});

it('legt beim erneuten Import nichts neu an und zählt alles als schon vorhanden (AK-4, AK-11)', function () {
    $path = otlpFile(
        otlpLine(apiRequestRecord(['input_tokens' => '1'])),
        otlpLine(apiRequestRecord(['input_tokens' => '2']), apiRequestRecord(['input_tokens' => '3'])),
    );

    $this->artisan('tokens:import', ['path' => $path])->assertExitCode(0);

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Neu gespeichert: 0')
        ->expectsOutputToContain('Schon vorhanden: 3')
        ->expectsOutputToContain('Kaputt: 0')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(3);
});

it('speichert bei ergänzter Datei nur die neuen Einträge (AK-5)', function () {
    $path = otlpFile(otlpLine(apiRequestRecord(['input_tokens' => '1'])));
    $this->artisan('tokens:import', ['path' => $path])->assertExitCode(0);

    file_put_contents($path, otlpLine(apiRequestRecord(['input_tokens' => '2']))."\n", FILE_APPEND);

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Neu gespeichert: 1')
        ->expectsOutputToContain('Schon vorhanden: 1')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(2);
});

it('erzeugt aus einer umbenannten Datei mit denselben Einträgen keine Doppelten (AK-6)', function () {
    $line = otlpLine(apiRequestRecord());
    $first = otlpFile($line);
    $second = otlpFile('', $line);

    $this->artisan('tokens:import', ['path' => $first])->assertExitCode(0);
    $this->artisan('tokens:import', ['path' => $second])
        ->expectsOutputToContain('Schon vorhanden: 1')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(1);
});

it('erkennt Doppelte unabhängig von der Reihenfolge der JSON-Schlüssel', function () {
    $record = apiRequestRecord();
    $reordered = array_reverse($record, true);

    $this->artisan('tokens:import', ['path' => otlpFile(otlpLine($record), otlpLine($reordered))])
        ->expectsOutputToContain('Schon vorhanden: 1')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(1);
});

it('überspringt ungültiges JSON mit Zeilennummer und Grund und speichert den Rest (AK-7, AK-8, AK-10)', function () {
    $path = otlpFile(
        otlpLine(apiRequestRecord(['input_tokens' => '1'])),
        '{"resourceLogs": [',
        otlpLine(apiRequestRecord(['input_tokens' => '2'])),
    );

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Zeile 2: ungültiges JSON')
        ->expectsOutputToContain('Neu gespeichert: 2')
        ->expectsOutputToContain('Schon vorhanden: 0')
        ->expectsOutputToContain('Kaputt: 1')
        ->assertExitCode(1);

    expect(ApiRequest::count())->toBe(2);
});

it('speichert einen Eintrag ohne Pflichtwert nicht und nennt Zeile und Wert (AK-9, AK-10)', function (string $missing) {
    $path = otlpFile(
        otlpLine(apiRequestRecord()),
        otlpLine(apiRequestRecord([$missing => null, 'input_tokens' => $missing === 'input_tokens' ? null : '5'])),
    );

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain("Zeile 2: Pflichtwert fehlt: {$missing}")
        ->expectsOutputToContain('Kaputt: 1')
        ->assertExitCode(1);

    expect(ApiRequest::count())->toBe(1);
})->with(['event.timestamp', 'model', 'input_tokens', 'output_tokens']);

it('speichert die gültigen Einträge einer Zeile, auch wenn ein anderer kaputt ist', function () {
    $path = otlpFile(otlpLine(
        apiRequestRecord(['input_tokens' => '1']),
        apiRequestRecord(['model' => null]),
    ));

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Zeile 1: Pflichtwert fehlt: model (Eintrag 2)')
        ->expectsOutputToContain('Neu gespeichert: 1')
        ->assertExitCode(1);

    expect(ApiRequest::count())->toBe(1);
});

it('bricht bei fehlender Datei verständlich ab und speichert nichts (AK-12)', function () {
    $this->artisan('tokens:import', ['path' => '/gibt/es/nicht.jsonl'])
        ->expectsOutputToContain('existiert nicht oder ist nicht lesbar')
        ->assertExitCode(1);

    expect(ApiRequest::count())->toBe(0);
});

it('bricht bei einem Verzeichnis statt einer Datei ab (AK-12)', function () {
    $this->artisan('tokens:import', ['path' => sys_get_temp_dir()])
        ->expectsOutputToContain('Verzeichnis')
        ->assertExitCode(1);
});

it('bricht bei nicht lesbarer Datei ab (AK-12)', function () {
    $path = otlpFile(otlpLine(apiRequestRecord()));
    chmod($path, 0000);

    if (is_readable($path)) {
        $this->markTestSkipped('Läuft als root, Datei bleibt lesbar.');
    }

    $this->artisan('tokens:import', ['path' => $path])->assertExitCode(1);

    expect(ApiRequest::count())->toBe(0);
});

it('speichert fehlende Cache-Token als 0 (AK-13)', function () {
    $this->artisan('tokens:import', ['path' => otlpFile(otlpLine(apiRequestRecord([
        'cache_read_tokens' => null,
        'cache_creation_tokens' => null,
    ])))])->assertExitCode(0);

    $call = ApiRequest::firstOrFail();
    expect($call->cache_read_tokens)->toBe(0)->and($call->cache_creation_tokens)->toBe(0);
});

it('lässt fehlende Kosten, Dauer, Session, Prompt und Version leer (AK-14)', function () {
    $this->artisan('tokens:import', ['path' => otlpFile(otlpLine(apiRequestRecord([
        'cost_usd' => null,
        'duration_ms' => null,
        'session.id' => null,
        'prompt.id' => null,
        'app.version' => null,
    ])))])->assertExitCode(0);

    $call = ApiRequest::firstOrFail();
    expect($call->cost_usd)->toBeNull()
        ->and($call->duration_ms)->toBeNull()
        ->and($call->session_id)->toBeNull()
        ->and($call->prompt_id)->toBeNull()
        ->and($call->app_version)->toBeNull();
});

it('endet bei leerer Datei erfolgreich mit 0/0/0 (EC-1)', function () {
    $path = tempnam(sys_get_temp_dir(), 'otlp');

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Neu gespeichert: 0')
        ->expectsOutputToContain('Schon vorhanden: 0')
        ->expectsOutputToContain('Kaputt: 0')
        ->assertExitCode(0);
});

it('ignoriert leere Zeilen, auch am Dateiende (EC-2)', function () {
    $path = otlpFile('', otlpLine(apiRequestRecord()), '   ', '', '');

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Kaputt: 0')
        ->assertExitCode(0);
});

it('speichert eine halb geschriebene letzte Zeile beim nächsten Import, wenn sie vollständig ist (EC-3)', function () {
    $complete = otlpLine(apiRequestRecord(['input_tokens' => '2']));
    $path = tempnam(sys_get_temp_dir(), 'otlp');
    file_put_contents($path, otlpLine(apiRequestRecord(['input_tokens' => '1']))."\n".substr($complete, 0, 80));

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Zeile 2: ungültiges JSON')
        ->assertExitCode(1);

    file_put_contents($path, substr($complete, 80)."\n", FILE_APPEND);

    $this->artisan('tokens:import', ['path' => $path])
        ->expectsOutputToContain('Neu gespeichert: 1')
        ->expectsOutputToContain('Schon vorhanden: 1')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(2);
});

it('speichert zwei gleiche Einträge in derselben Datei nur einmal (EC-4)', function () {
    $line = otlpLine(apiRequestRecord());

    $this->artisan('tokens:import', ['path' => otlpFile($line, $line)])
        ->expectsOutputToContain('Neu gespeichert: 1')
        ->expectsOutputToContain('Schon vorhanden: 1')
        ->assertExitCode(0);
});

it('meldet gültiges JSON ohne OTLP-Aufbau als kaputte Zeile mit Grund (EC-6)', function (string $line) {
    $this->artisan('tokens:import', ['path' => otlpFile($line)])
        ->expectsOutputToContain('Zeile 1: kein OTLP-Aufbau')
        ->assertExitCode(1);
})->with([
    'ohne resourceLogs' => '{"foo": 1}',
    'Liste statt Objekt' => '[1, 2]',
    'Zahl' => '42',
    'resourceLogs keine Liste' => '{"resourceLogs": "x"}',
    'logRecords keine Liste' => '{"resourceLogs": [{"scopeLogs": [{"logRecords": 5}]}]}',
]);

it('liest Zahlen als Zahl und als Text (EC-7)', function () {
    $this->artisan('tokens:import', ['path' => otlpFile(otlpLine(apiRequestRecord([
        'input_tokens' => 1200,
        'output_tokens' => '340',
        'cache_read_tokens' => 5000.0,
        'cost_usd' => 0.5,
        'duration_ms' => 2875,
    ])))])->assertExitCode(0);

    $call = ApiRequest::firstOrFail();
    expect($call->input_tokens)->toBe(1200)
        ->and($call->output_tokens)->toBe(340)
        ->and($call->cache_read_tokens)->toBe(5000)
        ->and((float) $call->cost_usd)->toBe(0.5)
        ->and($call->duration_ms)->toBe(2875);
});

it('meldet negative oder nicht-numerische Token-Werte als ungültigen Wert (EC-8)', function (string $key, mixed $value) {
    $this->artisan('tokens:import', ['path' => otlpFile(otlpLine(apiRequestRecord([$key => $value])))])
        ->expectsOutputToContain("Zeile 1: ungültiger Wert: {$key}")
        ->expectsOutputToContain('Kaputt: 1')
        ->assertExitCode(1);

    expect(ApiRequest::count())->toBe(0);
})->with([
    ['input_tokens', '-5'],
    ['output_tokens', 'viele'],
    ['cache_read_tokens', -1],
    ['cache_creation_tokens', '1.5'],
    ['event.timestamp', 'gestern'],
]);

it('ergänzt nach einem Abbruch den Rest ohne Doppelte (EC-9)', function () {
    $first = otlpLine(apiRequestRecord(['input_tokens' => '1']));
    $second = otlpLine(apiRequestRecord(['input_tokens' => '2']));

    $this->artisan('tokens:import', ['path' => otlpFile($first)])->assertExitCode(0);
    $this->artisan('tokens:import', ['path' => otlpFile($first, $second)])
        ->expectsOutputToContain('Neu gespeichert: 1')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(2);
});

it('speichert mehr Einträge als ein Speicherpaket fasst vollständig', function () {
    $lines = [];
    for ($i = 1; $i <= 1203; $i++) {
        $lines[] = otlpLine(apiRequestRecord(['input_tokens' => (string) $i]));
    }

    $this->artisan('tokens:import', ['path' => otlpFile(...$lines)])
        ->expectsOutputToContain('Neu gespeichert: 1203')
        ->assertExitCode(0);

    expect(ApiRequest::count())->toBe(1203);
});
