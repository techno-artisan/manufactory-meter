<?php

use App\Filament\Resources\ApiRequests\ApiRequestResource;
use App\Filament\Resources\ApiRequests\Pages\ListApiRequests;
use App\Filament\Resources\ApiRequests\Pages\ViewApiRequest;
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
    $this->actingAs(User::factory()->create());
});

/**
 * Speichert einen Aufruf so, wie der Import es tut (Zeitpunkt als UTC-Text mit Millisekunden).
 *
 * @param  array<string, mixed>  $overrides
 */
function storeApiRequest(string $occurredAtUtc, array $overrides = []): ApiRequest
{
    $id = DB::table('api_requests')->insertGetId(array_merge([
        'occurred_at' => CarbonImmutable::parse($occurredAtUtc, 'UTC')->format('Y-m-d H:i:s.v'),
        'model' => 'claude-sonnet-5-5',
        'input_tokens' => 1,
        'output_tokens' => 1,
        'cache_read_tokens' => 0,
        'cache_creation_tokens' => 0,
        'cost_usd' => '0.0100000000',
        'duration_ms' => 100,
        'session_id' => null,
        'prompt_id' => null,
        'app_version' => null,
        'raw' => json_encode(['attributes' => ['event.name' => 'api_request']]),
        'fingerprint' => hash('sha256', (string) Str::uuid()),
        'created_at' => now(),
    ], $overrides));

    return ApiRequest::findOrFail($id);
}

/**
 * @param  array<string, mixed>  $period
 */
function filterPeriod(array $period): Testable
{
    return Livewire::test(ListApiRequests::class)->filterTable('period', $period);
}

it('zeigt die Liste mit Titel und allen Aufrufen (AK-1)', function () {
    $records = ApiRequest::factory()->count(3)->create();

    $this->get(ApiRequestResource::getUrl('index'))
        ->assertOk()
        ->assertSee('API-Aufrufe')
        ->assertSee(['Zeitpunkt', 'Modell', 'Input-Token', 'Output-Token', 'Cache-Lese-Token', 'Cache-Schreib-Token', 'Kosten (USD)', 'Dauer (ms)']);

    Livewire::test(ListApiRequests::class)
        ->assertCanSeeTableRecords($records)
        ->assertTableColumnVisible('occurred_at')
        ->assertTableColumnVisible('model')
        ->assertTableColumnVisible('input_tokens')
        ->assertTableColumnVisible('output_tokens')
        ->assertTableColumnVisible('cache_read_tokens')
        ->assertTableColumnVisible('cache_creation_tokens')
        ->assertTableColumnVisible('cost_usd')
        ->assertTableColumnVisible('duration_ms');
});

it('zeigt den Menüeintrag „API-Aufrufe“ (AK-1)', function () {
    $this->get('/admin')->assertOk()->assertSee('API-Aufrufe');
});

it('sortiert standardmäßig neueste oben (AK-2)', function () {
    $old = storeApiRequest('2026-11-20 10:00:00');
    $new = storeApiRequest('2026-11-22 10:00:00');
    $mid = storeApiRequest('2026-11-21 10:00:00');

    Livewire::test(ListApiRequests::class)
        ->assertCanSeeTableRecords([$new, $mid, $old], inOrder: true);
});

it('blendet Session, Prompt und Version anfangs aus und zeigt sie eingeblendet (AK-3, AK-4)', function () {
    $record = storeApiRequest('2026-11-21 10:00:00', [
        'session_id' => 'sess-4711', 'prompt_id' => 'prompt-0815', 'app_version' => '2.1.7',
    ]);

    $component = Livewire::test(ListApiRequests::class);

    foreach (['session_id', 'prompt_id', 'app_version'] as $column) {
        expect($component->instance()->isTableColumnToggledHidden($column))->toBeTrue();
    }

    $component
        ->assertDontSee('sess-4711')
        ->toggleAllTableColumns()
        ->assertTableColumnVisible('session_id')
        ->assertTableColumnStateSet('session_id', 'sess-4711', $record)
        ->assertTableColumnStateSet('prompt_id', 'prompt-0815', $record)
        ->assertTableColumnStateSet('app_version', '2.1.7', $record)
        ->assertSee(['sess-4711', 'prompt-0815', '2.1.7']);
});

it('sortiert nach jeder sortierbaren Spalte in beide Richtungen (AK-5)', function (string $column) {
    $a = storeApiRequest('2026-11-20 10:00:00', ['model' => 'a', 'input_tokens' => 1, 'output_tokens' => 1, 'cache_read_tokens' => 1, 'cache_creation_tokens' => 1, 'cost_usd' => '0.1', 'duration_ms' => 1]);
    $b = storeApiRequest('2026-11-21 10:00:00', ['model' => 'b', 'input_tokens' => 2, 'output_tokens' => 2, 'cache_read_tokens' => 2, 'cache_creation_tokens' => 2, 'cost_usd' => '0.2', 'duration_ms' => 2]);
    $c = storeApiRequest('2026-11-22 10:00:00', ['model' => 'c', 'input_tokens' => 10, 'output_tokens' => 10, 'cache_read_tokens' => 10, 'cache_creation_tokens' => 10, 'cost_usd' => '1.5', 'duration_ms' => 10]);

    Livewire::test(ListApiRequests::class)
        ->sortTable($column)
        ->assertCanSeeTableRecords([$a, $b, $c], inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords([$c, $b, $a], inOrder: true);
})->with(['occurred_at', 'model', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_creation_tokens', 'cost_usd', 'duration_ms']);

it('kehrt die Richtung beim zweiten Klick auf den Spaltenkopf um (AK-5)', function () {
    $small = storeApiRequest('2026-11-20 10:00:00', ['input_tokens' => 1]);
    $big = storeApiRequest('2026-11-21 10:00:00', ['input_tokens' => 99]);

    $component = Livewire::test(ListApiRequests::class)->call('sortTable', 'input_tokens');
    $component->assertCanSeeTableRecords([$small, $big], inOrder: true);
    $component->call('sortTable', 'input_tokens')->assertCanSeeTableRecords([$big, $small], inOrder: true);
});

it('macht Session, Prompt und Version nicht sortierbar (AK-6)', function () {
    Livewire::test(ListApiRequests::class)
        ->assertTableColumnExists('session_id', fn ($column) => ! $column->isSortable())
        ->assertTableColumnExists('prompt_id', fn ($column) => ! $column->isSortable())
        ->assertTableColumnExists('app_version', fn ($column) => ! $column->isSortable());
});

it('bietet im Modell-Filter genau die gespeicherten Modelle an (AK-7)', function () {
    foreach (['modell-a', 'modell-b', 'modell-c', 'modell-a'] as $model) {
        storeApiRequest('2026-11-21 10:00:00', ['model' => $model]);
    }

    Livewire::test(ListApiRequests::class)
        ->assertTableFilterExists('model', fn ($filter) => $filter->getOptions() === [
            'modell-a' => 'modell-a', 'modell-b' => 'modell-b', 'modell-c' => 'modell-c',
        ]);
});

it('filtert nach mehreren Modellen und zeigt ohne Auswahl alle (AK-8, AK-9)', function () {
    $a = storeApiRequest('2026-11-21 10:00:00', ['model' => 'modell-a']);
    $b = storeApiRequest('2026-11-21 11:00:00', ['model' => 'modell-b']);
    $c = storeApiRequest('2026-11-21 12:00:00', ['model' => 'modell-c']);

    Livewire::test(ListApiRequests::class)
        ->assertCanSeeTableRecords([$a, $b, $c])
        ->filterTable('model', ['modell-a', 'modell-b'])
        ->assertCanSeeTableRecords([$a, $b])
        ->assertCanNotSeeTableRecords([$c])
        ->filterTable('model', [])
        ->assertCanSeeTableRecords([$a, $b, $c]);
});

it('filtert einen ganzen deutschen Tag inklusive der Tagesgrenzen (AK-10)', function () {
    $before = storeApiRequest('2026-11-20 22:59:59.999'); // 20.11. 23:59:59 deutsche Zeit
    $start = storeApiRequest('2026-11-20 23:00:00');      // 21.11. 00:00:00
    $end = storeApiRequest('2026-11-21 22:59:59.999');    // 21.11. 23:59:59.999
    $after = storeApiRequest('2026-11-21 23:00:00');      // 22.11. 00:00:00

    filterPeriod(['from' => '2026-11-21', 'until' => '2026-11-21'])
        ->assertCanSeeTableRecords([$start, $end])
        ->assertCanNotSeeTableRecords([$before, $after]);
});

it('filtert mit nur „von“ oder nur „bis“ (AK-11, AK-12)', function () {
    $d20 = storeApiRequest('2026-11-20 10:00:00');
    $d21 = storeApiRequest('2026-11-21 10:00:00');
    $d22 = storeApiRequest('2026-11-22 10:00:00');

    filterPeriod(['from' => '2026-11-21'])
        ->assertCanSeeTableRecords([$d21, $d22])
        ->assertCanNotSeeTableRecords([$d20]);

    filterPeriod(['until' => '2026-11-21'])
        ->assertCanSeeTableRecords([$d20, $d21])
        ->assertCanNotSeeTableRecords([$d22]);
});

it('nutzt deutsche Tagesgrenzen (AK-13)', function () {
    $record = storeApiRequest('2026-11-21 23:30:00');

    filterPeriod(['from' => '2026-11-22', 'until' => '2026-11-22'])->assertCanSeeTableRecords([$record]);
    filterPeriod(['from' => '2026-11-21', 'until' => '2026-11-21'])->assertCanNotSeeTableRecords([$record]);
});

it('umfasst am Tag der Zeitumstellung den ganzen Tag (EC-4)', function () {
    $first = storeApiRequest('2026-10-24 22:00:00');  // 25.10. 00:00 MESZ
    $last = storeApiRequest('2026-10-25 22:59:59');   // 25.10. 23:59:59 MEZ
    $next = storeApiRequest('2026-10-25 23:00:00');   // 26.10. 00:00 MEZ

    filterPeriod(['from' => '2026-10-25', 'until' => '2026-10-25'])
        ->assertCanSeeTableRecords([$first, $last])
        ->assertCanNotSeeTableRecords([$next]);
});

it('kombiniert Modell- und Zeitraum-Filter (AK-14)', function () {
    $match = storeApiRequest('2026-11-21 10:00:00', ['model' => 'modell-a']);
    $wrongModel = storeApiRequest('2026-11-21 10:00:00', ['model' => 'modell-b']);
    $wrongDay = storeApiRequest('2026-11-22 10:00:00', ['model' => 'modell-a']);

    Livewire::test(ListApiRequests::class)
        ->filterTable('model', ['modell-a'])
        ->filterTable('period', ['from' => '2026-11-21', 'until' => '2026-11-21'])
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$wrongModel, $wrongDay]);
});

it('zeigt Zeitpunkte in deutscher Zeit mit Sommer- und Winterzeit (AK-15)', function () {
    storeApiRequest('2026-07-01 12:00:00');
    storeApiRequest('2026-01-15 12:00:00');

    Livewire::test(ListApiRequests::class)
        ->assertSee('01.07.2026 14:00:00')
        ->assertSee('15.01.2026 13:00:00');
});

it('zeigt Zahlen im deutschen Format (AK-16)', function () {
    storeApiRequest('2026-11-21 10:00:00', [
        'input_tokens' => 12345, 'cache_read_tokens' => 1234567, 'duration_ms' => 4321, 'cost_usd' => '0.0123456700',
    ]);

    Livewire::test(ListApiRequests::class)
        ->assertSee(['12.345', '1.234.567', '4.321', '0,0123 $']);
});

/**
 * Texte aller Zellen der Tabellenzeile, die auf den Aufruf verlinkt.
 *
 * @return list<string>
 */
function rowCellTexts(string $html, ApiRequest $record): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $url = ApiRequestResource::getUrl('view', ['record' => $record]);

    foreach ($dom->getElementsByTagName('tr') as $row) {
        if (! str_contains($dom->saveHTML($row) ?: '', $url)) {
            continue;
        }

        $texts = [];
        foreach ($row->getElementsByTagName('td') as $cell) {
            $texts[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent) ?? '');
        }

        return $texts;
    }

    return [];
}

it('zeigt fehlende Werte als Strich, nie als 0 (AK-17)', function () {
    $missing = storeApiRequest('2026-11-21 10:00:00', [
        'input_tokens' => 5, 'output_tokens' => 6, 'cache_read_tokens' => 7, 'cache_creation_tokens' => 8,
        'cost_usd' => null, 'duration_ms' => null, 'session_id' => null, 'prompt_id' => null, 'app_version' => null,
    ]);

    $html = Livewire::test(ListApiRequests::class)->toggleAllTableColumns()->html();
    $cells = rowCellTexts($html, $missing);

    expect($cells)->not->toBeEmpty()
        ->and(array_count_values($cells)['–'] ?? 0)->toBe(5)
        ->and($cells)->not->toContain('0')
        ->and($cells)->not->toContain('0,0000 $');
});

it('zeigt Kosten von 0 als 0,0000 $ (EC-8)', function () {
    $free = storeApiRequest('2026-11-20 10:00:00', ['cost_usd' => '0']);

    $cells = rowCellTexts(Livewire::test(ListApiRequests::class)->html(), $free);

    expect($cells)->toContain('0,0000 $')->and($cells)->not->toContain('–');
});

it('stellt fehlende Kosten und Dauer in beiden Richtungen ans Ende (EC-3)', function (string $column) {
    $none = storeApiRequest('2026-11-22 10:00:00', ['cost_usd' => null, 'duration_ms' => null]);
    $low = storeApiRequest('2026-11-20 10:00:00', ['cost_usd' => '0.1', 'duration_ms' => 1]);
    $high = storeApiRequest('2026-11-21 10:00:00', ['cost_usd' => '0.9', 'duration_ms' => 9]);

    Livewire::test(ListApiRequests::class)
        ->sortTable($column)
        ->assertCanSeeTableRecords([$low, $high, $none], inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords([$high, $low, $none], inOrder: true);
})->with(['cost_usd', 'duration_ms']);

it('zeigt die Detailansicht mit allen Werten und dem Original-Eintrag (AK-18, AK-19)', function () {
    $record = storeApiRequest('2026-07-01 12:00:00', [
        'model' => 'claude-opus-5-5',
        'input_tokens' => 12345,
        'cost_usd' => '0.0123456789',
        'duration_ms' => 4321,
        'session_id' => 'sess-4711',
        'prompt_id' => 'prompt-0815',
        'app_version' => '2.1.7',
        'raw' => json_encode(['attributes' => ['user.email' => 'nutzer@example.test', 'event.name' => 'api_request']]),
    ]);

    $this->get(ApiRequestResource::getUrl('view', ['record' => $record]))
        ->assertOk()
        ->assertSee('API-Aufruf')
        ->assertSee(['01.07.2026 14:00:00', 'claude-opus-5-5', '12.345', '0,0123456789 $', '4.321', 'sess-4711', 'prompt-0815', '2.1.7'])
        ->assertSee('nutzer@example.test')
        ->assertSee("{\n    &quot;attributes&quot;: {\n        &quot;user.email&quot;: &quot;nutzer@example.test&quot;", false);
});

it('verlinkt Zeilen der Liste auf die Detailansicht (AK-18)', function () {
    $record = storeApiRequest('2026-11-21 10:00:00');

    Livewire::test(ListApiRequests::class)
        ->assertSee(ApiRequestResource::getUrl('view', ['record' => $record]));
});

it('zeigt keine Aktionen zum Anlegen, Ändern oder Löschen (AK-20)', function () {
    $record = storeApiRequest('2026-11-21 10:00:00');

    Livewire::test(ListApiRequests::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    Livewire::test(ViewApiRequest::class, ['record' => $record->getKey()])
        ->assertActionDoesNotExist('edit')
        ->assertActionDoesNotExist('delete');

    expect(ApiRequestResource::hasPage('create'))->toBeFalse()
        ->and(ApiRequestResource::hasPage('edit'))->toBeFalse();
});

it('liefert für Anlege- und Ändern-Adressen eine Fehlerseite und ändert nichts (AK-21)', function () {
    $record = storeApiRequest('2026-11-21 10:00:00');
    $before = DB::table('api_requests')->get()->toArray();
    $base = ApiRequestResource::getUrl('index');

    $this->get($base.'/create')->assertNotFound();
    $this->get($base.'/'.$record->getKey().'/edit')->assertNotFound();
    $this->post($base.'/create')->assertStatus(405);

    expect(DB::table('api_requests')->get()->toArray())->toEqual($before);
});

it('zeigt 50 pro Seite, umschaltbar auf 25 und 100 (AK-22)', function () {
    ApiRequest::factory()->count(120)->create();

    $component = Livewire::test(ListApiRequests::class);
    expect($component->instance()->getTableRecords()->count())->toBe(50);
    expect($component->instance()->getTable()->getPaginationPageOptions())->toBe([25, 50, 100]);

    $component->set('tableRecordsPerPage', 25);
    expect($component->instance()->getTableRecords()->count())->toBe(25);

    $component->set('tableRecordsPerPage', 100);
    expect($component->instance()->getTableRecords()->count())->toBe(100);
});

it('zeigt jeden Aufruf beim Blättern genau einmal, auch bei gleichem Zeitpunkt (EC-2)', function () {
    for ($i = 0; $i < 60; $i++) {
        storeApiRequest('2026-11-21 10:00:00');
    }

    $component = Livewire::test(ListApiRequests::class)->set('tableRecordsPerPage', 25);
    $seen = [];
    foreach ([1, 2, 3] as $page) {
        $component->call('gotoPage', $page, 'page');
        $seen = [...$seen, ...$component->instance()->getTableRecords()->modelKeys()];
    }

    expect($seen)->toHaveCount(60)->and(array_unique($seen))->toHaveCount(60);
});

it('zeigt bei leerer Datenbank den Hinweis auf den Import (AK-24)', function () {
    Livewire::test(ListApiRequests::class)
        ->assertSee('Noch keine API-Aufrufe')
        ->assertSee('php artisan tokens:import')
        ->assertDontSee('Filter zurücksetzen');
});

it('zeigt bei leerem Filter-Ergebnis den Hinweis und setzt Filter zurück (AK-25, EC-1)', function () {
    $record = storeApiRequest('2026-11-21 10:00:00');

    Livewire::test(ListApiRequests::class)
        ->filterTable('period', ['from' => '2026-11-22', 'until' => '2026-11-20'])
        ->assertCanNotSeeTableRecords([$record])
        ->assertSee('Keine Aufrufe für diese Filter')
        ->assertSee('Filter zurücksetzen')
        ->callTableAction('resetFilters')
        ->assertCanSeeTableRecords([$record]);
});

it('behält Filter nach Rückkehr aus der Detailansicht (AK-26)', function () {
    $a = storeApiRequest('2026-11-21 10:00:00', ['model' => 'modell-a']);
    $b = storeApiRequest('2026-11-21 10:00:00', ['model' => 'modell-b']);

    Livewire::test(ListApiRequests::class)->filterTable('model', ['modell-a']);

    $this->get(ApiRequestResource::getUrl('view', ['record' => $a]))->assertOk();

    Livewire::test(ListApiRequests::class)
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$b]);
});

it('liefert für eine unbekannte ID „nicht gefunden“ (EC-7)', function () {
    $this->get(ApiRequestResource::getUrl('index').'/999999')->assertNotFound();
});

it('leitet ohne Anmeldung zur Anmeldeseite (AK-27)', function () {
    $record = storeApiRequest('2026-11-21 10:00:00', ['model' => 'geheimes-modell']);
    auth()->logout();

    $this->get(ApiRequestResource::getUrl('index'))->assertRedirect('/admin/login');
    $this->get(ApiRequestResource::getUrl('view', ['record' => $record]))->assertRedirect('/admin/login');
});

it('verweigert in production den Zugang zur Liste (AK-28)', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get(ApiRequestResource::getUrl('index'))->assertForbidden();
});
