<?php

namespace App\Support\TokenImport;

use Carbon\CarbonImmutable;
use JsonException;
use Throwable;

/**
 * Zerlegt eine Zeile des OTLP-JSON-File-Exporters und liefert die
 * claude_code.api_request-Einträge als speicherfertige Aufrufe.
 *
 * Andere Ereignisse werden still übergangen. Ein kaputter Eintrag verhindert
 * nicht das Speichern der übrigen Einträge derselben Zeile.
 */
final class OtlpLineParser
{
    private const EVENT_NAME = 'api_request';

    private const EVENT_BODY = 'claude_code.api_request';

    private const ISO_TIMESTAMP = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,9})?(Z|[+-]\d{2}:?\d{2})?$/i';

    public function parse(string $line): ParsedLine
    {
        try {
            $data = json_decode($line, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return ParsedLine::broken('ungültiges JSON ('.$e->getMessage().')');
        }

        if (! is_array($data) || array_is_list($data) || ! array_key_exists('resourceLogs', $data)) {
            return ParsedLine::broken('kein OTLP-Aufbau (resourceLogs fehlt)');
        }

        $records = $this->logRecords($data['resourceLogs']);

        if ($records === null) {
            return ParsedLine::broken('kein OTLP-Aufbau (resourceLogs, scopeLogs oder logRecords ist keine Liste)');
        }

        $rows = [];
        $errors = [];
        $count = count($records);

        foreach ($records as $index => $record) {
            $suffix = $count > 1 ? ' (Eintrag '.($index + 1).')' : '';

            if (! is_array($record) || ($record !== [] && array_is_list($record))) {
                $errors[] = 'kein gültiger Log-Eintrag'.$suffix;

                continue;
            }

            $attributes = $this->attributes($record['attributes'] ?? null);

            if (! $this->isApiRequest($record, $attributes)) {
                continue;
            }

            $result = $this->toRow($record, $attributes);

            if (is_string($result)) {
                $errors[] = $result.$suffix;
            } else {
                $rows[] = $result;
            }
        }

        return new ParsedLine($rows, $errors);
    }

    /**
     * Sammelt alle Log-Einträge einer Zeile; null, wenn der OTLP-Aufbau nicht stimmt.
     *
     * @return list<mixed>|null
     */
    private function logRecords(mixed $resourceLogs): ?array
    {
        if (! is_array($resourceLogs) || ! array_is_list($resourceLogs)) {
            return null;
        }

        $records = [];

        foreach ($resourceLogs as $resourceLog) {
            if (! is_array($resourceLog)) {
                return null;
            }

            $scopeLogs = $resourceLog['scopeLogs'] ?? [];

            if (! is_array($scopeLogs) || ! array_is_list($scopeLogs)) {
                return null;
            }

            foreach ($scopeLogs as $scopeLog) {
                if (! is_array($scopeLog)) {
                    return null;
                }

                $logRecords = $scopeLog['logRecords'] ?? [];

                if (! is_array($logRecords) || ! array_is_list($logRecords)) {
                    return null;
                }

                array_push($records, ...$logRecords);
            }
        }

        return $records;
    }

    /**
     * Wandelt die OTLP-Attributliste in Schlüssel => skalarer Wert um.
     * Attribute ohne skalaren Wert (z. B. Listen) werden ausgelassen.
     *
     * @return array<string, int|float|string|bool>
     */
    private function attributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $map = [];

        foreach ($attributes as $attribute) {
            if (! is_array($attribute) || ! is_string($attribute['key'] ?? null) || ! is_array($attribute['value'] ?? null)) {
                continue;
            }

            $value = $this->scalarValue($attribute['value']);

            if ($value !== null && ! array_key_exists($attribute['key'], $map)) {
                $map[$attribute['key']] = $value;
            }
        }

        return $map;
    }

    /**
     * @param  array<mixed>  $value  OTLP-AnyValue
     */
    private function scalarValue(array $value): int|float|string|bool|null
    {
        foreach (['stringValue', 'intValue', 'doubleValue', 'boolValue'] as $type) {
            if (array_key_exists($type, $value) && is_scalar($value[$type])) {
                return $value[$type];
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $record
     * @param  array<string, int|float|string|bool>  $attributes
     */
    private function isApiRequest(array $record, array $attributes): bool
    {
        if (($attributes['event.name'] ?? null) === self::EVENT_NAME) {
            return true;
        }

        $body = $record['body'] ?? null;

        return is_array($body) && $this->scalarValue($body) === self::EVENT_BODY;
    }

    /**
     * @param  array<mixed>  $record
     * @param  array<string, int|float|string|bool>  $attributes
     * @return array<string, int|float|string|null>|string Speicherfertige Zeile oder Grund, warum der Eintrag kaputt ist
     */
    private function toRow(array $record, array $attributes): array|string
    {
        $missing = [];
        $invalid = [];

        $occurredAt = $this->timestamp($attributes, 'event.timestamp', $missing, $invalid);
        $model = $this->requiredString($attributes, 'model', $missing);
        $inputTokens = $this->tokens($attributes, 'input_tokens', true, $missing, $invalid);
        $outputTokens = $this->tokens($attributes, 'output_tokens', true, $missing, $invalid);
        $cacheRead = $this->tokens($attributes, 'cache_read_tokens', false, $missing, $invalid);
        $cacheCreation = $this->tokens($attributes, 'cache_creation_tokens', false, $missing, $invalid);
        $cost = $this->cost($attributes, 'cost_usd', $invalid);
        $duration = $this->cost($attributes, 'duration_ms', $invalid);

        $problems = [];

        if ($missing !== []) {
            $problems[] = 'Pflichtwert fehlt: '.implode(', ', $missing);
        }

        if ($invalid !== []) {
            $problems[] = 'ungültiger Wert: '.implode(', ', $invalid);
        }

        if ($problems !== []) {
            return implode('; ', $problems);
        }

        $raw = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $canonical = json_encode($this->sortKeys($record), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        return [
            'occurred_at' => $occurredAt,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheRead ?? 0,
            'cache_creation_tokens' => $cacheCreation ?? 0,
            'cost_usd' => $cost,
            'duration_ms' => $duration === null ? null : (int) round($duration),
            'session_id' => $this->optionalString($attributes, 'session.id'),
            'prompt_id' => $this->optionalString($attributes, 'prompt.id'),
            'app_version' => $this->optionalString($attributes, 'app.version'),
            'raw' => $raw,
            'fingerprint' => hash('sha256', $canonical),
        ];
    }

    /**
     * @param  array<string, int|float|string|bool>  $attributes
     * @param  list<string>  $missing
     * @param  list<string>  $invalid
     */
    private function timestamp(array $attributes, string $key, array &$missing, array &$invalid): ?string
    {
        $value = $attributes[$key] ?? null;

        if ($value === null || $value === '') {
            $missing[] = $key;

            return null;
        }

        if (! is_string($value) || preg_match(self::ISO_TIMESTAMP, trim($value)) !== 1) {
            $invalid[] = $key;

            return null;
        }

        try {
            return CarbonImmutable::parse(trim($value), 'UTC')->utc()->format('Y-m-d H:i:s.v');
        } catch (Throwable) {
            $invalid[] = $key;

            return null;
        }
    }

    /**
     * @param  array<string, int|float|string|bool>  $attributes
     * @param  list<string>  $missing
     */
    private function requiredString(array $attributes, string $key, array &$missing): ?string
    {
        $value = $this->optionalString($attributes, $key);

        if ($value === null) {
            $missing[] = $key;
        }

        return $value;
    }

    /**
     * @param  array<string, int|float|string|bool>  $attributes
     */
    private function optionalString(array $attributes, string $key): ?string
    {
        $value = $attributes[$key] ?? null;

        if ($value === null || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Ganze Zahl ≥ 0, als Zahl oder Text. Fehlt der Wert, ist das Ergebnis null.
     *
     * @param  array<string, int|float|string|bool>  $attributes
     * @param  list<string>  $missing
     * @param  list<string>  $invalid
     */
    private function tokens(array $attributes, string $key, bool $required, array &$missing, array &$invalid): ?int
    {
        $value = $attributes[$key] ?? null;

        if ($value === null || $value === '') {
            if ($required) {
                $missing[] = $key;
            }

            return null;
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value && $value >= 0 && $value <= PHP_INT_MAX) {
            return (int) $value;
        }

        $int = is_bool($value) ? false : filter_var(is_string($value) ? trim($value) : $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($int === false) {
            $invalid[] = $key;

            return null;
        }

        return $int;
    }

    /**
     * Zahl ≥ 0 (Kosten, Dauer), als Zahl oder Text. Fehlt der Wert, bleibt er leer (null), nicht 0.
     *
     * @param  array<string, int|float|string|bool>  $attributes
     * @param  list<string>  $invalid
     */
    private function cost(array $attributes, string $key, array &$invalid): int|float|null
    {
        $value = $attributes[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (is_bool($value) || ! is_numeric($value)) {
            $invalid[] = $key;

            return null;
        }

        $number = $value + 0;

        if (! is_finite((float) $number) || $number < 0) {
            $invalid[] = $key;

            return null;
        }

        return $number;
    }

    /**
     * Sortiert die Schlüssel aller JSON-Objekte, damit der Fingerabdruck
     * nicht von der Reihenfolge der Schlüssel abhängt.
     */
    private function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn (mixed $item): mixed => $this->sortKeys($item), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
