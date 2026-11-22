<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequests\Tables;

use App\Models\ApiRequest;
use App\Support\GermanFormat;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiRequestsTable
{
    /**
     * Format, in dem der Import die Zeitpunkte (UTC) speichert.
     */
    private const string STORED_FORMAT = 'Y-m-d H:i:s';

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Zeitpunkt')
                    ->formatStateUsing(fn (ApiRequest $record): string => GermanFormat::dateTime($record->occurred_at))
                    ->sortable(),
                TextColumn::make('model')
                    ->label('Modell')
                    ->sortable(),
                self::integerColumn('input_tokens', 'Input-Token'),
                self::integerColumn('output_tokens', 'Output-Token'),
                self::integerColumn('cache_read_tokens', 'Cache-Lese-Token'),
                self::integerColumn('cache_creation_tokens', 'Cache-Schreib-Token'),
                TextColumn::make('cost_usd')
                    ->label('Kosten (USD)')
                    ->alignEnd()
                    ->formatStateUsing(fn (?string $state): string => GermanFormat::cost($state))
                    ->placeholder(GermanFormat::MISSING)
                    ->sortable(query: self::nullsLast('cost_usd')),
                TextColumn::make('duration_ms')
                    ->label('Dauer (ms)')
                    ->alignEnd()
                    ->formatStateUsing(fn (?int $state): string => GermanFormat::integer($state))
                    ->placeholder(GermanFormat::MISSING)
                    ->sortable(query: self::nullsLast('duration_ms')),
                self::hiddenTextColumn('session_id', 'Session'),
                self::hiddenTextColumn('prompt_id', 'Prompt'),
                self::hiddenTextColumn('app_version', 'Claude-Code-Version'),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                SelectFilter::make('model')
                    ->label('Modell')
                    ->multiple()
                    ->options(fn (): array => ApiRequest::query()
                        ->distinct()
                        ->orderBy('model')
                        ->pluck('model', 'model')
                        ->all()),
                self::periodFilter(),
                self::sessionFilter(),
            ])
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordActions([
                ViewAction::make()->label('Ansehen'),
            ])
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
                    ->action(fn (ListRecords $livewire) => $livewire->resetTableFiltersForm()),
            ]);
    }

    private static function integerColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->alignEnd()
            ->formatStateUsing(fn (?int $state): string => GermanFormat::integer($state))
            ->sortable();
    }

    private static function hiddenTextColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->placeholder(GermanFormat::MISSING)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    /**
     * Sortiert so, dass fehlende Werte in beiden Richtungen am Ende stehen.
     *
     * @param  'cost_usd'|'duration_ms'  $column
     */
    private static function nullsLast(string $column): \Closure
    {
        $isNull = match ($column) {
            'cost_usd' => 'cost_usd is null',
            'duration_ms' => 'duration_ms is null',
        };

        return fn (Builder $query, string $direction): Builder => $query
            ->orderByRaw($isNull)
            ->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
    }

    /**
     * Zeitraum in ganzen deutschen Kalendertagen, beide Tage inklusive.
     */
    private static function periodFilter(): Filter
    {
        return Filter::make('period')
            ->schema([
                DatePicker::make('from')
                    ->label('Von')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->closeOnDateSelection(),
                DatePicker::make('until')
                    ->label('Bis')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->closeOnDateSelection(),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $from = self::berlinDayStart($data['from'] ?? null);
                $until = self::berlinDayStart($data['until'] ?? null);

                return $query
                    ->when($from, fn (Builder $query, CarbonImmutable $from): Builder => $query
                        ->where('occurred_at', '>=', $from->utc()->format(self::STORED_FORMAT)))
                    ->when($until, fn (Builder $query, CarbonImmutable $until): Builder => $query
                        ->where('occurred_at', '<', $until->addDay()->utc()->format(self::STORED_FORMAT)));
            })
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($from = self::berlinDayStart($data['from'] ?? null)) {
                    $indicators[] = Indicator::make('Von '.$from->format('d.m.Y'))->removeField('from');
                }

                if ($until = self::berlinDayStart($data['until'] ?? null)) {
                    $indicators[] = Indicator::make('Bis '.$until->format('d.m.Y'))->removeField('until');
                }

                return $indicators;
            });
    }

    /**
     * Nur Aufrufe genau einer Session (PROJ-4). Wird meist per Sprung von der Seite „Sessions“ gesetzt.
     */
    private static function sessionFilter(): Filter
    {
        return Filter::make('session')
            ->schema([
                TextInput::make('session_id')
                    ->label('Session')
                    ->maxLength(255),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when(self::sessionId($data), fn (Builder $query, string $sessionId): Builder => $query
                    ->where('session_id', $sessionId)))
            ->indicateUsing(fn (array $data): array => ($sessionId = self::sessionId($data)) !== null
                ? [Indicator::make('Session '.$sessionId)->removeField('session_id')]
                : []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function sessionId(array $data): ?string
    {
        $value = $data['session_id'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Beginn des Tages (00:00 deutsche Zeit) für ein Datum aus dem Filter.
     */
    private static function berlinDayStart(mixed $date): ?CarbonImmutable
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

    private static function hasAnyRecords(): bool
    {
        return once(fn (): bool => ApiRequest::query()->exists());
    }
}
