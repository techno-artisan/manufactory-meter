<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\ApiRequests\ApiRequestResource;
use App\Models\ApiRequest;
use App\Support\DailyUsage\DailyUsageReport;
use App\Support\DailyUsage\DayPaginator;
use App\Support\DailyUsage\UsageTotals;
use App\Support\GermanFormat;
use App\Support\SessionUsage\SessionUsageReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Nur lesende Auswertung nach Session: Token, Aufrufe und Kosten je Claude-Code-Session (PROJ-4).
 */
class SessionUsage extends Page implements HasTable
{
    use InteractsWithTable;

    /** Standard-Zeitraum: die letzten 30 Tage inklusive heute. */
    public const int DEFAULT_DAYS = 30;

    /** @var list<int> */
    public const array PAGE_SIZES = [25, 50, 100];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Sessions';

    protected static ?string $title = 'Sessions';

    protected static ?string $slug = 'sessions';

    protected static ?int $navigationSort = 3;

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, ?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage): DayPaginator => $this->rows($filters ?? [], $sortColumn, $sortDirection, $page, $recordsPerPage))
            ->columns([
                self::textColumn('session', 'Session')->wrap(),
                self::textColumn('first_call', 'Erster Aufruf')->sortable(),
                self::textColumn('last_call', 'Letzter Aufruf')->sortable(),
                self::numberColumn('calls', 'Aufrufe'),
                self::textColumn('models', 'Modelle')->wrap(),
                self::numberColumn('input_tokens', 'Input-Token'),
                self::numberColumn('output_tokens', 'Output-Token'),
                self::numberColumn('cache_read_tokens', 'Cache-Lese-Token'),
                self::numberColumn('cache_creation_tokens', 'Cache-Schreib-Token'),
                self::numberColumn('cost', 'Kosten (USD)'),
            ])
            ->defaultSort('last_call', 'desc')
            ->persistSortInSession()
            ->filters([
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Von')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->closeOnDateSelection()
                            ->default(fn (): string => self::today()->subDays(self::DEFAULT_DAYS - 1)->format('Y-m-d')),
                        DatePicker::make('until')
                            ->label('Bis')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->closeOnDateSelection()
                            ->default(fn (): string => self::today()->format('Y-m-d')),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($from = DailyUsageReport::berlinDay($data['from'] ?? null)) {
                            $indicators[] = Indicator::make('Von '.$from->format('d.m.Y'))->removeField('from');
                        }

                        if ($until = DailyUsageReport::berlinDay($data['until'] ?? null)) {
                            $indicators[] = Indicator::make('Bis '.$until->format('d.m.Y'))->removeField('until');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('model')
                    ->label('Modell')
                    ->multiple()
                    ->options(fn (): array => ApiRequest::query()
                        ->distinct()
                        ->orderBy('model')
                        ->pluck('model', 'model')
                        ->all()),
            ])
            ->persistFiltersInSession()
            ->paginated(self::PAGE_SIZES)
            ->enforcePaginationPageOptions()
            ->defaultPaginationPageOption(50)
            ->recordUrl(fn (array $record): ?string => $record['url'] ?? null)
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading(fn (): string => self::hasAnyRecords()
                ? 'Keine Aufrufe für diese Filter'
                : 'Noch keine API-Aufrufe')
            ->emptyStateDescription(fn (): string => self::hasAnyRecords()
                ? 'Passe die Filter an oder setze sie zurück.'
                : 'Lies Aufrufe mit „php artisan tokens:import <datei>“ ein.')
            ->emptyStateActions([
                Action::make('resetFilters')
                    ->label('Filter zurücksetzen')
                    ->visible(fn (): bool => self::hasAnyRecords())
                    ->action(fn () => $this->resetTableFiltersForm()),
            ]);
    }

    /**
     * Session-Zeilen einer Seite, am Ende die Gesamtsumme über alle passenden Sessions.
     * Blättern und „Showing … of …“ zählen Sessions, nicht die Gesamtsummen-Zeile.
     *
     * @param  array<string, mixed>  $filters
     */
    private function rows(array $filters, ?string $sortColumn, ?string $sortDirection, int $page, int $perPage): DayPaginator
    {
        if (! self::hasAnyRecords()) {
            return new DayPaginator([], 0, $perPage, $page);
        }

        $period = is_array($filters['period'] ?? null) ? $filters['period'] : [];
        $modelFilter = is_array($filters['model'] ?? null) ? $filters['model'] : [];
        $models = array_values(array_map(strval(...), array_filter(
            is_array($modelFilter['values'] ?? null) ? $modelFilter['values'] : [],
            is_scalar(...),
        )));
        $from = DailyUsageReport::berlinDay($period['from'] ?? null);
        $until = DailyUsageReport::berlinDay($period['until'] ?? null);

        $report = new SessionUsageReport($from, $until, $models);
        $sessionCount = $report->sessionCount();

        if ($sessionCount === 0) {
            return new DayPaginator([], 0, $perPage, $page);
        }

        $linkFilters = [
            'period' => ['from' => $from?->format('Y-m-d') ?? '', 'until' => $until?->format('Y-m-d') ?? ''],
            'model' => ['values' => $models],
        ];

        $rows = [];

        foreach ($report->page($sortColumn, $sortDirection, $page, $perPage) as $index => $session) {
            $rows['session-'.$index] = self::row(
                'session',
                $session->sessionId,
                GermanFormat::dateTime($session->firstCall),
                GermanFormat::dateTime($session->lastCall),
                implode(', ', $session->models),
                $session->totals,
                ApiRequestResource::getUrl('index', [
                    'filters' => ['session' => ['session_id' => $session->sessionId]] + $linkFilters,
                ]),
            );
        }

        $rows['total'] = self::row(
            'total',
            'Gesamtsumme ('.GermanFormat::integer($sessionCount).($sessionCount === 1 ? ' Session)' : ' Sessions)'),
            '',
            '',
            '',
            $report->total(),
        );

        return new DayPaginator($rows, $sessionCount, $perPage, $page);
    }

    /**
     * @param  'session'|'total'  $kind
     * @return array<string, mixed>
     */
    private static function row(string $kind, string $session, string $firstCall, string $lastCall, string $models, UsageTotals $totals, ?string $url = null): array
    {
        return [
            'kind' => $kind,
            'session' => $session,
            'first_call' => $firstCall,
            'last_call' => $lastCall,
            'models' => $models,
            'calls' => GermanFormat::integer($totals->calls),
            'input_tokens' => GermanFormat::integer($totals->inputTokens),
            'output_tokens' => GermanFormat::integer($totals->outputTokens),
            'cache_read_tokens' => GermanFormat::integer($totals->cacheReadTokens),
            'cache_creation_tokens' => GermanFormat::integer($totals->cacheCreationTokens),
            'cost' => GermanFormat::costSum($totals->costUnits, $totals->calls, $totals->callsWithoutCost),
            'url' => $url,
        ];
    }

    private static function textColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->weight(fn (array $record): ?FontWeight => ($record['kind'] ?? null) === 'total' ? FontWeight::Bold : null);
    }

    private static function numberColumn(string $name, string $label): TextColumn
    {
        return self::textColumn($name, $label)->alignEnd()->sortable();
    }

    private static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(GermanFormat::TIMEZONE)->startOfDay();
    }

    private static function hasAnyRecords(): bool
    {
        return once(fn (): bool => ApiRequest::query()->exists());
    }
}
