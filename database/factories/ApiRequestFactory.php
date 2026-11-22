<?php

namespace Database\Factories;

use App\Models\ApiRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Erfundene API-Aufrufe für Tests (E-Mail nur unter example.test, IDs zufällig).
 *
 * @extends Factory<ApiRequest>
 */
class ApiRequestFactory extends Factory
{
    protected $model = ApiRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sessionId = (string) Str::uuid();
        $promptId = (string) Str::uuid();

        return [
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now', 'UTC'),
            'model' => fake()->randomElement(['claude-sonnet-5-5', 'claude-opus-5-5', 'claude-haiku-5-5']),
            'input_tokens' => fake()->numberBetween(0, 50_000),
            'output_tokens' => fake()->numberBetween(0, 10_000),
            'cache_read_tokens' => fake()->numberBetween(0, 2_000_000),
            'cache_creation_tokens' => fake()->numberBetween(0, 100_000),
            'cost_usd' => (string) fake()->randomFloat(6, 0, 2),
            'duration_ms' => fake()->numberBetween(100, 60_000),
            'session_id' => $sessionId,
            'prompt_id' => $promptId,
            'app_version' => '2.1.'.fake()->numberBetween(0, 99),
            'raw' => [
                'attributes' => [
                    'event.name' => 'api_request',
                    'session.id' => $sessionId,
                    'prompt.id' => $promptId,
                    'user.email' => 'nutzer'.fake()->numberBetween(1, 999).'@example.test',
                    'user.account_uuid' => (string) Str::uuid(),
                    'organization.id' => (string) Str::uuid(),
                ],
            ],
            'fingerprint' => hash('sha256', (string) Str::uuid()),
        ];
    }
}
