<?php

declare(strict_types=1);

namespace App\Support\SessionUsage;

use App\Support\DailyUsage\UsageTotals;
use Carbon\CarbonImmutable;

/**
 * Eine Session-Zeile: Summen aus den passenden Aufrufen einer Session (PROJ-4).
 */
final readonly class SessionRow
{
    /**
     * @param  list<string>  $models  alphabetisch, jedes Modell einmal
     */
    public function __construct(
        public string $sessionId,
        public CarbonImmutable $firstCall,
        public CarbonImmutable $lastCall,
        public array $models,
        public UsageTotals $totals,
    ) {}
}
