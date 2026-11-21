<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registro extends Model
{
    protected $fillable = ['tipo', 'ejercicio', 'numero', 'registrado_at', 'expediente_id', 'persona_id', 'asunto', 'resumen', 'justificante_id'];

    protected function casts(): array
    {
        return ['registrado_at' => 'datetime'];
    }

    /** Número legible: E-2026-000123 o S-2026-000045. */
    public function codigo(): string
    {
        return sprintf('%s-%d-%06d', $this->tipo === 'entrada' ? 'E' : 'S', $this->ejercicio, $this->numero);
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function justificante(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'justificante_id');
    }
}
