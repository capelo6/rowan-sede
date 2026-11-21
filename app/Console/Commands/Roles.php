<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Crea los roles y permisos del panel de gestión. */
class Roles extends Command
{
    protected $signature = 'sede:roles';

    protected $description = 'Crea o actualiza los roles y permisos del panel';

    public const PERMISOS = [
        'expedientes.ver' => 'Ver expedientes',
        'expedientes.tramitar' => 'Tramitar expedientes (revisar, requerir, asignar tasas, resolver)',
        'pagos.registrar' => 'Registrar pagos en ventanilla',
        'catalogo.gestionar' => 'Gestionar procedimientos, tasas, plazos, festivos y plantillas',
        'usuarios.gestionar' => 'Gestionar usuarios y roles',
        'auditoria.ver' => 'Ver el registro de auditoría',
        'representaciones.validar' => 'Validar representaciones',
    ];

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_keys(self::PERMISOS) as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('administracion', 'web')->syncPermissions(array_keys(self::PERMISOS));
        Role::findOrCreate('tramitacion', 'web')->syncPermissions(['expedientes.ver', 'expedientes.tramitar', 'pagos.registrar', 'representaciones.validar']);
        Role::findOrCreate('consulta', 'web')->syncPermissions(['expedientes.ver']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Roles: administracion, tramitacion, consulta.');

        return self::SUCCESS;
    }
}
