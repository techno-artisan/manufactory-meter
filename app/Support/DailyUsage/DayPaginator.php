<?php

declare(strict_types=1);

namespace App\Support\DailyUsage;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Blättert nach ganzen Tagen: Gesamtzahl, Seitengröße und „von … bis …“ zählen Tage,
 * auch wenn ein Tag mehrere Zeilen (Modellzeilen, Tagessumme) hat.
 *
 * @extends LengthAwarePaginator<string, array<string, mixed>>
 */
final class DayPaginator extends LengthAwarePaginator
{
    public function firstItem(): ?int
    {
        return $this->total() > 0 ? ($this->currentPage() - 1) * $this->perPage() + 1 : null;
    }

    public function lastItem(): ?int
    {
        $first = $this->firstItem();

        return $first === null ? null : min($first + $this->perPage() - 1, $this->total());
    }
}
