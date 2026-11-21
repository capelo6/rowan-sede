<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Ajustes editables desde el panel (datos del organismo, textos). */
class Ajuste extends Model
{
    protected $fillable = ['clave', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }

    public static function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        $todos = Cache::rememberForever('sede.ajustes', fn () => static::query()->pluck('valor', 'clave')->all());

        return $todos[$clave] ?? $porDefecto;
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        static::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget('sede.ajustes');
    }
}
