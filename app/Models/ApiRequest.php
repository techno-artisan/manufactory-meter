<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ApiRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein gespeicherter Claude-Code-API-Aufruf (Ereignis claude_code.api_request).
 *
 * Wird nur vom Import angelegt (`tokens:import`) und danach nicht mehr geändert.
 *
 * @property int $id
 * @property CarbonImmutable $occurred_at
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property int $cache_creation_tokens
 * @property string|null $cost_usd
 * @property int|null $duration_ms
 * @property string|null $session_id
 * @property string|null $prompt_id
 * @property string|null $app_version
 * @property array<string, mixed> $raw
 * @property string $fingerprint
 * @property CarbonImmutable|null $created_at
 */
class ApiRequest extends Model
{
    /** @use HasFactory<ApiRequestFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'cache_creation_tokens' => 'integer',
            'cost_usd' => 'decimal:10',
            'duration_ms' => 'integer',
            'raw' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
