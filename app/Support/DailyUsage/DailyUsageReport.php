<?php

declare(strict_types=1);

namespace App\Support\DailyUsage;

use App\Support\GermanFormat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Zählt die gespeicherten API-Aufrufe je deutschem Kalendertag und Modell zusammen (PROJ-3).
 *
 * Die Datenbank summiert je UTC-Stunde und Modell; jede Stunde wird danach genau einem
 * deutschen Kalendertag zugeordnet. Das ist auch an Tagen der Zeitumstellung korrekt,
 * weil die deutsche Zeit immer um ganze Stunden von UTC abweicht.
 *
 * Nur lesend.
 */
final class DailyUsageReport
{
    /** Format, in dem der Import die Zeitpunkte (UTC) speichert. */
    private const string STORED_FORMAT = 'Y-m-d H:i:s';

    /** @var array<string, array<string, UsageTotals>> Tag (Y-m-d) => Modell => Summen */
    private array $days = [];

    private UsageTotals $total;

    /** @var list<string> anzuzeigende Tage (Y-m-d), neueste zuerst, wenn es keinen lückenlosen Bereich gibt */
    private array $dayList = [];

    /** Bei eingeschalteter Option: lückenloser Bereich von $rangeStart bis $rangeEnd (inklusive). */
    private ?string $rangeStart = null;

    private ?string $rangeEnd = null;

    /** @var list<string> Tage mit Aufrufen nach (nur bei eingeschalteter Option) */
    private array $daysAfterRange = [];

    /**
     * @param  list<string>  $models  leere Liste = alle Modelle
     */
    public function __construct(
        ?CarbonImmutable $from,
        ?CarbonImmutable $until,
        array $models,
        bool $showEmptyDays,
        CarbonImmutable $today,
    ) {
        $this->total = new UsageTotals;

        if ($from !== null && $until !== null && $until->lt($from)) {
            return;
        }

        $this->load($from, $until, $models);

        foreach ($this->days as $models) {
            foreach ($models as $totals) {
                $this->total = $this->total->plus($totals);
            }
        }

        $dataDays = array_keys($this->days);
        rsort($dataDays, SORT_STRING);

        if (! $showEmptyDays) {
            $this->dayList = $dataDays;

            return;
        }

        $todayDate = $today->setTimezone(GermanFormat::TIMEZONE)->format('Y-m-d');
        $start = $from?->format('Y-m-d') ?? ($dataDays === [] ? null : end($dataDays));
        $end = $until?->format('Y-m-d') ?? $todayDate;
        $end = min($end, $todayDate);

        if ($start === null) {
            return;
        }

        $this->daysAfterRange = array_values(array_filter($dataDays, fn (string $day): bool => $day > $end));

        if ($start <= $end) {
            $this->rangeStart = $start;
            $this->rangeEnd = $end;
        } else {
            $this->dayList = $this->daysAfterRange;
            $this->daysAfterRange = [];
        }
    }

    /**
     * Beginn des Tages (00:00 deutsche Zeit) für ein Datum aus einem Filter, sonst null.
     */
    public static function berlinDay(mixed $date): ?CarbonImmutable
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(substr($date, 0, 10), GermanFormat::TIMEZONE)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function dayCount(): int
    {
        if ($this->rangeStart === null || $this->rangeEnd === null) {
            return count($this->dayList);
        }

        return count($this->daysAfterRange) + self::daysBetween($this->rangeStart, $this->rangeEnd) + 1;
    }

    /**
     * Die Tage einer Seite (Y-m-d), neueste zuerst. Ein Tag steht immer ganz auf einer Seite.
     *
     * @return list<string>
     */
    public function daysForPage(int $page, int $daysPerPage): array
    {
        $first = max(0, ($page - 1) * $daysPerPage);
        $last = min($this->dayCount(), $first + $daysPerPage);
        $result = [];

        for ($index = $first; $index < $last; $index++) {
            $result[] = $this->dayAt($index);
        }

        return $result;
    }

    /**
     * Modellzeilen eines Tages, alphabetisch nach Modell; leer für einen Tag ohne Aufrufe.
     *
     * @return array<string, UsageTotals>
     */
    public function modelsOn(string $day): array
    {
        $models = $this->days[$day] ?? [];
        ksort($models, SORT_STRING | SORT_FLAG_CASE);

        return $models;
    }

    public function dayTotal(string $day): UsageTotals
    {
        return array_reduce(
            $this->days[$day] ?? [],
            fn (UsageTotals $carry, UsageTotals $totals): UsageTotals => $carry->plus($totals),
            new UsageTotals,
        );
    }

    /**
     * Summe über alle passenden Tage, unabhängig von der angezeigten Seite.
     */
    public function total(): UsageTotals
    {
        return $this->total;
    }

    private function dayAt(int $index): string
    {
        if ($this->rangeStart === null || $this->rangeEnd === null) {
            return $this->dayList[$index];
        }

        $after = count($this->daysAfterRange);

        if ($index < $after) {
            return $this->daysAfterRange[$index];
        }

        return CarbonImmutable::parse($this->rangeEnd, 'UTC')->subDays($index - $after)->format('Y-m-d');
    }

    private static function daysBetween(string $start, string $end): int
    {
        return (int) CarbonImmutable::parse($start, 'UTC')->diffInDays(CarbonImmutable::parse($end, 'UTC'));
    }

    /**
     * @param  list<string>  $models
     */
    private function load(?CarbonImmutable $from, ?CarbonImmutable $until, array $models): void
    {
        $units = UsageTotals::COST_UNITS_PER_DOLLAR;

        $rows = DB::table('api_requests')
            ->selectRaw('substr(occurred_at, 1, 13) as hour')
            ->addSelect('model')
            ->selectRaw('count(*) as calls')
            ->selectRaw('sum(input_tokens) as input_tokens')
            ->selectRaw('sum(output_tokens) as output_tokens')
            ->selectRaw('sum(cache_read_tokens) as cache_read_tokens')
            ->selectRaw('sum(cache_creation_tokens) as cache_creation_tokens')
            ->selectRaw("sum(cast(round(cost_usd * {$units}) as integer)) as cost_units")
            ->selectRaw('sum(case when cost_usd is null then 1 else 0 end) as calls_without_cost')
            ->when($from, fn ($query, CarbonImmutable $from) => $query
                ->where('occurred_at', '>=', $from->utc()->format(self::STORED_FORMAT)))
            ->when($until, fn ($query, CarbonImmutable $until) => $query
                ->where('occurred_at', '<', $until->addDay()->utc()->format(self::STORED_FORMAT)))
            ->when($models !== [], fn ($query) => $query->whereIn('model', $models))
            ->groupBy('hour', 'model')
            ->get();

        /** @var array<string, string> $dayOfHour */
        $dayOfHour = [];

        foreach ($rows as $row) {
            $hour = (string) $row->hour;
            $day = $dayOfHour[$hour] ??= CarbonImmutable::createFromFormat('!Y-m-d H', $hour, 'UTC')
                ?->setTimezone(GermanFormat::TIMEZONE)
                ->format('Y-m-d') ?? substr($hour, 0, 10);
            $model = (string) $row->model;

            $totals = new UsageTotals(
                (int) $row->calls,
                (int) $row->input_tokens,
                (int) $row->output_tokens,
                (int) $row->cache_read_tokens,
                (int) $row->cache_creation_tokens,
                (int) $row->cost_units,
                (int) $row->calls_without_cost,
            );

            $this->days[$day][$model] = isset($this->days[$day][$model])
                ? $this->days[$day][$model]->plus($totals)
                : $totals;
        }
    }
}
