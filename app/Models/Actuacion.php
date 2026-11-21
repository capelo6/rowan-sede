<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Servicio programado de un expediente (con evidencia opcional y renovación periódica). */
class Actuacion extends Model
{
    protected $table = 'actuaciones';

    protected $fillable = ['expediente_id', 'descripcion', 'programada_para', 'realizada_at', 'realizada_por', 'evidencia_id', 'periodicidad_meses', 'proxima_renovacion'];

    protected function casts(): array
    {
        return [
            'programada_para' => 'datetime',
            'realizada_at' => 'datetime',
            'proxima_renovacion' => 'date',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function realizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'realizada_por');
    }

    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia_id');
    }
}
