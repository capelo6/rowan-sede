<?php

return [

    /*
    | Datos del organismo titular de la sede. Se pueden sobrescribir desde el panel (Ajustes).
    */
    'organismo' => [
        'nombre' => env('SEDE_ORGANISMO', 'Organismo de demostración'),
        'nif' => env('SEDE_ORGANISMO_NIF', 'P0000000A'),
        'direccion' => env('SEDE_ORGANISMO_DIRECCION', ''),
        'email' => env('SEDE_ORGANISMO_EMAIL', ''),
        'telefono' => env('SEDE_ORGANISMO_TELEFONO', ''),
        'web' => env('SEDE_ORGANISMO_WEB', ''),
    ],

    'acceso' => [
        // Acceso simulado (sin certificado) para desarrollo y demostraciones. Nunca en producción.
        'simulado' => (bool) env('SEDE_ACCESO_SIMULADO', false),
        // Segundos de validez del reto que firma AutoFirma.
        'reto_ttl' => 300,
        // Carpeta con los certificados raíz e intermedios de confianza (PEM o DER).
        'confianza' => env('SEDE_CONFIANZA', storage_path('app/confianza')),
        // Rechazar certificados cuyo emisor no esté en la carpeta de confianza.
        'exigir_confianza' => (bool) env('SEDE_EXIGIR_CONFIANZA', true),
        // Comprobación de revocación por OCSP.
        'ocsp' => [
            'activo' => (bool) env('SEDE_OCSP', true),
            'timeout' => 8,
            // Si el servicio OCSP no responde: false = rechazar el acceso (recomendado).
            'permitir_si_no_responde' => (bool) env('SEDE_OCSP_PERMITIR_SIN_RESPUESTA', false),
        ],
        // Ruta pública del cliente JavaScript de AutoFirma.
        'autoscript' => env('SEDE_AUTOSCRIPT', '/vendor/autofirma/autoscript.js'),
    ],

    'documentos' => [
        'disco' => env('SEDE_DISCO', 'local'),
        'formatos' => ['pdf', 'jpg', 'jpeg', 'png'],
        'max_mb' => 10,
        // Prefijo del código seguro de verificación (CSV).
        'prefijo_csv' => env('SEDE_PREFIJO_CSV', 'SEDE'),
    ],

    'expedientes' => [
        // Formato del número: {ejercicio}/{secuencia de 6 dígitos}.
        'formato_numero' => '%d/%06d',
    ],

    'plazos' => [
        'subsanacion_dias' => 10,   // hábiles (art. 68 Ley 39/2015)
        'pago_dias' => 10,          // hábiles
        'urgente_dias' => 3,        // semáforo: «Urgente» si quedan estos días o menos
    ],

    'notificaciones' => [
        'dias_rechazo' => 10,       // naturales (art. 43.2 Ley 39/2015)
    ],

    /*
    | Calendario mínimo de avisos por correo. Días contados desde el inicio del plazo
    | («mitad» = mitad del plazo; negativos = días antes del vencimiento).
    */
    'avisos' => [
        'notificacion_sin_abrir' => [0, 5, 8],
        'requerimiento' => [0, 'mitad', -2],
        'tasa' => [0, 'mitad', -2],
    ],

    'pagos' => [
        'redsys' => [
            'entorno' => env('REDSYS_ENTORNO', 'pruebas'), // pruebas | real
            'url' => [
                'pruebas' => 'https://sis-t.redsys.es:25443/sis/realizarPago',
                'real' => 'https://sis.redsys.es/sis/realizarPago',
            ],
            'fuc' => env('REDSYS_FUC', '999008881'),
            'terminal' => env('REDSYS_TERMINAL', '1'),
            // Clave de comercio en base64. La de pruebas es la pública de Redsys.
            'clave' => env('REDSYS_CLAVE', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'),
            'moneda' => '978',
            'bizum' => (bool) env('REDSYS_BIZUM', true),
            'nombre_comercio' => env('REDSYS_NOMBRE_COMERCIO', env('SEDE_ORGANISMO', 'Sede electrónica')),
        ],
    ],

    'gestion' => [
        // El personal tiene que configurar el segundo factor (TOTP) para entrar en el panel.
        'doble_factor_obligatorio' => (bool) env('SEDE_GESTION_2FA', true),
    ],

    'sello' => [
        // Certificado de sello electrónico del organismo (PKCS#12).
        'p12' => env('SEDE_SELLO_P12', storage_path('app/sello/sello.p12')),
        'clave' => env('SEDE_SELLO_CLAVE', ''),
        'motivo' => 'Sello electrónico',
    ],
];
