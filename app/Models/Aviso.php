<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de cada aviso por correo enviado. La clave evita enviar dos veces el mismo. */
class Aviso extends Model
{
    protected $fillable = ['clave', 'persona_id', 'expediente_id', 'tipo', 'asunto', 'enviado_at', 'error'];

    protected function casts(): array
    {
        return ['enviado_at' => 'datetime'];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}
