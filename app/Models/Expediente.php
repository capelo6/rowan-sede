<?php

namespace App\Models;

use App\Enums\EstadoExpediente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Expediente extends Model
{
    use LogsActivity;

    protected $fillable = [
        'numero', 'procedimiento_id', 'titular_id', 'presentador_id', 'representacion_id', 'estado', 'resultado',
        'datos', 'asignado_a', 'presentado_at', 'resuelto_at', 'estado_cambiado_at', 'desistimiento_propuesto_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoExpediente::class,
            'datos' => 'array',
            'presentado_at' => 'datetime',
            'resuelto_at' => 'datetime',
            'estado_cambiado_at' => 'datetime',
            'desistimiento_propuesto_at' => 'datetime',
        ];
    }

    public const RESULTADOS = [
        'aprobada' => 'Aprobada',
        'denegada' => 'Denegada',
        'retirada' => 'Retirada por la persona interesada',
        'anulada' => 'Anulada',
        'desistida' => 'Desistida',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['estado', 'resultado', 'asignado_a', 'numero'])
            ->logOnlyDirty()
            ->useLogName('expedientes');
    }

    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(Procedimiento::class);
    }

    public function titular(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'titular_id');
    }

    public function presentador(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'presentador_id');
    }

    public function representacion(): BelongsTo
    {
        return $this->belongsTo(Representacion::class);
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ExpedienteEstado::class)->orderBy('created_at')->orderBy('id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function requerimientos(): HasMany
    {
        return $this->hasMany(Requerimiento::class);
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function actuaciones(): HasMany
    {
        return $this->hasMany(Actuacion::class);
    }

    public function requerimientoPendiente(): ?Requerimiento
    {
        return $this->requerimientos()->where('estado', 'pendiente')->latest('id')->first();
    }

    public function liquidacionPendiente(): ?Liquidacion
    {
        return $this->liquidaciones()->where('estado', 'pendiente')->latest('id')->first();
    }

    /** Lo ven el titular y quien lo presentó (la persona representante). */
    public function esAccesiblePara(Persona $persona): bool
    {
        return $this->titular_id === $persona->id || $this->presentador_id === $persona->id;
    }

    public function referencia(): string
    {
        return $this->numero ?? 'Borrador '.$this->id;
    }
}
