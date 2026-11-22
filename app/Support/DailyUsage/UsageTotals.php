<?php

declare(strict_types=1);

namespace App\Support\DailyUsage;

/**
 * Summen einer Zeile der Tagesübersicht (Modellzeile, Tagessumme oder Gesamtsumme).
 *
 * Kosten werden als ganze Zahl in Zehnmilliardstel-Dollar geführt, damit beim
 * Aufsummieren keine Rundungsfehler entstehen.
 */
final readonly class UsageTotals
{
    /** Kostenwerte sind ganze Zahlen in dieser Einheit (10 Nachkommastellen). */
    public const int COST_UNITS_PER_DOLLAR = 10_000_000_000;

    public function __construct(
        public int $calls = 0,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheCreationTokens = 0,
        public int $costUnits = 0,
        public int $callsWithoutCost = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self(
            $this->calls + $other->calls,
            $this->inputTokens + $other->inputTokens,
            $this->outputTokens + $other->outputTokens,
            $this->cacheReadTokens + $other->cacheReadTokens,
            $this->cacheCreationTokens + $other->cacheCreationTokens,
            $this->costUnits + $other->costUnits,
            $this->callsWithoutCost + $other->callsWithoutCost,
        );
    }
}
