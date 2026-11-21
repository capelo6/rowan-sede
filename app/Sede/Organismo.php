<?php

namespace App\Sede;

use App\Models\Ajuste;

/** Datos del organismo titular de la sede: primero los Ajustes del panel y, si no, la configuración. */
class Organismo
{
    public static function dato(string $clave): string
    {
        return (string) (Ajuste::obtener('organismo.'.$clave) ?? config('sede.organismo.'.$clave, ''));
    }

    public static function nombre(): string
    {
        return self::dato('nombre');
    }

    public static function nif(): string
    {
        return self::dato('nif');
    }

    public static function direccion(): string
    {
        return self::dato('direccion');
    }

    public static function email(): string
    {
        return self::dato('email');
    }

    public static function telefono(): string
    {
        return self::dato('telefono');
    }
}
