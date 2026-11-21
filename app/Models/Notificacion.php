<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = ['expediente_id', 'persona_id', 'asunto', 'cuerpo', 'documento_id', 'puesta_disposicion_at', 'accedida_at', 'rechazada_at', 'estado', 'justificante_id'];

    protected function casts(): array
    {
        return [
            'puesta_disposicion_at' => 'datetime',
            'accedida_at' => 'datetime',
            'rechazada_at' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function justificante(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'justificante_id');
    }

    /** Fecha a partir de la cual se entiende rechazada (10 días naturales, art. 43.2 Ley 39/2015). */
    public function venceAt(): \Illuminate\Support\Carbon
    {
        return $this->puesta_disposicion_at->copy()->addDays((int) config('sede.notificaciones.dias_rechazo', 10));
    }
}
