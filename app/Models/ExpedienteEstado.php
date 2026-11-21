<?php

namespace App\Models;

use App\Enums\EstadoExpediente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpedienteEstado extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['expediente_id', 'estado_anterior', 'estado_nuevo', 'motivo', 'actor_tipo', 'actor_id'];

    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoExpediente::class,
            'estado_nuevo' => EstadoExpediente::class,
            'created_at' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}
