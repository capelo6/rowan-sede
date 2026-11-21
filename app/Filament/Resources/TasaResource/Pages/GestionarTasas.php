<?php

namespace App\Filament\Resources\TasaResource\Pages;

use App\Filament\Resources\TasaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarTasas extends ManageRecords
{
    protected static string $resource = TasaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
