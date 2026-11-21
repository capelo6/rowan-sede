<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Plantilla de documento de salida. El cuerpo admite marcadores {{ clave }}:
 * expediente.numero, expediente.procedimiento, titular.nombre, titular.nif,
 * fecha, organismo.nombre, motivo, requerimiento.items, requerimiento.plazo, liquidacion.total…
 */
class Plantilla extends Model
{
    protected $fillable = ['codigo', 'nombre', 'tipo', 'asunto', 'cuerpo', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public const TIPOS = [
        'resolucion' => 'Resolución estimatoria',
        'denegacion' => 'Denegación motivada',
        'requerimiento' => 'Requerimiento de subsanación',
        'notificacion' => 'Notificación',
        'otro' => 'Otro',
    ];

    /** @param  array<string, string>  $valores */
    public function rellenar(array $valores, ?string $texto = null): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function ($m) use ($valores) {
            return array_key_exists($m[1], $valores) ? $valores[$m[1]] : $m[0];
        }, $texto ?? $this->cuerpo);
    }
}
