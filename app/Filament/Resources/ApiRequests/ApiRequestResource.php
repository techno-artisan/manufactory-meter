<?php

namespace App\Filament\Resources\ApiRequests;

use App\Filament\Resources\ApiRequests\Pages\ListApiRequests;
use App\Filament\Resources\ApiRequests\Pages\ViewApiRequest;
use App\Filament\Resources\ApiRequests\Schemas\ApiRequestInfolist;
use App\Filament\Resources\ApiRequests\Tables\ApiRequestsTable;
use App\Models\ApiRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Nur lesende Ansicht der gespeicherten API-Aufrufe (PROJ-2).
 *
 * Es gibt bewusst keine Seiten zum Anlegen oder Ändern; Adressen dafür existieren nicht.
 */
class ApiRequestResource extends Resource
{
    protected static ?string $model = ApiRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'API-Aufrufe';

    protected static ?string $modelLabel = 'API-Aufruf';

    protected static ?string $pluralModelLabel = 'API-Aufrufe';

    protected static ?string $slug = 'api-aufrufe';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApiRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiRequests::route('/'),
            'view' => ViewApiRequest::route('/{record}'),
        ];
    }
}
