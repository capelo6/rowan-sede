<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $fillable = ['liquidacion_id', 'canal', 'pedido', 'importe', 'estado', 'codigo_respuesta', 'autorizacion', 'datos_pasarela', 'registrado_por', 'pagado_at', 'justificante_id'];

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'datos_pasarela' => 'array',
            'pagado_at' => 'datetime',
        ];
    }

    public const CANALES = [
        'tarjeta' => 'Tarjeta bancaria',
        'bizum' => 'Bizum',
        'ventanilla' => 'Ventanilla',
        'transferencia' => 'Transferencia',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function justificante(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'justificante_id');
    }
}
