<?php

namespace App\Filament\Resources\FestivoResource\Pages;

use App\Filament\Resources\FestivoResource;
use App\Sede\Plazos\Calendario;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GestionarFestivos extends ManageRecords
{
    protected static string $resource = FestivoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn () => app(Calendario::class)->olvidar())];
    }
}
