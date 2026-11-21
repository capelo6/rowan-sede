<?php

namespace App\Console\Commands;

use App\Models\Ajuste;
use App\Models\Festivo;
use App\Models\Plantilla;
use App\Models\Procedimiento;
use App\Models\Tasa;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

/** Carga un organismo ficticio («Mancomunidad de Valdeloria») con procedimientos, tasas, plantillas y personal. */
class Demo extends Command
{
    protected $signature = 'sede:demo {--clave=demo-sede-2026 : Contraseña del personal de demostración}';

    protected $description = 'Carga los datos de demostración de la sede electrónica';

    public function handle(): int
    {
        $this->call('sede:roles');

        foreach ([
            'organismo.nombre' => 'Mancomunidad de Valdeloria',
            'organismo.nif' => 'P9999999J',
            'organismo.direccion' => 'Plaza Mayor, 1 · 99999 Valdeloria',
            'organismo.email' => 'sede@valdeloria.example',
            'organismo.telefono' => '900 000 000',
        ] as $clave => $valor) {
            Ajuste::guardar($clave, $valor);
        }

        $clave = (string) $this->option('clave');
        foreach ([
            ['Administración', 'admin@valdeloria.example', 'administracion'],
            ['Laura Tramitadora', 'tramitacion@valdeloria.example', 'tramitacion'],
            ['Consulta', 'consulta@valdeloria.example', 'consulta'],
        ] as [$nombre, $email, $rol]) {
            $u = User::query()->updateOrCreate(['email' => $email], ['name' => $nombre, 'password' => $clave, 'activo' => true]);
            $u->syncRoles([$rol]);
        }

        $ejercicio = (int) now()->format('Y');
        foreach ([[$ejercicio], [$ejercicio + 1]] as [$anio]) {
            foreach ([
                ['T01', 'Tasa por expedición de certificados', 12.50, 0],
                ['T02', 'Tasa por tramitación de autorizaciones', 45.00, 0],
                ['P01', 'Precio público por servicio de mantenimiento', 60.00, 21],
            ] as [$codigo, $concepto, $importe, $iva]) {
                Tasa::query()->updateOrCreate(['codigo' => $codigo, 'ejercicio' => $anio], ['concepto' => $concepto, 'importe' => $importe, 'iva_porcentaje' => $iva, 'activa' => true]);
            }
        }

        foreach ([
            ["$ejercicio-01-01", 'Año Nuevo'], ["$ejercicio-01-06", 'Epifanía del Señor'], ["$ejercicio-05-01", 'Fiesta del Trabajo'],
            ["$ejercicio-08-15", 'Asunción de la Virgen'], ["$ejercicio-10-12", 'Fiesta Nacional de España'], ["$ejercicio-11-01", 'Todos los Santos'],
            ["$ejercicio-12-06", 'Día de la Constitución'], ["$ejercicio-12-08", 'Inmaculada Concepción'], ["$ejercicio-12-25", 'Natividad del Señor'],
        ] as [$fecha, $desc]) {
            Festivo::query()->updateOrCreate(['fecha' => $fecha, 'ambito' => 'nacional'], ['descripcion' => $desc]);
        }
        Cache::forget('sede.festivos');

        $procedimientos = [
            [
                'codigo' => 'SOLICITUD-GENERAL', 'nombre' => 'Solicitud general', 'orden' => 1, 'requiere_tasa' => false,
                'resumen' => 'Presente cualquier escrito, solicitud o comunicación que no tenga un trámite propio.',
                'informacion' => "Use este trámite para dirigirse al organismo cuando no exista un procedimiento específico.\nRecibirá el justificante de registro con el número, la fecha y la hora de entrada.",
                'definicion' => [
                    'campos' => [
                        ['clave' => 'asunto', 'etiqueta' => 'Asunto', 'tipo' => 'texto', 'obligatorio' => true, 'max' => 200],
                        ['clave' => 'expone', 'etiqueta' => 'Expone', 'tipo' => 'texto_largo', 'obligatorio' => true, 'ayuda' => 'Explique los hechos y motivos de su solicitud.'],
                        ['clave' => 'solicita', 'etiqueta' => 'Solicita', 'tipo' => 'texto_largo', 'obligatorio' => true],
                    ],
                    'documentos' => [
                        ['clave' => 'adjunto', 'nombre' => 'Documentación adjunta', 'obligatorio' => false, 'formatos' => ['pdf', 'jpg', 'png'], 'max_mb' => 10],
                    ],
                ],
            ],
            [
                'codigo' => 'CERTIFICADO', 'nombre' => 'Solicitud de certificado o duplicado', 'orden' => 2, 'requiere_tasa' => true, 'plazo_resolucion_dias' => 30,
                'resumen' => 'Pida un certificado o el duplicado de un título o documento expedido por el organismo.',
                'informacion' => 'Indique el documento que necesita. Tras revisar la solicitud le asignaremos la tasa, que podrá pagar con tarjeta o Bizum.',
                'definicion' => [
                    'campos' => [
                        ['clave' => 'tipo', 'etiqueta' => 'Documento que solicita', 'tipo' => 'opcion', 'obligatorio' => true, 'opciones' => ['Certificado', 'Duplicado de título', 'Copia de documento']],
                        ['clave' => 'referencia', 'etiqueta' => 'Referencia o número del documento original', 'tipo' => 'texto', 'obligatorio' => false],
                        ['clave' => 'motivo', 'etiqueta' => 'Motivo', 'tipo' => 'texto_largo', 'obligatorio' => true],
                    ],
                    'documentos' => [
                        ['clave' => 'identidad', 'nombre' => 'Documento de identidad', 'obligatorio' => true, 'formatos' => ['pdf', 'jpg', 'png'], 'max_mb' => 5, 'ayuda' => 'Por las dos caras.'],
                    ],
                ],
            ],
            [
                'codigo' => 'AUTORIZACION', 'nombre' => 'Solicitud de autorización de uso', 'orden' => 3, 'requiere_tasa' => true, 'plazo_resolucion_dias' => 90,
                'resumen' => 'Solicite autorización para un uso u obra en un espacio o instalación del organismo.',
                'informacion' => 'La autorización requiere la conformidad de las demás personas titulares, si las hay. Puede aportar su autorización firmada.',
                'definicion' => [
                    'campos' => [
                        ['clave' => 'ubicacion', 'etiqueta' => 'Ubicación o referencia del espacio', 'tipo' => 'texto', 'obligatorio' => true],
                        ['clave' => 'descripcion', 'etiqueta' => 'Descripción de la actuación', 'tipo' => 'texto_largo', 'obligatorio' => true],
                        ['clave' => 'fecha_prevista', 'etiqueta' => 'Fecha prevista', 'tipo' => 'fecha', 'obligatorio' => false],
                        ['clave' => 'cotitulares', 'etiqueta' => 'Hay otras personas titulares', 'tipo' => 'si_no'],
                    ],
                    'documentos' => [
                        ['clave' => 'memoria', 'nombre' => 'Memoria o croquis', 'obligatorio' => true, 'formatos' => ['pdf'], 'max_mb' => 10],
                        ['clave' => 'conformidad', 'nombre' => 'Autorización firmada de las demás personas titulares', 'obligatorio' => false, 'formatos' => ['pdf', 'jpg', 'png'], 'max_mb' => 5],
                    ],
                ],
            ],
        ];
        foreach ($procedimientos as $p) {
            Procedimiento::query()->updateOrCreate(['codigo' => $p['codigo']], $p + ['activo' => true, 'permite_representacion' => true]);
        }

        foreach ([
            ['RES-ESTIMATORIA', 'Resolución estimatoria', 'resolucion', 'Resolución de su solicitud',
                '<p>Vista la solicitud presentada el {{ expediente.fecha_presentacion }} por {{ titular.nombre }} (NIF {{ titular.nif }}), expediente {{ expediente.numero }}, relativa a «{{ expediente.procedimiento }}», y comprobado que reúne los requisitos exigidos,</p><p><strong>RESUELVO</strong> estimar la solicitud.</p><p>{{ motivo }}</p><p>Contra esta resolución puede interponer los recursos que procedan en los plazos legalmente establecidos.</p><p>{{ organismo.nombre }}, {{ fecha }}.</p>'],
            ['RES-DENEGATORIA', 'Denegación motivada', 'denegacion', 'Resolución de su solicitud',
                '<p>Vista la solicitud presentada el {{ expediente.fecha_presentacion }} por {{ titular.nombre }} (NIF {{ titular.nif }}), expediente {{ expediente.numero }}, relativa a «{{ expediente.procedimiento }}»,</p><p><strong>RESUELVO</strong> denegar la solicitud por los siguientes motivos:</p><p>{{ motivo }}</p><p>Contra esta resolución puede interponer los recursos que procedan en los plazos legalmente establecidos.</p><p>{{ organismo.nombre }}, {{ fecha }}.</p>'],
        ] as [$codigo, $nombre, $tipo, $asunto, $cuerpo]) {
            Plantilla::query()->updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'tipo' => $tipo, 'asunto' => $asunto, 'cuerpo' => $cuerpo, 'activa' => true]);
        }

        $this->info('Demo cargada: Mancomunidad de Valdeloria.');
        $this->line('Panel: /gestion · admin@valdeloria.example, tramitacion@valdeloria.example, consulta@valdeloria.example · clave '.$clave);
        $this->line('Sede: / · active SEDE_ACCESO_SIMULADO=true para entrar sin certificado.');

        return self::SUCCESS;
    }
}
