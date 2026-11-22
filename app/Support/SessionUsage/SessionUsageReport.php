<?php

declare(strict_types=1);

namespace App\Support\SessionUsage;

use App\Support\DailyUsage\UsageTotals;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Zählt die gespeicherten API-Aufrufe je Session zusammen (PROJ-4).
 *
 * Gezählt werden nur passende Aufrufe (Zeitraum, Modelle) mit gespeicherter Session-ID.
 * Zusammenzählen, Sortieren und Blättern übernimmt die Datenbank; geladen werden nur die
 * Sessions der angezeigten Seite. Nur lesend.
 */
final class SessionUsageReport
{
    /** Format, in dem der Import die Zeitpunkte (UTC) speichert. */
    private const string STORED_FORMAT = 'Y-m-d H:i:s';

    /** Sortierbare Spalten der Seite. */
    public const array SORTABLE = [
        'first_call',
        'last_call',
        'calls',
        'input_tokens',
        'output_tokens',
        'cache_read_tokens',
        'cache_creation_tokens',
        'cost',
    ];

    private readonly bool $impossible;

    private ?UsageTotals $total = null;

    private int $sessionCount = 0;

    /**
     * @param  list<string>  $models  leere Liste = alle Modelle
     */
    public function __construct(
        private readonly ?CarbonImmutable $from,
        private readonly ?CarbonImmutable $until,
        private readonly array $models,
    ) {
        $this->impossible = $from !== null && $until !== null && $until->lt($from);
    }

    /**
     * Summe über alle passenden Sessions, unabhängig von der angezeigten Seite.
     */
    public function total(): UsageTotals
    {
        $this->loadTotal();

        return $this->total ?? new UsageTotals;
    }

    public function sessionCount(): int
    {
        $this->loadTotal();

        return $this->sessionCount;
    }

    /**
     * Die Sessions einer Seite in der gewünschten Sortierung.
     *
     * @return list<SessionRow>
     */
    public function page(?string $sortColumn, ?string $sortDirection, int $page, int $perPage): array
    {
        if ($this->impossible) {
            return [];
        }

        $column = in_array($sortColumn, self::SORTABLE, true) ? $sortColumn : 'last_call';
        $direction = $sortColumn === null ? 'desc' : ($sortDirection === 'asc' ? 'asc' : 'desc');
        $units = UsageTotals::COST_UNITS_PER_DOLLAR;

        $query = $this->filtered()
            ->select('session_id')
            ->selectRaw('count(*) as calls')
            ->selectRaw('sum(input_tokens) as input_tokens')
            ->selectRaw('sum(output_tokens) as output_tokens')
            ->selectRaw('sum(cache_read_tokens) as cache_read_tokens')
            ->selectRaw('sum(cache_creation_tokens) as cache_creation_tokens')
            ->selectRaw("coalesce(sum(cast(round(cost_usd * {$units}) as integer)), 0) as cost")
            ->selectRaw('count(*) - count(cost_usd) as calls_without_cost')
            ->selectRaw('min(occurred_at) as first_call')
            ->selectRaw('max(occurred_at) as last_call')
            ->groupBy('session_id');

        if ($column === 'cost') {
            // Sessions ganz ohne Kosten stehen in beiden Richtungen am Ende.
            $query->orderByRaw('count(cost_usd) = 0');
        }

        $rows = $query
            ->orderBy($column, $direction)
            ->orderBy('session_id')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get();

        $modelsBySession = $this->modelsOf(array_values($rows->pluck('session_id')->map(strval(...))->all()));

        return array_values($rows->map(fn (object $row): SessionRow => new SessionRow(
            sessionId: (string) $row->session_id,
            firstCall: CarbonImmutable::parse((string) $row->first_call, 'UTC'),
            lastCall: CarbonImmutable::parse((string) $row->last_call, 'UTC'),
            models: $modelsBySession[(string) $row->session_id] ?? [],
            totals: new UsageTotals(
                (int) $row->calls,
                (int) $row->input_tokens,
                (int) $row->output_tokens,
                (int) $row->cache_read_tokens,
                (int) $row->cache_creation_tokens,
                (int) $row->cost,
                (int) $row->calls_without_cost,
            ),
        ))->all());
    }

    private function loadTotal(): void
    {
        if ($this->total !== null) {
            return;
        }

        $this->total = new UsageTotals;

        if ($this->impossible) {
            return;
        }

        $units = UsageTotals::COST_UNITS_PER_DOLLAR;

        $row = $this->filtered()
            ->selectRaw('count(distinct session_id) as sessions')
            ->selectRaw('count(*) as calls')
            ->selectRaw('sum(input_tokens) as input_tokens')
            ->selectRaw('sum(output_tokens) as output_tokens')
            ->selectRaw('sum(cache_read_tokens) as cache_read_tokens')
            ->selectRaw('sum(cache_creation_tokens) as cache_creation_tokens')
            ->selectRaw("sum(cast(round(cost_usd * {$units}) as integer)) as cost")
            ->selectRaw('count(*) - count(cost_usd) as calls_without_cost')
            ->first();

        if ($row === null) {
            return;
        }

        $this->sessionCount = (int) $row->sessions;
        $this->total = new UsageTotals(
            (int) $row->calls,
            (int) $row->input_tokens,
            (int) $row->output_tokens,
            (int) $row->cache_read_tokens,
            (int) $row->cache_creation_tokens,
            (int) $row->cost,
            (int) $row->calls_without_cost,
        );
    }

    /**
     * Modelle der passenden Aufrufe je Session, jedes einmal, alphabetisch.
     *
     * @param  list<string>  $sessionIds
     * @return array<string, list<string>>
     */
    private function modelsOf(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $result = [];

        foreach (array_chunk($sessionIds, 500) as $chunk) {
            $pairs = $this->filtered()
                ->whereIn('session_id', $chunk)
                ->distinct()
                ->get(['session_id', 'model']);

            foreach ($pairs as $pair) {
                $result[(string) $pair->session_id][] = (string) $pair->model;
            }
        }

        foreach ($result as &$models) {
            sort($models, SORT_STRING | SORT_FLAG_CASE);
        }

        return $result;
    }

    /**
     * Aufrufe mit Session-ID, die zu Zeitraum und Modellen passen. Gleiche Zeitraum-Regel wie
     * in der Liste „API-Aufrufe“ und der Tagesübersicht.
     */
    private function filtered(): Builder
    {
        return DB::table('api_requests')
            ->whereNotNull('session_id')
            ->where('session_id', '!=', '')
            ->when($this->from, fn (Builder $query, CarbonImmutable $from): Builder => $query
                ->where('occurred_at', '>=', $from->utc()->format(self::STORED_FORMAT)))
            ->when($this->until, fn (Builder $query, CarbonImmutable $until): Builder => $query
                ->where('occurred_at', '<', $until->addDay()->utc()->format(self::STORED_FORMAT)))
            ->when($this->models !== [], fn (Builder $query): Builder => $query->whereIn('model', $this->models));
    }
}
