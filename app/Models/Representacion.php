<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Representacion extends Model
{
    protected $table = 'representaciones';

    protected $fillable = ['representante_id', 'representado_id', 'ambito', 'procedimientos', 'estado', 'validada_por', 'validada_at', 'vigente_hasta', 'observaciones'];

    protected function casts(): array
    {
        return [
            'procedimientos' => 'array',
            'validada_at' => 'datetime',
            'vigente_hasta' => 'date',
        ];
    }

    public const ESTADOS = [
        'pendiente' => 'Pendiente de validar',
        'validada' => 'Validada',
        'rechazada' => 'Rechazada',
        'revocada' => 'Revocada',
    ];

    public function representante(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'representante_id');
    }

    public function representado(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'representado_id');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validada_por');
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }

    public function vigente(): bool
    {
        return $this->estado === 'validada'
            && ($this->vigente_hasta === null || ! $this->vigente_hasta->isPast() || $this->vigente_hasta->isToday());
    }
}
