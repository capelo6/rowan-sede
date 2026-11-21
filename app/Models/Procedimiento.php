<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Procedimiento del catálogo. La definición describe el formulario:
 *  campos:     [{clave, etiqueta, tipo, obligatorio, ayuda, opciones, max}]
 *  documentos: [{clave, nombre, obligatorio, formatos: [pdf,jpg], max_mb, ayuda}]
 */
class Procedimiento extends Model
{
    protected $fillable = [
        'codigo', 'nombre', 'resumen', 'informacion', 'definicion', 'permite_representacion',
        'requiere_tasa', 'plazo_resolucion_dias', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'definicion' => 'array',
            'permite_representacion' => 'boolean',
            'requiere_tasa' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function campos(): array
    {
        return array_values($this->definicion['campos'] ?? []);
    }

    /** @return list<array<string, mixed>> */
    public function documentosRequeridos(): array
    {
        return array_values($this->definicion['documentos'] ?? []);
    }

    public function documentoRequerido(string $clave): ?array
    {
        foreach ($this->documentosRequeridos() as $doc) {
            if (($doc['clave'] ?? null) === $clave) {
                return $doc;
            }
        }

        return null;
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }
}
