<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requerimiento extends Model
{
    protected $fillable = ['expediente_id', 'user_id', 'motivo', 'items', 'plazo_dias', 'plazo_habiles', 'notificado_at', 'vence_at', 'atendido_at', 'estado'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'plazo_habiles' => 'boolean',
            'notificado_at' => 'datetime',
            'vence_at' => 'date',
            'atendido_at' => 'datetime',
        ];
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return list<array<string, mixed>> */
    public function itemsDocumento(): array
    {
        return array_values(array_filter($this->items ?? [], fn ($i) => ($i['tipo'] ?? 'documento') === 'documento'));
    }

    /** @return list<array<string, mixed>> */
    public function itemsDato(): array
    {
        return array_values(array_filter($this->items ?? [], fn ($i) => ($i['tipo'] ?? '') === 'dato'));
    }
}
