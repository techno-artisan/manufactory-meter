<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\ApiRequests\ApiRequestResource;
use App\Models\ApiRequest;
use App\Support\DailyUsage\DailyUsageReport;
use App\Support\DailyUsage\DayPaginator;
use App\Support\DailyUsage\UsageTotals;
use App\Support\GermanFormat;
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
 * Nur lesende Tagesübersicht: Token, Aufrufe und Kosten je deutschem Kalendertag und Modell (PROJ-3).
 */
class DailyUsage extends Page implements HasTable
{
    use InteractsWithTable;

    /** Standard-Zeitraum: die letzten 30 Tage inklusive heute. */
    public const int DEFAULT_DAYS = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Tagesübersicht';

    protected static ?string $title = 'Tagesübersicht';

    protected static ?string $slug = 'tagesuebersicht';

    protected static ?int $navigationSort = 2;

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters, int $page, int $recordsPerPage): DayPaginator => $this->rows($filters ?? [], $page, $recordsPerPage))
            ->columns([
                self::textColumn('day', 'Tag'),
                self::textColumn('model', 'Modell'),
                self::numberColumn('calls', 'Aufrufe'),
                self::numberColumn('input_tokens', 'Input-Token'),
                self::numberColumn('output_tokens', 'Output-Token'),
                self::numberColumn('cache_read_tokens', 'Cache-Lese-Token'),
                self::numberColumn('cache_creation_tokens', 'Cache-Schreib-Token'),
                self::numberColumn('cost', 'Kosten (USD)'),
            ])
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
                Filter::make('show_empty_days')
                    ->label('Tage ohne Aufrufe zeigen')
                    ->toggle(),
            ])
            ->persistFiltersInSession()
            ->paginated([10, 31, 100])
            ->defaultPaginationPageOption(10)
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
     * Zeilen einer Seite: je Tag die Modellzeilen und die Tagessumme (oder eine Zeile ohne
     * Aufrufe), am Ende die Gesamtsumme über alle passenden Tage. Blättern zählt ganze Tage.
     *
     * @param  array<string, mixed>  $filters
     */
    private function rows(array $filters, int $page, int $daysPerPage): DayPaginator
    {
        $report = self::hasAnyRecords() ? self::report($filters) : null;

        if ($report === null || $report->dayCount() === 0) {
            return new DayPaginator([], 0, $daysPerPage, $page);
        }

        $rows = [];

        foreach ($report->daysForPage($page, $daysPerPage) as $day) {
            $label = CarbonImmutable::parse($day, 'UTC')->format('d.m.Y');
            $models = $report->modelsOn($day);

            if ($models === []) {
                $rows["{$day}:empty"] = self::row('empty', $label, GermanFormat::MISSING, new UsageTotals);

                continue;
            }

            $index = 0;

            foreach ($models as $model => $totals) {
                $rows["{$day}:".$index++] = self::row('model', $label, (string) $model, $totals, ApiRequestResource::getUrl('index', [
                    'filters' => [
                        'model' => ['values' => [(string) $model]],
                        'period' => ['from' => $day, 'until' => $day],
                    ],
                ]));
            }

            $rows["{$day}:sum"] = self::row('day', $label, 'Tagessumme', $report->dayTotal($day));
        }

        $rows['total'] = self::row('total', 'Gesamtsumme', '', $report->total());

        return new DayPaginator($rows, $report->dayCount(), $daysPerPage, $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function report(array $filters): DailyUsageReport
    {
        $period = is_array($filters['period'] ?? null) ? $filters['period'] : [];
        $modelFilter = is_array($filters['model'] ?? null) ? $filters['model'] : [];
        $models = is_array($modelFilter['values'] ?? null) ? $modelFilter['values'] : [];
        $emptyDays = is_array($filters['show_empty_days'] ?? null) ? $filters['show_empty_days'] : [];

        return new DailyUsageReport(
            from: DailyUsageReport::berlinDay($period['from'] ?? null),
            until: DailyUsageReport::berlinDay($period['until'] ?? null),
            models: array_values(array_map(strval(...), array_filter($models, is_scalar(...)))),
            showEmptyDays: (bool) ($emptyDays['isActive'] ?? false),
            today: self::today(),
        );
    }

    /**
     * @param  'model'|'day'|'empty'|'total'  $kind
     * @return array<string, mixed>
     */
    private static function row(string $kind, string $day, string $model, UsageTotals $totals, ?string $url = null): array
    {
        return [
            'kind' => $kind,
            'day' => $day,
            'model' => $model,
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
            ->weight(fn (array $record): ?FontWeight => self::isSum($record) ? FontWeight::Bold : null);
    }

    private static function numberColumn(string $name, string $label): TextColumn
    {
        return self::textColumn($name, $label)->alignEnd();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private static function isSum(array $record): bool
    {
        return in_array($record['kind'] ?? null, ['day', 'total'], true);
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
