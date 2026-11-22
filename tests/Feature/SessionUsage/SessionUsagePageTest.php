<?php

declare(strict_types=1);

use App\Filament\Pages\DailyUsage;
use App\Filament\Pages\SessionUsage;
use App\Filament\Resources\ApiRequests\Pages\ListApiRequests;
use App\Models\ApiRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 22.11.2026, 12:00 deutsche Zeit
    $this->travelTo(CarbonImmutable::parse('2026-11-22 11:00:00', 'UTC'));
    $this->actingAs(User::factory()->create());
});

/**
 * Speichert einen Aufruf so, wie der Import es tut (Zeitpunkt als UTC-Text mit Millisekunden).
 *
 * @param  array<string, mixed>  $overrides
 */
function storeSessionCall(string $sessionId, string $occurredAtUtc, array $overrides = []): void
{
    DB::table('api_requests')->insert(array_merge([
        'occurred_at' => CarbonImmutable::parse($occurredAtUtc, 'UTC')->format('Y-m-d H:i:s.v'),
        'model' => 'modell-a',
        'input_tokens' => 1,
        'output_tokens' => 1,
        'cache_read_tokens' => 0,
        'cache_creation_tokens' => 0,
        'cost_usd' => '0.0100000000',
        'duration_ms' => 100,
        'session_id' => $sessionId,
        'raw' => json_encode(['attributes' => ['event.name' => 'api_request']]),
        'fingerprint' => hash('sha256', (string) Str::uuid()),
        'created_at' => now(),
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $filters
 */
function sessionPage(array $filters = []): Testable
{
    $component = Livewire::test(SessionUsage::class);

    foreach ($filters as $name => $data) {
        $component->filterTable($name, $data);
    }

    return $component;
}

/**
 * Session-Zeilen der aktuellen Seite (ohne Gesamtsumme).
 *
 * @return list<array<string, mixed>>
 */
function sessionRows(Testable $component): array
{
    return array_values(array_filter(
        $component->instance()->getTableRecords()->all(),
        fn (array $row): bool => $row['kind'] === 'session',
    ));
}

/**
 * @return array<string, mixed>
 */
function sessionTotal(Testable $component): array
{
    $total = array_values(array_filter(
        $component->instance()->getTableRecords()->all(),
        fn (array $row): bool => $row['kind'] === 'total',
    ));

    expect($total)->toHaveCount(1);

    return $total[0];
}

/**
 * @return array<string, mixed>
 */
function sessionRow(Testable $component, string $sessionId): array
{
    $rows = array_values(array_filter(sessionRows($component), fn (array $row): bool => $row['session'] === $sessionId));

    expect($rows)->toHaveCount(1);

    return $rows[0];
}

it('zeigt Menüeintrag, Titel und die 10 Spalten (AK-1)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00');

    $this->get('/admin')->assertOk()->assertSee('Sessions');
    $this->get(SessionUsage::getUrl())->assertOk()->assertSee('Sessions');

    $columns = array_map(fn ($column): string => (string) $column->getLabel(), array_values(sessionPage()->instance()->getTable()->getColumns()));

    expect($columns)->toBe([
        'Session', 'Erster Aufruf', 'Letzter Aufruf', 'Aufrufe', 'Modelle', 'Input-Token',
        'Output-Token', 'Cache-Lese-Token', 'Cache-Schreib-Token', 'Kosten (USD)',
    ]);
});

it('fasst je Session zu genau einer Zeile mit Summen zusammen (AK-2)', function () {
    foreach ([[100, '0.01'], [200, '0.02'], [300, '0.03']] as $i => [$input, $cost]) {
        storeSessionCall('s1', "2026-11-21 1{$i}:00:00", [
            'input_tokens' => $input, 'output_tokens' => $input + 1,
            'cache_read_tokens' => $input + 2, 'cache_creation_tokens' => $input + 3, 'cost_usd' => $cost,
        ]);
    }
    storeSessionCall('s2', '2026-11-21 09:00:00');

    $component = sessionPage();

    expect(sessionRows($component))->toHaveCount(2)
        ->and(sessionRow($component, 's1'))->toMatchArray([
            'calls' => '3', 'input_tokens' => '600', 'output_tokens' => '603',
            'cache_read_tokens' => '606', 'cache_creation_tokens' => '609', 'cost' => '0,0600 $',
        ])
        ->and(sessionRow($component, 's2')['calls'])->toBe('1');
});

it('zeigt ersten und letzten Aufruf in deutscher Zeit (AK-3, EC-5)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00');
    storeSessionCall('s1', '2026-11-21 09:00:00');
    storeSessionCall('s1', '2026-11-21 11:30:00');
    storeSessionCall('s2', '2026-11-21 08:15:00');

    $component = sessionPage();

    expect(sessionRow($component, 's1'))->toMatchArray([
        'first_call' => '21.11.2026 10:00:00', 'last_call' => '21.11.2026 12:30:00',
    ])->and(sessionRow($component, 's2'))->toMatchArray([
        'first_call' => '21.11.2026 09:15:00', 'last_call' => '21.11.2026 09:15:00',
    ]);
});

it('zeigt die Modelle einmal und alphabetisch (AK-4)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00', ['model' => 'model-b']);
    storeSessionCall('s1', '2026-11-21 10:01:00', ['model' => 'model-a']);
    storeSessionCall('s1', '2026-11-21 10:02:00', ['model' => 'model-b']);

    expect(sessionRow(sessionPage(), 's1')['models'])->toBe('model-a, model-b');
});

it('zeigt lange Session-IDs und viele Modelle vollständig (AK-5, EC-6)', function () {
    $uuid = (string) Str::uuid();
    $long = str_repeat('x', 200);
    storeSessionCall($uuid, '2026-11-21 10:00:00');
    storeSessionCall($long, '2026-11-21 10:00:00');

    foreach (range(1, 12) as $n) {
        storeSessionCall($long, '2026-11-21 10:00:00', ['model' => sprintf('modell-%02d', $n)]);
    }

    expect(strlen($uuid))->toBe(36);

    sessionPage()->assertSee($uuid)->assertSee($long)->assertSee('modell-01, modell-02')->assertSee('modell-12');
});

it('zeigt deutsche Zahlenformate (AK-6)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00', [
        'input_tokens' => 12345, 'cache_read_tokens' => 1234567, 'cost_usd' => '0.0123456700',
    ]);

    expect(sessionRow(sessionPage(), 's1'))->toMatchArray([
        'input_tokens' => '12.345', 'cache_read_tokens' => '1.234.567', 'cost' => '0,0123 $',
    ]);
});

it('zeigt fehlende Kosten als Hinweis oder Strich und 0 als bekannt (AK-7 bis AK-9)', function () {
    storeSessionCall('teilweise', '2026-11-21 10:00:00', ['cost_usd' => '0.01']);
    storeSessionCall('teilweise', '2026-11-21 10:01:00', ['cost_usd' => '0.02']);
    storeSessionCall('teilweise', '2026-11-21 10:02:00', ['cost_usd' => null]);
    storeSessionCall('ohne', '2026-11-21 10:00:00', ['cost_usd' => null]);
    storeSessionCall('ohne', '2026-11-21 10:01:00', ['cost_usd' => null]);
    storeSessionCall('null', '2026-11-21 10:00:00', ['cost_usd' => '0']);

    $component = sessionPage();

    expect(sessionRow($component, 'teilweise')['cost'])->toBe('0,0300 $ (1 ohne Kosten)')
        ->and(sessionRow($component, 'ohne')['cost'])->toBe('–')
        ->and(sessionRow($component, 'null')['cost'])->toBe('0,0000 $')
        ->and(sessionTotal($component)['cost'])->toBe('0,0300 $ (3 ohne Kosten)');
});

it('zeigt in der Gesamtsumme einen Strich, wenn alle Kosten fehlen (AK-26)', function () {
    storeSessionCall('a', '2026-11-21 10:00:00', ['cost_usd' => null]);
    storeSessionCall('b', '2026-11-21 10:00:00', ['cost_usd' => null]);

    expect(sessionTotal(sessionPage())['cost'])->toBe('–');
});

it('sortiert standardmäßig nach letztem Aufruf, neueste zuerst (AK-10)', function () {
    storeSessionCall('alt', '2026-11-19 10:00:00');
    storeSessionCall('neu', '2026-11-21 10:00:00');
    storeSessionCall('mitte', '2026-11-18 10:00:00');
    storeSessionCall('mitte', '2026-11-20 10:00:00');

    expect(array_column(sessionRows(sessionPage()), 'session'))->toBe(['neu', 'mitte', 'alt']);
});

it('sortiert per Spaltenkopf in beide Richtungen (AK-11)', function (string $column, array $asc) {
    storeSessionCall('a', '2026-11-18 10:00:00', ['input_tokens' => 5, 'output_tokens' => 1, 'cache_read_tokens' => 3, 'cache_creation_tokens' => 2, 'cost_usd' => '0.03']);
    storeSessionCall('a', '2026-11-21 10:00:00', ['input_tokens' => 5, 'output_tokens' => 1, 'cache_read_tokens' => 3, 'cache_creation_tokens' => 2, 'cost_usd' => '0.03']);
    storeSessionCall('b', '2026-11-19 10:00:00', ['input_tokens' => 1, 'output_tokens' => 9, 'cache_read_tokens' => 1, 'cache_creation_tokens' => 7, 'cost_usd' => '0.01']);
    storeSessionCall('c', '2026-11-17 10:00:00', ['input_tokens' => 3, 'output_tokens' => 5, 'cache_read_tokens' => 9, 'cache_creation_tokens' => 1, 'cost_usd' => '0.50']);
    storeSessionCall('c', '2026-11-17 11:00:00', ['input_tokens' => 3, 'output_tokens' => 5, 'cache_read_tokens' => 9, 'cache_creation_tokens' => 1, 'cost_usd' => '0.50']);
    storeSessionCall('c', '2026-11-17 12:00:00', ['input_tokens' => 3, 'output_tokens' => 5, 'cache_read_tokens' => 9, 'cache_creation_tokens' => 1, 'cost_usd' => '0.50']);

    $component = sessionPage()->sortTable($column);
    expect(array_column(sessionRows($component), 'session'))->toBe($asc);

    $component->sortTable($column);
    expect(array_column(sessionRows($component), 'session'))->toBe(array_reverse($asc));
})->with([
    'erster Aufruf' => ['first_call', ['c', 'a', 'b']],
    'letzter Aufruf' => ['last_call', ['c', 'b', 'a']],
    'Aufrufe' => ['calls', ['b', 'a', 'c']],
    'Input-Token' => ['input_tokens', ['b', 'c', 'a']],
    'Output-Token' => ['output_tokens', ['a', 'b', 'c']],
    'Cache-Lese-Token' => ['cache_read_tokens', ['b', 'a', 'c']],
    'Cache-Schreib-Token' => ['cache_creation_tokens', ['c', 'a', 'b']],
    'Kosten' => ['cost', ['b', 'a', 'c']],
]);

it('stellt Sessions ohne Kosten in beiden Richtungen ans Ende (AK-12, AK-13)', function () {
    storeSessionCall('klein', '2026-11-21 10:00:00', ['cost_usd' => '0.01']);
    storeSessionCall('gross', '2026-11-21 10:00:00', ['cost_usd' => '0.05']);
    storeSessionCall('ohne', '2026-11-21 10:00:00', ['cost_usd' => null]);
    storeSessionCall('teil', '2026-11-21 10:00:00', ['cost_usd' => '0.03']);
    storeSessionCall('teil', '2026-11-21 10:00:00', ['cost_usd' => null]);
    storeSessionCall('zwei', '2026-11-21 10:00:00', ['cost_usd' => '0.02']);

    $component = sessionPage()->sortTable('cost', 'desc');
    expect(array_column(sessionRows($component), 'session'))->toBe(['gross', 'teil', 'zwei', 'klein', 'ohne']);

    $component->sortTable('cost', 'asc');
    expect(array_column(sessionRows($component), 'session'))->toBe(['klein', 'zwei', 'teil', 'gross', 'ohne']);
});

it('bietet Session und Modelle nicht zum Sortieren an (AK-14)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00');

    $table = sessionPage()->instance()->getTable();

    expect($table->getColumn('session')->isSortable())->toBeFalse()
        ->and($table->getColumn('models')->isSortable())->toBeFalse();
});

it('setzt beim ersten Öffnen die letzten 30 Tage inklusive heute (AK-15)', function () {
    storeSessionCall('alt', '2026-10-23 12:00:00');
    storeSessionCall('drin', '2026-10-23 22:00:00'); // 24.10. 00:00 deutsche Zeit
    storeSessionCall('drin', '2026-10-23 21:59:59'); // 23.10. 23:59:59 deutsche Zeit

    $component = sessionPage()
        ->assertSet('tableFilters.period.from', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-10-24')
        ->assertSet('tableFilters.period.until', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-11-22');

    expect(array_column(sessionRows($component), 'session'))->toBe(['drin'])
        ->and(sessionRow($component, 'drin')['calls'])->toBe('1')
        ->and(sessionTotal($component)['calls'])->toBe('1');
});

it('zählt nur Aufrufe im Zeitraum, beide Grenzen inklusive und optional (AK-16 bis AK-19)', function (?string $from, ?string $until, array $expected) {
    storeSessionCall('s1', '2026-11-19 23:00:00'); // 20.11. 00:00
    storeSessionCall('s1', '2026-11-20 22:59:59'); // 20.11. 23:59:59
    storeSessionCall('s1', '2026-11-20 23:00:00'); // 21.11. 00:00:00
    storeSessionCall('s1', '2026-11-21 22:59:59'); // 21.11. 23:59:59
    storeSessionCall('s1', '2026-11-21 23:00:00'); // 22.11. 00:00
    storeSessionCall('nur20', '2026-11-20 10:00:00');

    $component = sessionPage(['period' => ['from' => $from, 'until' => $until]]);

    $rows = [];
    foreach (sessionRows($component) as $row) {
        $rows[$row['session']] = [$row['calls'], $row['first_call'], $row['last_call']];
    }

    expect($rows)->toBe($expected);
})->with([
    'ein Tag' => ['2026-11-21', '2026-11-21', ['s1' => ['2', '21.11.2026 00:00:00', '21.11.2026 23:59:59']]],
    'nur von' => ['2026-11-21', null, ['s1' => ['3', '21.11.2026 00:00:00', '22.11.2026 00:00:00']]],
    'nur bis' => [null, '2026-11-21', [
        's1' => ['4', '20.11.2026 00:00:00', '21.11.2026 23:59:59'],
        'nur20' => ['1', '20.11.2026 11:00:00', '20.11.2026 11:00:00'],
    ]],
]);

it('ordnet Aufrufe dem deutschen Kalendertag zu (AK-20)', function () {
    storeSessionCall('s1', '2026-11-21 23:30:00');

    $component = sessionPage(['period' => ['from' => '2026-11-22', 'until' => '2026-11-22']]);

    expect(sessionRow($component, 's1')['first_call'])->toBe('22.11.2026 00:30:00');
});

it('erfasst am Tag der Zeitumstellung den ganzen deutschen Tag (EC-3)', function () {
    storeSessionCall('s1', '2026-10-24 22:00:00'); // 25.10. 00:00 (Sommerzeit)
    storeSessionCall('s1', '2026-10-25 22:59:59'); // 25.10. 23:59:59 (Winterzeit)
    storeSessionCall('s1', '2026-10-25 23:00:00'); // 26.10.

    $component = sessionPage(['period' => ['from' => '2026-10-25', 'until' => '2026-10-25']]);

    expect(sessionRow($component, 's1'))->toMatchArray([
        'calls' => '2', 'first_call' => '25.10.2026 00:00:00', 'last_call' => '25.10.2026 23:59:59',
    ]);
});

it('bietet genau die gespeicherten Modelle an und filtert Zeilen und Gesamtsumme (AK-21 bis AK-23)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00', ['model' => 'A', 'input_tokens' => 1]);
    storeSessionCall('s1', '2026-11-21 10:00:00', ['model' => 'B', 'input_tokens' => 10]);
    storeSessionCall('s1', '2026-11-21 10:00:00', ['model' => 'C', 'input_tokens' => 100]);
    storeSessionCall('nurC', '2026-11-21 10:00:00', ['model' => 'C', 'input_tokens' => 1000]);

    $component = sessionPage();
    expect($component->instance()->getTable()->getFilter('model')->getOptions())->toBe(['A' => 'A', 'B' => 'B', 'C' => 'C']);

    $component->filterTable('model', ['A', 'B']);

    expect(array_column(sessionRows($component), 'session'))->toBe(['s1'])
        ->and(sessionRow($component, 's1'))->toMatchArray(['calls' => '2', 'models' => 'A, B', 'input_tokens' => '11'])
        ->and(sessionTotal($component))->toMatchArray(['calls' => '2', 'input_tokens' => '11']);

    $component->filterTable('model', ['A']);
    expect(array_column(sessionRows($component), 'session'))->toBe(['s1']);
});

it('stimmt mit der Gesamtsumme der Tagesübersicht überein (AK-24)', function () {
    foreach (range(1, 60) as $i) {
        storeSessionCall('s'.($i % 7), CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC')->subHours($i * 13)->format('Y-m-d H:i:s'), [
            'model' => ['a', 'b', 'c'][$i % 3],
            'input_tokens' => $i * 101, 'output_tokens' => $i * 7, 'cache_read_tokens' => $i * 1003, 'cache_creation_tokens' => $i * 11,
            'cost_usd' => $i % 5 === 0 ? null : '0.00012345'.$i,
        ]);
    }

    $filters = ['period' => ['from' => '2026-11-01', 'until' => '2026-11-20'], 'model' => ['a', 'c']];
    $sessions = sessionTotal(sessionPage($filters));

    $daily = Livewire::test(DailyUsage::class);
    foreach ($filters as $name => $data) {
        $daily->filterTable($name, $data);
    }
    $dailyTotal = collect($daily->instance()->getTableRecords()->all())->firstWhere('kind', 'total');

    foreach (['calls', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_creation_tokens', 'cost'] as $key) {
        expect($sessions[$key])->toBe($dailyTotal[$key]);
    }
});

it('zeigt die Gesamtsumme mit Anzahl der Sessions über alle Seiten (AK-25, AK-27, AK-28)', function () {
    foreach (range(1, 120) as $i) {
        storeSessionCall(sprintf('s%03d', $i), '2026-11-21 10:00:00', ['input_tokens' => 2]);
    }

    $component = sessionPage();
    $records = $component->instance()->getTableRecords();
    $table = $component->instance()->getTable();

    expect($table->getPaginationPageOptions())->toBe([25, 50, 100])
        ->and($records->perPage())->toBe(50)
        ->and(sessionRows($component))->toHaveCount(50)
        ->and($records->total())->toBe(120)
        ->and($records->lastItem())->toBe(50)
        ->and(sessionTotal($component))->toMatchArray(['session' => 'Gesamtsumme (120 Sessions)', 'calls' => '120', 'input_tokens' => '240', 'url' => null]);

    $component->call('gotoPage', 3, 'page');
    expect(sessionRows($component))->toHaveCount(20)
        ->and(sessionTotal($component)['calls'])->toBe('120');

    $component->set('tableRecordsPerPage', 25);
    expect(sessionRows($component))->toHaveCount(25);
});

it('zeigt beim Blättern jede Session genau einmal, auch bei gleichen Werten (EC-4)', function () {
    foreach (range(1, 60) as $i) {
        storeSessionCall(sprintf('s%02d', $i), '2026-11-21 10:00:00');
    }

    $seen = [];
    $component = sessionPage()->sortTable('cost', 'desc')->set('tableRecordsPerPage', 25);

    foreach ([1, 2, 3] as $page) {
        $component->call('gotoPage', $page, 'page');
        $seen = [...$seen, ...array_column(sessionRows($component), 'session')];
    }

    expect($seen)->toHaveCount(60)->and(array_unique($seen))->toHaveCount(60);
});

it('zählt Aufrufe ohne Session-ID nicht', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00');
    storeSessionCall('x', '2026-11-21 10:00:00', ['session_id' => null]);
    storeSessionCall('x', '2026-11-21 10:00:00', ['session_id' => '']);

    $component = sessionPage();

    expect(array_column(sessionRows($component), 'session'))->toBe(['s1'])
        ->and(sessionTotal($component)['session'])->toBe('Gesamtsumme (1 Session)');
});

it('summiert sehr große Werte und viele kleine Kosten exakt (EC-7, EC-8)', function () {
    $batch = [];
    foreach (range(1, 10_000) as $i) {
        $batch[] = [
            'occurred_at' => '2026-11-21 10:00:00.000', 'model' => 'a', 'session_id' => 's1',
            'input_tokens' => 1_000_000, 'output_tokens' => 0, 'cache_read_tokens' => 0, 'cache_creation_tokens' => 0,
            'cost_usd' => '0.0000123400', 'raw' => '{}', 'fingerprint' => hash('sha256', 'f'.$i),
        ];
        if (count($batch) === 1000) {
            DB::table('api_requests')->insert($batch);
            $batch = [];
        }
    }

    expect(sessionRow(sessionPage(), 's1'))->toMatchArray([
        'calls' => '10.000', 'input_tokens' => '10.000.000.000', 'cost' => '0,1234 $',
    ]);
});

it('öffnet bei Klick die Liste mit Session-, Zeitraum- und Modell-Filter (AK-29)', function () {
    storeSessionCall('s1', '2026-11-19 22:59:59', ['model' => 'A']); // 19.11.
    storeSessionCall('s1', '2026-11-19 23:00:00', ['model' => 'A']); // 20.11. 00:00
    storeSessionCall('s1', '2026-11-22 10:00:00', ['model' => 'A']);
    storeSessionCall('s1', '2026-11-21 10:00:00', ['model' => 'B']);
    storeSessionCall('s2', '2026-11-21 10:00:00', ['model' => 'A']);

    $component = sessionPage(['period' => ['from' => '2026-11-20', 'until' => '2026-11-22'], 'model' => ['A']]);
    $row = sessionRow($component, 's1');

    expect($row['url'])->toStartWith(url('/admin/api-aufrufe'));

    parse_str((string) parse_url($row['url'], PHP_URL_QUERY), $query);

    expect($query['filters'])->toBe([
        'session' => ['session_id' => 's1'],
        'period' => ['from' => '2026-11-20', 'until' => '2026-11-22'],
        'model' => ['values' => ['A']],
    ]);

    $this->get($row['url'])->assertOk()->assertSee('Session s1');

    $expected = ApiRequest::query()->where('session_id', 's1')->where('model', 'A')
        ->where('occurred_at', '>=', '2026-11-19 23:00:00')->get();

    Livewire::withQueryParams($query)
        ->test(ListApiRequests::class)
        ->assertCanSeeTableRecords($expected)
        ->assertCountTableRecords((int) $row['calls']);
});

it('übergibt leere Filter als leer an die Liste (AK-30)', function () {
    storeSessionCall('s1', '2026-01-05 10:00:00', ['model' => 'A']);
    storeSessionCall('s1', '2026-11-21 10:00:00', ['model' => 'B']);

    // In der Liste ist noch ein alter Filter gemerkt.
    Livewire::test(ListApiRequests::class)->filterTable('model', ['B'])->filterTable('period', ['from' => '2026-11-21', 'until' => null]);

    $row = sessionRow(sessionPage(['period' => ['from' => null, 'until' => null]]), 's1');
    parse_str((string) parse_url($row['url'], PHP_URL_QUERY), $query);

    expect($query['filters']['period'])->toBe(['from' => '', 'until' => ''])
        ->and($query['filters']['model'] ?? [])->toBe([]);

    Livewire::withQueryParams($query)
        ->test(ListApiRequests::class)
        ->assertSet('tableFilters.model.values', [])
        ->assertCountTableRecords(2);
});

it('zeigt den Session-Filter in der Liste als einzeln entfernbaren Filter (AK-31)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00');
    storeSessionCall('s2', '2026-11-21 10:00:00');

    $component = Livewire::test(ListApiRequests::class)
        ->filterTable('session', ['session_id' => 's1'])
        ->assertCountTableRecords(1)
        ->assertSee('Session s1');

    $component->call('removeTableFilter', 'session', 'session_id')
        ->assertCountTableRecords(2)
        ->assertDontSee('Session s1');
});

it('zeigt bei unbekannter Session-ID in der Liste den Filter-Hinweis (EC-10)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00');

    Livewire::withQueryParams(['filters' => ['session' => ['session_id' => 'gibt-es-nicht']]])
        ->test(ListApiRequests::class)
        ->assertCountTableRecords(0)
        ->assertSee('Keine Aufrufe für diese Filter');
});

it('behält Filter, Sortierung und Seitengröße nach der Rückkehr (AK-32)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00', ['model' => 'a']);

    sessionPage(['period' => ['from' => '2026-11-01', 'until' => '2026-11-21'], 'model' => ['a']])
        ->sortTable('cost', 'asc')
        ->set('tableRecordsPerPage', 100);

    $this->get(ListApiRequests::getUrl())->assertOk();

    Livewire::test(SessionUsage::class)
        ->assertSet('tableFilters.period.from', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-11-01')
        ->assertSet('tableFilters.period.until', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-11-21')
        ->assertSet('tableFilters.model.values', ['a'])
        ->assertSet('tableSort', 'cost:asc')
        ->assertSet('tableRecordsPerPage', 100);
});

it('zeigt bei leerer Datenbank den Import-Hinweis (AK-33)', function () {
    sessionPage()
        ->assertCountTableRecords(0)
        ->assertSee('Noch keine API-Aufrufe')
        ->assertSee('php artisan tokens:import')
        ->assertDontSee('Filter zurücksetzen');
});

it('zeigt bei leeren Treffern den Filter-Hinweis und setzt auf den Standard zurück (AK-34, EC-1)', function () {
    storeSessionCall('s1', '2026-11-10 10:00:00');

    sessionPage(['period' => ['from' => '2026-11-21', 'until' => '2026-11-01']])
        ->assertCountTableRecords(0)
        ->assertSee('Keine Aufrufe für diese Filter');

    $component = sessionPage(['period' => ['from' => '2026-11-01', 'until' => '2026-11-02']])
        ->assertCountTableRecords(0)
        ->assertSee('Keine Aufrufe für diese Filter')
        ->assertSee('Filter zurücksetzen')
        ->callTableAction('resetFilters')
        ->assertSet('tableFilters.period.from', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-10-24')
        ->assertSet('tableFilters.period.until', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-11-22');

    expect(array_column(sessionRows($component), 'session'))->toBe(['s1']);
});

it('bietet keine Aktionen zum Anlegen, Ändern oder Löschen (AK-35)', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00');

    $component = sessionPage();
    $table = $component->instance()->getTable();

    expect($table->getRecordActions())->toBe([])
        ->and($table->getToolbarActions())->toBe([])
        ->and($table->getHeaderActions())->toBe([]);

    $component->assertDontSee(['Anlegen', 'Bearbeiten', 'Löschen', 'Create', 'Edit', 'Delete']);
});

it('leitet nicht angemeldete Besucher zur Anmeldung (AK-38)', function () {
    auth()->logout();
    storeSessionCall('s1', '2026-11-21 10:00:00', ['input_tokens' => 4711]);

    $this->get(SessionUsage::getUrl())->assertRedirect('/admin/login');
});

it('verweigert in production den Zugang (AK-39)', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get(SessionUsage::getUrl())->assertForbidden();
});

it('ändert beim Anzeigen keine Daten', function () {
    storeSessionCall('s1', '2026-11-21 10:00:00');
    $before = DB::table('api_requests')->get()->toArray();

    sessionPage()->sortTable('cost');

    expect(DB::table('api_requests')->get()->toArray())->toEqual($before);
});

it('gibt Session-IDs maskiert aus und baut den Link sicher (Sicherheit)', function () {
    $evil = '<script>alert(1)</script>&filters[x]=1';
    storeSessionCall($evil, '2026-11-21 10:00:00');

    $component = sessionPage()
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;');

    parse_str((string) parse_url(sessionRows($component)[0]['url'], PHP_URL_QUERY), $query);

    expect($query['filters']['session']['session_id'])->toBe($evil);
});

it('stürzt bei manipulierten Filter-, Sortier- und Seitenwerten nicht ab (Sicherheit)', function (string $property, mixed $value) {
    storeSessionCall('s1', '2026-11-21 10:00:00');

    sessionPage()
        ->set($property, $value)
        ->assertOk()
        ->assertSee('Sessions');
})->with([
    'kein Datum' => ['tableFilters', ['period' => ['from' => 'kein-datum', 'until' => "'; drop table api_requests; --"]]],
    'Modelle als Text' => ['tableFilters', ['model' => ['values' => "' or 1=1 --"]]],
    'verschachtelte Werte' => ['tableFilters', ['period' => ['from' => ['x']]]],
    'unbekannte Sortierung' => ['tableSort', 'session_id; drop table api_requests:desc'],
    'Seitengröße 1000' => ['tableRecordsPerPage', 1000],
]);

it('zeigt 100.000 Aufrufe in 5.000 Sessions jeweils in höchstens 2 Sekunden (AK-36, AK-37)', function () {
    ini_set('memory_limit', '512M');

    $start = CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC');
    $models = ['model-a', 'model-b', 'model-c', 'model-d'];
    $sessions = array_map(fn (): string => (string) Str::uuid(), range(1, 5_000));
    $batch = [];

    for ($i = 0; $i < 100_000; $i++) {
        $batch[] = [
            'occurred_at' => $start->subSeconds(intdiv($i * 365 * 86_400, 100_000))->format('Y-m-d H:i:s.v'),
            'model' => $models[$i % 4],
            'session_id' => $sessions[intdiv($i, 20)],
            'input_tokens' => 25_000,
            'output_tokens' => 5_000,
            'cache_read_tokens' => 1_000_000,
            'cache_creation_tokens' => 50_000,
            'cost_usd' => $i % 11 === 0 ? null : '0.9000000000',
            'duration_ms' => 100,
            'raw' => '{}',
            'fingerprint' => hash('sha256', (string) $i),
            'created_at' => $start->format('Y-m-d H:i:s'),
        ];

        if (count($batch) === 2_000) {
            DB::table('api_requests')->insert($batch);
            $batch = [];
        }
    }

    $page = sessionPage();

    $steps = [
        'Filter' => fn () => $page->filterTable('period', ['from' => null, 'until' => null]),
        'Seitengröße' => fn () => $page->set('tableRecordsPerPage', 100),
        'Sortieren' => fn () => $page->sortTable('cost', 'desc'),
        'Blättern' => fn () => $page->call('gotoPage', 20, 'page'),
        'Neu laden' => fn () => Livewire::test(SessionUsage::class),
        'Liste mit Session-Filter' => fn () => Livewire::withQueryParams(['filters' => ['session' => ['session_id' => $sessions[2_500]]]])
            ->test(ListApiRequests::class)->assertCountTableRecords(20),
    ];

    foreach ($steps as $label => $step) {
        $began = microtime(true);
        $step();
        $seconds = microtime(true) - $began;

        expect($seconds)->toBeLessThan(2.0, "{$label} dauerte {$seconds} s");
    }

    expect($page->instance()->getTableRecords()->total())->toBe(5_000);
});
