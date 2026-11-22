<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequests\Pages;

use App\Filament\Resources\ApiRequests\ApiRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListApiRequests extends ListRecords
{
    protected static string $resource = ApiRequestResource::class;

    protected ?string $heading = 'API-Aufrufe';

    public function getTitle(): string
    {
        return 'API-Aufrufe';
    }

    public function getBreadcrumb(): string
    {
        return 'Liste';
    }
}
