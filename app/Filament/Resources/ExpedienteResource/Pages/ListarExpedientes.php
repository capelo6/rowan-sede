<?php

namespace App\Filament\Resources\ExpedienteResource\Pages;

use App\Filament\Pages\Tablero;
use App\Filament\Resources\ExpedienteResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListarExpedientes extends ListRecords
{
    protected static string $resource = ExpedienteResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('tablero')->label('Ver en tablero')->icon('heroicon-o-view-columns')->color('gray')->url(Tablero::getUrl())];
    }
}
