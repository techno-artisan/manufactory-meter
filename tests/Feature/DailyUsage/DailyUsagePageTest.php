<?php

declare(strict_types=1);

use App\Filament\Pages\DailyUsage;
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
function storeUsage(string $occurredAtUtc, array $overrides = []): void
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
        'raw' => json_encode(['attributes' => ['event.name' => 'api_request']]),
        'fingerprint' => hash('sha256', (string) Str::uuid()),
        'created_at' => now(),
    ], $overrides));
}

/**
 * Sichtbare Zeilen der aktuellen Seite als Liste.
 *
 * @return list<array<string, mixed>>
 */
function usageRows(Testable $component): array
{
    return array_values($component->instance()->getTableRecords()->all());
}

/**
 * @return list<array<string, mixed>>
 */
function rowsOfKind(Testable $component, string $kind): array
{
    return array_values(array_filter(usageRows($component), fn (array $row): bool => $row['kind'] === $kind));
}

/**
 * @param  array<string, mixed>  $filters
 */
function usagePage(array $filters = []): Testable
{
    $component = Livewire::test(DailyUsage::class);

    foreach ($filters as $name => $data) {
        $component->filterTable($name, $data);
    }

    return $component;
}

it('zeigt Menüeintrag, Titel und die 8 Spalten (AK-1)', function () {
    storeUsage('2026-11-21 10:00:00');

    $this->get('/admin')->assertOk()->assertSee('Tagesübersicht');

    $this->get(DailyUsage::getUrl())
        ->assertOk()
        ->assertSee('Tagesübersicht')
        ->assertSeeInOrder(['Tag', 'Modell', 'Aufrufe', 'Input-Token', 'Output-Token', 'Cache-Lese-Token', 'Cache-Schreib-Token', 'Kosten (USD)']);
});

it('fasst je Tag und Modell zu einer Modellzeile zusammen (AK-2)', function () {
    storeUsage('2026-11-21 08:00:00', ['input_tokens' => 100, 'output_tokens' => 10, 'cache_read_tokens' => 1000, 'cache_creation_tokens' => 5, 'cost_usd' => '0.01']);
    storeUsage('2026-11-21 09:00:00', ['input_tokens' => 200, 'output_tokens' => 20, 'cache_read_tokens' => 2000, 'cache_creation_tokens' => 6, 'cost_usd' => '0.02']);
    storeUsage('2026-11-21 10:00:00', ['model' => 'modell-b']);

    $models = rowsOfKind(usagePage(), 'model');

    expect($models)->toHaveCount(2)
        ->and($models[0])->toMatchArray([
            'day' => '21.11.2026', 'model' => 'modell-a', 'calls' => '2', 'input_tokens' => '300',
            'output_tokens' => '30', 'cache_read_tokens' => '3.000', 'cache_creation_tokens' => '11', 'cost' => '0,0300 $',
        ])
        ->and($models[1])->toMatchArray(['day' => '21.11.2026', 'model' => 'modell-b', 'calls' => '1']);
});

it('zeigt den neuesten Tag oben und Modelle alphabetisch (AK-3, AK-4)', function () {
    storeUsage('2026-11-20 10:00:00');
    storeUsage('2026-11-22 10:00:00', ['model' => 'b']);
    storeUsage('2026-11-22 10:00:00', ['model' => 'a']);
    storeUsage('2026-11-22 10:00:00', ['model' => 'c']);
    storeUsage('2026-11-21 10:00:00');

    $rows = array_map(fn (array $row): string => $row['day'].' '.$row['model'], usageRows(usagePage()));

    expect($rows)->toBe([
        '22.11.2026 a', '22.11.2026 b', '22.11.2026 c', '22.11.2026 Tagessumme',
        '21.11.2026 modell-a', '21.11.2026 Tagessumme',
        '20.11.2026 modell-a', '20.11.2026 Tagessumme',
        'Gesamtsumme ',
    ]);
});

it('zeigt Tagessumme und Gesamtsumme (AK-5, AK-6)', function () {
    storeUsage('2026-11-21 10:00:00', ['model' => 'a', 'input_tokens' => 10, 'output_tokens' => 1, 'cache_read_tokens' => 100, 'cache_creation_tokens' => 2, 'cost_usd' => '0.5']);
    storeUsage('2026-11-21 11:00:00', ['model' => 'b', 'input_tokens' => 20, 'output_tokens' => 2, 'cache_read_tokens' => 200, 'cache_creation_tokens' => 3, 'cost_usd' => '0.25']);
    storeUsage('2026-11-20 11:00:00', ['model' => 'b', 'input_tokens' => 5, 'output_tokens' => 5, 'cache_read_tokens' => 5, 'cache_creation_tokens' => 5, 'cost_usd' => '1']);

    $component = usagePage();

    expect(rowsOfKind($component, 'day')[0])->toMatchArray([
        'day' => '21.11.2026', 'calls' => '2', 'input_tokens' => '30', 'output_tokens' => '3',
        'cache_read_tokens' => '300', 'cache_creation_tokens' => '5', 'cost' => '0,7500 $',
    ])->and(rowsOfKind($component, 'total')[0])->toMatchArray([
        'calls' => '3', 'input_tokens' => '35', 'output_tokens' => '8',
        'cache_read_tokens' => '305', 'cache_creation_tokens' => '10', 'cost' => '1,7500 $',
    ]);
});

it('blättert nach ganzen Tagen und zeigt auf jeder Seite die Gesamtsumme aller Tage (AK-7)', function () {
    for ($day = 1; $day <= 12; $day++) {
        storeUsage(sprintf('2026-11-%02d 10:00:00', $day), ['model' => 'a']);
        storeUsage(sprintf('2026-11-%02d 10:00:00', $day), ['model' => 'b']);
    }

    $component = usagePage();
    $page1 = usageRows($component);

    expect(rowsOfKind($component, 'day'))->toHaveCount(10)
        ->and(end($page1))->toMatchArray(['kind' => 'total', 'calls' => '24']);

    $component->call('gotoPage', 2, 'page');
    $page2 = usageRows($component);

    expect(array_column(rowsOfKind($component, 'model'), 'day'))->toBe(['02.11.2026', '02.11.2026', '01.11.2026', '01.11.2026'])
        ->and(end($page2))->toMatchArray(['kind' => 'total', 'calls' => '24']);
});

it('setzt beim ersten Öffnen die letzten 30 Tage inklusive heute (AK-8)', function () {
    storeUsage('2026-10-23 12:00:00', ['input_tokens' => 1000]);
    storeUsage('2026-10-24 12:00:00');
    storeUsage('2026-11-22 10:00:00');

    $component = usagePage()
        ->assertSet('tableFilters.period.from', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-10-24')
        ->assertSet('tableFilters.period.until', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-11-22');

    expect(array_column(rowsOfKind($component, 'model'), 'day'))->toBe(['22.11.2026', '24.10.2026'])
        ->and(rowsOfKind($component, 'total')[0]['input_tokens'])->toBe('2');
});

it('filtert nach deutschen Kalendertagen, beide Grenzen inklusive und optional (AK-9 bis AK-11)', function (?string $from, ?string $until, array $expected) {
    storeUsage('2026-11-20 10:00:00');
    storeUsage('2026-11-20 23:00:00'); // 00:00:00 deutsche Zeit am 21.11.
    storeUsage('2026-11-21 22:59:59'); // 23:59:59 deutsche Zeit am 21.11.
    storeUsage('2026-11-22 10:00:00');

    $component = usagePage(['period' => ['from' => $from, 'until' => $until]]);

    expect(array_column(rowsOfKind($component, 'model'), 'day'))->toBe($expected);

    if ($expected === ['21.11.2026']) {
        expect(rowsOfKind($component, 'model')[0]['calls'])->toBe('2');
    }
})->with([
    'genau ein Tag' => ['2026-11-21', '2026-11-21', ['21.11.2026']],
    'nur von' => ['2026-11-21', null, ['22.11.2026', '21.11.2026']],
    'nur bis' => [null, '2026-11-21', ['21.11.2026', '20.11.2026']],
]);

it('ordnet Aufrufe dem deutschen Kalendertag zu (AK-12)', function () {
    storeUsage('2026-11-21 23:30:00');

    expect(rowsOfKind(usagePage(), 'model')[0]['day'])->toBe('22.11.2026');
});

it('zählt am Tag der Zeitumstellung jeden Aufruf genau einmal (EC-2)', function () {
    storeUsage('2026-10-24 22:00:00'); // 25.10. 00:00 Sommerzeit
    storeUsage('2026-10-25 00:30:00'); // 02:30 Sommerzeit
    storeUsage('2026-10-25 01:30:00'); // 02:30 Winterzeit
    storeUsage('2026-10-25 22:59:59'); // 23:59:59 Winterzeit
    storeUsage('2026-10-25 23:00:00'); // 26.10. 00:00

    $component = usagePage(['period' => ['from' => '2026-10-25', 'until' => '2026-10-25']]);

    expect(rowsOfKind($component, 'model'))->toHaveCount(1)
        ->and(rowsOfKind($component, 'model')[0])->toMatchArray(['day' => '25.10.2026', 'calls' => '4']);
});

it('bietet genau die gespeicherten Modelle an und filtert auch die Summen (AK-13, AK-14)', function () {
    foreach (['a', 'b', 'c', 'a'] as $model) {
        storeUsage('2026-11-21 10:00:00', ['model' => $model]);
    }

    $component = usagePage()
        ->assertTableFilterExists('model', fn ($filter) => $filter->getOptions() === ['a' => 'a', 'b' => 'b', 'c' => 'c'])
        ->filterTable('model', ['a', 'b']);

    expect(array_column(rowsOfKind($component, 'model'), 'model'))->toBe(['a', 'b'])
        ->and(rowsOfKind($component, 'day')[0]['calls'])->toBe('3')
        ->and(rowsOfKind($component, 'total')[0]['calls'])->toBe('3');
});

it('zeigt fehlende Kosten als Hinweis oder Strich, in allen Summen (AK-15 bis AK-17)', function () {
    storeUsage('2026-11-21 10:00:00', ['model' => 'a', 'cost_usd' => '0.01']);
    storeUsage('2026-11-21 10:00:00', ['model' => 'a', 'cost_usd' => '0.02']);
    storeUsage('2026-11-21 10:00:00', ['model' => 'a', 'cost_usd' => null]);
    storeUsage('2026-11-21 10:00:00', ['model' => 'b', 'cost_usd' => null]);
    storeUsage('2026-11-21 10:00:00', ['model' => 'b', 'cost_usd' => null]);
    storeUsage('2026-11-21 10:00:00', ['model' => 'b', 'cost_usd' => null]);
    storeUsage('2026-11-20 10:00:00', ['model' => 'c', 'cost_usd' => null]);
    storeUsage('2026-11-19 10:00:00', ['model' => 'd', 'cost_usd' => '0']);

    $component = usagePage();
    $models = collect(rowsOfKind($component, 'model'))->keyBy('model');

    expect($models['a']['cost'])->toBe('0,0300 $ (1 ohne Kosten)')
        ->and($models['b']['cost'])->toBe('–')
        ->and($models['d']['cost'])->toBe('0,0000 $')
        ->and(rowsOfKind($component, 'day')[0]['cost'])->toBe('0,0300 $ (4 ohne Kosten)')
        ->and(rowsOfKind($component, 'total')[0]['cost'])->toBe('0,0300 $ (5 ohne Kosten)');
});

it('zeigt deutsche Zahlenformate mit 4 Nachkommastellen (AK-18)', function () {
    storeUsage('2026-11-21 10:00:00', ['input_tokens' => 12345, 'cache_read_tokens' => 1234567, 'cost_usd' => '0.01234567']);

    expect(rowsOfKind(usagePage(), 'model')[0])->toMatchArray([
        'input_tokens' => '12.345', 'cache_read_tokens' => '1.234.567', 'cost' => '0,0123 $',
    ]);
});

it('summiert sehr große Werte und viele kleine Kosten exakt (EC-5, EC-6)', function () {
    $rows = [];

    for ($i = 0; $i < 10_000; $i++) {
        $rows[] = [
            'occurred_at' => '2026-11-21 10:00:00.000', 'model' => 'a', 'input_tokens' => 1_000_000,
            'output_tokens' => 0, 'cache_read_tokens' => 0, 'cache_creation_tokens' => 0,
            'cost_usd' => '0.00001234', 'raw' => '{}', 'fingerprint' => hash('sha256', (string) $i),
        ];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('api_requests')->insert($chunk);
    }

    expect(rowsOfKind(usagePage(), 'model')[0])->toMatchArray([
        'calls' => '10.000', 'input_tokens' => '10.000.000.000', 'cost' => '0,1234 $',
    ]);
});

it('öffnet bei Klick auf eine Modellzeile die Liste mit passendem Tag und Modell (AK-19)', function () {
    storeUsage('2026-11-20 23:00:00', ['model' => 'a']); // 21.11. 00:00 deutsche Zeit
    storeUsage('2026-11-21 12:00:00', ['model' => 'a']);
    storeUsage('2026-11-21 12:00:00', ['model' => 'b']);
    storeUsage('2026-11-21 23:00:00', ['model' => 'a']); // 22.11.

    $row = rowsOfKind(usagePage(), 'model');
    $row = collect($row)->first(fn (array $row): bool => $row['day'] === '21.11.2026' && $row['model'] === 'a');

    expect($row['url'])->toContain('/admin/api-aufrufe');

    parse_str((string) parse_url($row['url'], PHP_URL_QUERY), $query);

    expect($query['filters'])->toBe([
        'model' => ['values' => ['a']],
        'period' => ['from' => '2026-11-21', 'until' => '2026-11-21'],
    ]);

    $this->get($row['url'])->assertOk();

    $ids = ApiRequest::query()->where('model', 'a')
        ->whereBetween('occurred_at', ['2026-11-20 23:00:00', '2026-11-21 22:59:59.999'])->pluck('id');

    Livewire::withQueryParams($query)
        ->test(ListApiRequests::class)
        ->assertCanSeeTableRecords(ApiRequest::query()->whereIn('id', $ids)->get())
        ->assertCountTableRecords(2);
});

it('verlinkt Tagessumme und Gesamtsumme nicht', function () {
    storeUsage('2026-11-21 12:00:00');

    $component = usagePage();

    expect(rowsOfKind($component, 'day')[0]['url'])->toBeNull()
        ->and(rowsOfKind($component, 'total')[0]['url'])->toBeNull();
});

it('blendet Tage ohne Aufrufe aus und auf Wunsch mit 0 ein (AK-20, AK-21)', function () {
    storeUsage('2026-11-20 10:00:00', ['cost_usd' => '0.5']);
    storeUsage('2026-11-22 10:00:00', ['cost_usd' => '0.5']);

    $component = usagePage(['period' => ['from' => '2026-11-20', 'until' => '2026-11-22']]);

    expect(array_column(rowsOfKind($component, 'model'), 'day'))->toBe(['22.11.2026', '20.11.2026'])
        ->and(rowsOfKind($component, 'empty'))->toBe([]);

    $component->filterTable('show_empty_days', true);

    expect(rowsOfKind($component, 'empty'))->toHaveCount(1)
        ->and(rowsOfKind($component, 'empty')[0])->toMatchArray([
            'day' => '21.11.2026', 'calls' => '0', 'input_tokens' => '0', 'output_tokens' => '0',
            'cache_read_tokens' => '0', 'cache_creation_tokens' => '0', 'cost' => '0,0000 $', 'url' => null,
        ])
        ->and(array_column(usageRows($component), 'day'))->toBe(['22.11.2026', '22.11.2026', '21.11.2026', '20.11.2026', '20.11.2026', 'Gesamtsumme'])
        ->and(rowsOfKind($component, 'total')[0])->toMatchArray(['calls' => '2', 'cost' => '1,0000 $']);
});

it('zeigt einen Tag ohne Aufrufe des gewählten Modells als leer (AK-22)', function () {
    storeUsage('2026-11-21 10:00:00', ['model' => 'a']);
    storeUsage('2026-11-22 10:00:00', ['model' => 'b']);

    $component = usagePage([
        'period' => ['from' => '2026-11-21', 'until' => '2026-11-22'],
        'model' => ['a'],
        'show_empty_days' => true,
    ]);

    expect(array_map(fn (array $row): string => $row['kind'].' '.$row['day'], usageRows($component)))
        ->toBe(['empty 22.11.2026', 'model 21.11.2026', 'day 21.11.2026', 'total Gesamtsumme']);
});

it('begrenzt leere Tage auf ältesten passenden Aufruf und heute (AK-23, EC-3)', function () {
    storeUsage('2026-11-18 10:00:00', ['model' => 'b']);
    storeUsage('2026-11-19 10:00:00', ['model' => 'a']);
    storeUsage('2026-11-21 10:00:00', ['model' => 'a']);

    $component = usagePage([
        'period' => ['from' => null, 'until' => '2026-12-31'],
        'model' => ['a'],
        'show_empty_days' => true,
    ]);

    expect(array_values(array_unique(array_column(rowsOfKind($component, 'model'), 'day'))))->toBe(['21.11.2026', '19.11.2026'])
        ->and(array_column(rowsOfKind($component, 'empty'), 'day'))->toBe(['22.11.2026', '20.11.2026']);

    $component->filterTable('period', ['from' => null, 'until' => null]);

    expect(array_column(rowsOfKind($component, 'empty'), 'day'))->toBe(['22.11.2026', '20.11.2026']);
});

it('zeigt keine Tage, wenn „bis“ vor „von“ liegt, auch mit Option (EC-1)', function () {
    storeUsage('2026-11-21 10:00:00');

    usagePage([
        'period' => ['from' => '2026-11-22', 'until' => '2026-11-20'],
        'show_empty_days' => true,
    ])
        ->assertCountTableRecords(0)
        ->assertSee('Keine Aufrufe für diese Filter');
});

it('behält Filter und Option nach der Rückkehr (AK-24)', function () {
    storeUsage('2026-11-21 10:00:00', ['model' => 'a']);

    usagePage([
        'period' => ['from' => '2026-11-01', 'until' => '2026-11-21'],
        'model' => ['a'],
        'show_empty_days' => true,
    ]);

    $this->get(ListApiRequests::getUrl())->assertOk();

    Livewire::test(DailyUsage::class)
        ->assertSet('tableFilters.period.from', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-11-01')
        ->assertSet('tableFilters.period.until', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-11-21')
        ->assertSet('tableFilters.model.values', ['a'])
        ->assertSet('tableFilters.show_empty_days.isActive', true);
});

it('zeigt bei leerer Datenbank den Import-Hinweis, auch mit Option (AK-25)', function () {
    usagePage(['show_empty_days' => true])
        ->assertCountTableRecords(0)
        ->assertSee('Noch keine API-Aufrufe')
        ->assertSee('php artisan tokens:import')
        ->assertDontSee('Filter zurücksetzen');
});

it('zeigt bei leeren Treffern den Filter-Hinweis und setzt auf den Standard zurück (AK-26)', function () {
    storeUsage('2026-11-10 10:00:00');

    $component = usagePage(['period' => ['from' => '2026-11-01', 'until' => '2026-11-02']])
        ->assertCountTableRecords(0)
        ->assertSee('Keine Aufrufe für diese Filter')
        ->assertSee('Filter zurücksetzen')
        ->callTableAction('resetFilters')
        ->assertSet('tableFilters.period.from', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-10-24')
        ->assertSet('tableFilters.period.until', fn (?string $value): bool => substr((string) $value, 0, 10) === '2026-11-22');

    expect(array_column(rowsOfKind($component, 'model'), 'day'))->toBe(['10.11.2026']);
});

it('bietet keine Aktionen zum Anlegen, Ändern oder Löschen (AK-27)', function () {
    storeUsage('2026-11-21 10:00:00');

    $component = usagePage();
    $table = $component->instance()->getTable();

    expect($table->getRecordActions())->toBe([])
        ->and($table->getToolbarActions())->toBe([])
        ->and($table->getHeaderActions())->toBe([]);

    $component->assertDontSee(['Anlegen', 'Bearbeiten', 'Löschen', 'Create', 'Edit', 'Delete']);
});

it('leitet nicht angemeldete Besucher zur Anmeldung (AK-29)', function () {
    auth()->logout();
    storeUsage('2026-11-21 10:00:00', ['input_tokens' => 4711]);

    $this->get(DailyUsage::getUrl())->assertRedirect('/admin/login');
});

it('verweigert in production den Zugang (AK-30)', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get(DailyUsage::getUrl())->assertForbidden();
});

it('ändert beim Anzeigen keine Daten', function () {
    storeUsage('2026-11-21 10:00:00');
    $before = DB::table('api_requests')->get()->toArray();

    usagePage(['show_empty_days' => true]);

    expect(DB::table('api_requests')->get()->toArray())->toEqual($before);
});

it('zählt in der Seitenangabe Tage, nicht Zeilen (AK-7)', function () {
    for ($day = 1; $day <= 12; $day++) {
        storeUsage(sprintf('2026-11-%02d 10:00:00', $day), ['model' => 'a']);
        storeUsage(sprintf('2026-11-%02d 10:00:00', $day), ['model' => 'b']);
    }

    $records = usagePage()->instance()->getTableRecords();

    expect($records->total())->toBe(12)
        ->and($records->firstItem())->toBe(1)
        ->and($records->lastItem())->toBe(10);
});

it('zeigt einen sehr langen Modellnamen vollständig (EC-4)', function () {
    $model = 'claude-'.str_repeat('sehr-langer-modellname-', 8).'ende';
    storeUsage('2026-11-21 10:00:00', ['model' => $model]);

    usagePage()->assertSee($model);
});
