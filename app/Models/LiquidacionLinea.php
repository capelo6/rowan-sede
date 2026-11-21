<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionLinea extends Model
{
    protected $fillable = ['liquidacion_id', 'tasa_id', 'concepto', 'cantidad', 'importe_unitario', 'iva_porcentaje', 'motivo', 'base', 'iva', 'total'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'importe_unitario' => 'decimal:2',
            'iva_porcentaje' => 'decimal:2',
            'base' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function tasa(): BelongsTo
    {
        return $this->belongsTo(Tasa::class);
    }

    /** Calcula base, IVA y total a partir de cantidad, precio unitario y porcentaje. */
    public static function calcular(float $cantidad, float $unitario, float $ivaPorcentaje): array
    {
        $base = round($cantidad * $unitario, 2);
        $iva = round($base * $ivaPorcentaje / 100, 2);

        return ['base' => $base, 'iva' => $iva, 'total' => round($base + $iva, 2)];
    }
}
