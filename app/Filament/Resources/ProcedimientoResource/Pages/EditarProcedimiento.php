<?php

namespace App\Filament\Resources\ProcedimientoResource\Pages;

use App\Filament\Resources\ProcedimientoResource;
use Filament\Resources\Pages\EditRecord;

class EditarProcedimiento extends EditRecord
{
    protected static string $resource = ProcedimientoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['definicion'] = ['campos' => array_values($data['definicion']['campos'] ?? []), 'documentos' => array_values($data['definicion']['documentos'] ?? [])];

        return $data;
    }
}
