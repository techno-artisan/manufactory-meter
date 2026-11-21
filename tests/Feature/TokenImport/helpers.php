<?php

/**
 * Erfundene Testdaten im Format des OTLP-JSON-File-Exporters (Claude-Code-Ereignisse).
 */
function otlpAttribute(string $key, mixed $value): array
{
    $type = match (true) {
        is_int($value) => 'intValue',
        is_float($value) => 'doubleValue',
        is_bool($value) => 'boolValue',
        default => 'stringValue',
    };

    return ['key' => $key, 'value' => [$type => $value]];
}

/**
 * @param  array<string, mixed>  $overrides  null entfernt ein Attribut
 */
function apiRequestRecord(array $overrides = []): array
{
    $attributes = array_merge([
        'event.name' => 'api_request',
        'event.timestamp' => '2026-11-21T10:15:30.250Z',
        'event.sequence' => 7,
        'session.id' => 'f3b1c2d4-5e6f-4a7b-8c9d-0e1f2a3b4c5d',
        'prompt.id' => 'a1b2c3d4-e5f6-4789-abcd-ef0123456789',
        'app.version' => '2.1.0',
        'user.email' => 'entwickler@example.test',
        'user.account_uuid' => '9d8c7b6a-5f4e-4d3c-b2a1-0f9e8d7c6b5a',
        'organization.id' => '1a2b3c4d-5e6f-4071-8293-a4b5c6d7e8f9',
        'model' => 'claude-sonnet-5-5',
        'input_tokens' => '1200',
        'output_tokens' => '340',
        'cache_read_tokens' => '5000',
        'cache_creation_tokens' => '800',
        'cost_usd' => '0.0421',
        'duration_ms' => '2875',
    ], $overrides);

    return [
        'timeUnixNano' => '1763720130250000000',
        'severityNumber' => 9,
        'body' => ['stringValue' => 'claude_code.api_request'],
        'attributes' => array_values(array_map(
            fn (string $key, mixed $value) => otlpAttribute($key, $value),
            array_keys(array_filter($attributes, fn ($v) => $v !== null)),
            array_filter($attributes, fn ($v) => $v !== null),
        )),
    ];
}

function otherEventRecord(string $name = 'user_prompt'): array
{
    return [
        'timeUnixNano' => '1763720130000000000',
        'body' => ['stringValue' => "claude_code.{$name}"],
        'attributes' => [
            otlpAttribute('event.name', $name),
            otlpAttribute('event.timestamp', '2026-11-21T10:15:30.000Z'),
            otlpAttribute('session.id', 'f3b1c2d4-5e6f-4a7b-8c9d-0e1f2a3b4c5d'),
        ],
    ];
}

/**
 * Eine Zeile des File-Exporters mit den angegebenen Log-Einträgen.
 */
function otlpLine(array ...$records): string
{
    return json_encode([
        'resourceLogs' => [[
            'resource' => ['attributes' => [otlpAttribute('service.name', 'claude-code')]],
            'scopeLogs' => [[
                'scope' => ['name' => 'com.anthropic.claude_code.events', 'version' => '2.1.0'],
                'logRecords' => $records,
            ]],
        ]],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function otlpFile(string ...$lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'otlp');
    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}
