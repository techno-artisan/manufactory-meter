<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequests\Schemas;

use App\Models\ApiRequest;
use App\Support\GermanFormat;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApiRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Werte')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        self::entry('occurred_at', 'Zeitpunkt', fn (ApiRequest $r): string => GermanFormat::dateTime($r->occurred_at)),
                        self::entry('model', 'Modell', fn (ApiRequest $r): string => $r->model),
                        self::entry('input_tokens', 'Input-Token', fn (ApiRequest $r): string => GermanFormat::integer($r->input_tokens)),
                        self::entry('output_tokens', 'Output-Token', fn (ApiRequest $r): string => GermanFormat::integer($r->output_tokens)),
                        self::entry('cache_read_tokens', 'Cache-Lese-Token', fn (ApiRequest $r): string => GermanFormat::integer($r->cache_read_tokens)),
                        self::entry('cache_creation_tokens', 'Cache-Schreib-Token', fn (ApiRequest $r): string => GermanFormat::integer($r->cache_creation_tokens)),
                        self::entry('cost_usd', 'Kosten (USD)', fn (ApiRequest $r): string => GermanFormat::exactCost($r->cost_usd)),
                        self::entry('duration_ms', 'Dauer (ms)', fn (ApiRequest $r): string => GermanFormat::integer($r->duration_ms)),
                        self::entry('session_id', 'Session', fn (ApiRequest $r): string => GermanFormat::text($r->session_id)),
                        self::entry('prompt_id', 'Prompt', fn (ApiRequest $r): string => GermanFormat::text($r->prompt_id)),
                        self::entry('app_version', 'Claude-Code-Version', fn (ApiRequest $r): string => GermanFormat::text($r->app_version)),
                    ]),
                Section::make('Original-Eintrag')
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('raw')
                            ->hiddenLabel()
                            ->state(fn (ApiRequest $r): string => self::prettyJson($r->raw))
                            ->view('filament.api-requests.raw-entry'),
                    ]),
            ]);
    }

    /**
     * @param  \Closure(ApiRequest): string  $format
     */
    private static function entry(string $name, string $label, \Closure $format): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state($format);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function prettyJson(array $raw): string
    {
        return (string) json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
