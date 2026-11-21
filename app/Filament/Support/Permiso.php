<?php

namespace App\Filament\Support;

/** Comprobación de permisos del personal en el panel. */
class Permiso
{
    public static function tiene(string $permiso): bool
    {
        $u = auth('web')->user();

        return $u !== null && $u->can($permiso);
    }
}
