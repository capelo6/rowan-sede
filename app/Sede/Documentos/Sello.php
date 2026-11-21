<?php

namespace App\Sede\Documentos;

use RuntimeException;

/**
 * Certificado de sello electrónico del organismo (PKCS#12). Se usa para firmar en PAdES
 * los documentos de salida: justificantes, requerimientos, resoluciones y notificaciones.
 */
class Sello
{
    /** @var array{cert: string, pkey: string, extracerts: string, nombre: string}|null */
    private ?array $credenciales = null;

    public function disponible(): bool
    {
        $ruta = (string) config('sede.sello.p12');

        return $ruta !== '' && is_file($ruta);
    }

    /** @return array{cert: string, pkey: string, extracerts: string, nombre: string} */
    public function credenciales(): array
    {
        if ($this->credenciales !== null) {
            return $this->credenciales;
        }
        if (! $this->disponible()) {
            throw new RuntimeException('No hay certificado de sello configurado.');
        }
        $p12 = (string) file_get_contents((string) config('sede.sello.p12'));
        $datos = [];
        if (! openssl_pkcs12_read($p12, $datos, (string) config('sede.sello.clave'))) {
            throw new RuntimeException('No se puede leer el certificado de sello: compruebe la contraseña.');
        }
        $info = openssl_x509_parse($datos['cert']) ?: [];
        $extra = '';
        foreach ($datos['extracerts'] ?? [] as $c) {
            $extra .= $c;
        }

        return $this->credenciales = [
            'cert' => $datos['cert'],
            'pkey' => $datos['pkey'],
            'extracerts' => $extra,
            'nombre' => (string) ($info['subject']['CN'] ?? 'Sello electrónico'),
        ];
    }

    /** Opciones de firma para tc-lib-pdf (perfil PAdES-BASELINE-B). */
    public function opcionesFirma(): array
    {
        $c = $this->credenciales();

        return array_filter([
            'profile' => 'pades-b-b',
            'digest_algorithm' => 'sha256',
            'signcert' => $c['cert'],
            'privkey' => $c['pkey'],
            'password' => '',
            'extracerts' => $c['extracerts'] !== '' ? $c['extracerts'] : null,
            'cert_type' => 2,
            'info' => [
                'Name' => $c['nombre'],
                'Location' => '',
                'Reason' => (string) config('sede.sello.motivo', 'Sello electrónico'),
                'ContactInfo' => (string) config('sede.organismo.email', ''),
            ],
        ], fn ($v) => $v !== null);
    }
}
