<?php

namespace App\Filament\Resources\ApiRequests\Pages;

use App\Filament\Resources\ApiRequests\ApiRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewApiRequest extends ViewRecord
{
    protected static string $resource = ApiRequestResource::class;

    public function getTitle(): string
    {
        return 'API-Aufruf';
    }

    public function getBreadcrumb(): string
    {
        return 'API-Aufruf';
    }
}
