<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liquidacion extends Model
{
    protected $table = 'liquidaciones';

    protected $fillable = ['expediente_id', 'referencia', 'estado', 'base', 'iva', 'total', 'vence_at', 'emitida_por', 'pagada_at'];

    protected function casts(): array
    {
        return [
            'base' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
            'vence_at' => 'date',
            'pagada_at' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(LiquidacionLinea::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitida_por');
    }

    public function recalcular(): void
    {
        $this->base = round((float) $this->lineas()->sum('base'), 2);
        $this->iva = round((float) $this->lineas()->sum('iva'), 2);
        $this->total = round((float) $this->lineas()->sum('total'), 2);
        $this->save();
    }

    /** Importe en céntimos, como lo pide la pasarela. */
    public function totalCentimos(): int
    {
        return (int) round(((float) $this->total) * 100);
    }
}
