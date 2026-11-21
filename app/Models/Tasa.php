<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tasa extends Model
{
    protected $fillable = ['codigo', 'ejercicio', 'concepto', 'importe', 'iva_porcentaje', 'activa'];

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'iva_porcentaje' => 'decimal:2',
            'activa' => 'boolean',
        ];
    }

    public function etiqueta(): string
    {
        return sprintf('%s · %s (%s €%s)', $this->codigo, $this->concepto, number_format((float) $this->importe, 2, ',', '.'),
            (float) $this->iva_porcentaje > 0 ? ' + IVA '.rtrim(rtrim(number_format((float) $this->iva_porcentaje, 2, ',', ''), '0'), ',').' %' : '');
    }
}
