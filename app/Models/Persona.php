<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Persona interesada que accede a la sede con certificado electrónico,
 * o entidad representada (tipo_persona = juridica).
 */
class Persona extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'tipo_persona', 'nif', 'nombre', 'apellido1', 'apellido2', 'razon_social', 'email', 'telefono',
        'direccion', 'codigo_postal', 'municipio', 'provincia', 'privacidad_aceptada_at',
        'consentimiento_notificaciones_at', 'ultimo_acceso_at', 'ultimo_certificado',
    ];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'privacidad_aceptada_at' => 'datetime',
            'consentimiento_notificaciones_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'ultimo_certificado' => 'array',
        ];
    }

    public function getAuthPassword(): string
    {
        return ''; // Sin contraseña: solo certificado electrónico.
    }

    public function nombreCompleto(): string
    {
        if ($this->tipo_persona === 'juridica') {
            return (string) $this->razon_social;
        }

        return trim(implode(' ', array_filter([$this->nombre, $this->apellido1, $this->apellido2])));
    }

    public function perfilCompleto(): bool
    {
        return $this->email && $this->privacidad_aceptada_at !== null;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    public function expedientesComoTitular(): HasMany
    {
        return $this->hasMany(Expediente::class, 'titular_id');
    }

    public function expedientesPresentados(): HasMany
    {
        return $this->hasMany(Expediente::class, 'presentador_id');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    public function representacionesComoRepresentante(): HasMany
    {
        return $this->hasMany(Representacion::class, 'representante_id');
    }

    public function representacionesComoRepresentado(): HasMany
    {
        return $this->hasMany(Representacion::class, 'representado_id');
    }
}
