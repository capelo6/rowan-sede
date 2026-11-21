<?php

namespace App\Filament\Resources\ProcedimientoResource\Pages;

use App\Filament\Resources\ProcedimientoResource;
use Filament\Resources\Pages\CreateRecord;

class CrearProcedimiento extends CreateRecord
{
    protected static string $resource = ProcedimientoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['definicion'] = ['campos' => array_values($data['definicion']['campos'] ?? []), 'documentos' => array_values($data['definicion']['documentos'] ?? [])];

        return $data;
    }
}
