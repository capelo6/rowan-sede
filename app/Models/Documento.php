<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Documento extends Model
{
    protected $fillable = [
        'expediente_id', 'categoria', 'clave_requisito', 'nombre', 'nombre_original', 'disco', 'ruta', 'mime',
        'tamano', 'sha256', 'csv', 'origen', 'persona_id', 'user_id', 'firmado', 'metadatos',
    ];

    protected function casts(): array
    {
        return [
            'firmado' => 'boolean',
            'metadatos' => 'array',
        ];
    }

    public const CATEGORIAS = [
        'aportado' => 'Documento aportado',
        'solicitud' => 'Solicitud',
        'firma' => 'Firma electrónica',
        'justificante_registro' => 'Justificante de registro',
        'requerimiento' => 'Requerimiento',
        'resolucion' => 'Resolución',
        'notificacion' => 'Notificación',
        'justificante_notificacion' => 'Justificante de notificación',
        'justificante_pago' => 'Justificante de pago',
        'representacion' => 'Documento de representación',
        'autorizacion' => 'Autorización de terceros',
        'evidencia' => 'Evidencia',
        'otro' => 'Otro',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contenido(): string
    {
        return (string) Storage::disk($this->disco)->get($this->ruta);
    }

    public function etiquetaCategoria(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? $this->categoria;
    }

    public function tamanoLegible(): string
    {
        $kb = $this->tamano / 1024;

        return $kb > 1024
            ? number_format($kb / 1024, 1, ',', '.').' MB'
            : number_format(max($kb, 1), 0, ',', '.').' KB';
    }
}
