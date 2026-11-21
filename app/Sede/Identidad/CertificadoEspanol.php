<?php

namespace App\Sede\Identidad;

use InvalidArgumentException;

/**
 * Datos de identidad leídos de un certificado cualificado español
 * (FNMT persona física, DNIe, representante de persona jurídica, sello de entidad…).
 */
final class CertificadoEspanol
{
    public const PERSONA_FISICA = 'persona_fisica';

    public const REPRESENTANTE_JURIDICA = 'representante_juridica';

    public const SELLO = 'sello';

    public const DESCONOCIDO = 'desconocido';

    public function __construct(
        public readonly string $pem,
        public readonly string $tipo,
        public readonly ?string $nif,
        public readonly ?string $nombre,
        public readonly ?string $apellidos,
        public readonly ?string $nifEntidad,
        public readonly ?string $razonSocial,
        public readonly string $emisor,
        public readonly string $serie,
        public readonly int $validoDesde,
        public readonly int $validoHasta,
        public readonly string $cn,
    ) {}

    public static function desdeDer(string $der): self
    {
        $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";

        return self::desdePem($pem);
    }

    public static function desdePem(string $pem): self
    {
        $info = openssl_x509_parse($pem, false);
        if (! is_array($info)) {
            throw new InvalidArgumentException('El certificado no se puede leer.');
        }
        $s = $info['subject'] ?? [];
        $leer = function (array $claves) use ($s): ?string {
            foreach ($claves as $c) {
                if (isset($s[$c])) {
                    $v = is_array($s[$c]) ? implode(' ', $s[$c]) : $s[$c];

                    return trim((string) $v);
                }
            }

            return null;
        };

        $cn = (string) $leer(['commonName', 'CN']);
        $serial = $leer(['serialNumber']);
        $gn = $leer(['givenName', 'GN']);
        $sn = $leer(['surname', 'SN']);
        $orgId = $leer(['organizationIdentifier', '2.5.4.97']);
        $org = $leer(['organizationName', 'O']);

        $nif = self::normalizarNif($serial) ?? self::nifEnTexto($cn);
        $nifEntidad = self::normalizarNif($orgId);
        if ($nifEntidad === null && preg_match('/\(R:\s*([A-Z0-9]+)\)/i', $cn, $m)) {
            $nifEntidad = self::normalizarNif($m[1]);
        }

        // Nombre y apellidos: atributos GN/SN o, si faltan, el CN del DNIe («APELLIDOS, NOMBRE (AUTENTICACIÓN)»).
        $nombre = $gn;
        $apellidos = $sn;
        if (($nombre === null || $apellidos === null) && preg_match('/^([^,]+),\s*([^(]+)/u', $cn, $m)) {
            $apellidos ??= trim($m[1]);
            $nombre ??= trim($m[2]);
        }

        $tipo = match (true) {
            $nifEntidad !== null && $nombre !== null => self::REPRESENTANTE_JURIDICA,
            $nifEntidad !== null => self::SELLO,
            $nif !== null => self::PERSONA_FISICA,
            default => self::DESCONOCIDO,
        };

        $emisor = (string) ($info['issuer']['commonName'] ?? $info['issuer']['CN'] ?? $info['issuer']['organizationName'] ?? '');

        return new self(
            pem: $pem,
            tipo: $tipo,
            nif: $nif,
            nombre: $nombre ? self::titulo($nombre) : null,
            apellidos: $apellidos ? self::titulo($apellidos) : null,
            nifEntidad: $nifEntidad,
            razonSocial: $nifEntidad ? $org : null,
            emisor: $emisor,
            serie: (string) ($info['serialNumberHex'] ?? $info['serialNumber'] ?? ''),
            validoDesde: (int) ($info['validFrom_time_t'] ?? 0),
            validoHasta: (int) ($info['validTo_time_t'] ?? 0),
            cn: $cn,
        );
    }

    public function der(): string
    {
        return (string) base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $this->pem));
    }

    public function vigente(?int $ahora = null): bool
    {
        $ahora ??= time();

        return $this->validoDesde <= $ahora && $ahora <= $this->validoHasta;
    }

    /** @return array<string, mixed> Resumen que se guarda con la persona (sin la clave pública). */
    public function resumen(): array
    {
        return [
            'tipo' => $this->tipo,
            'emisor' => $this->emisor,
            'serie' => $this->serie,
            'valido_hasta' => date('Y-m-d', $this->validoHasta),
            'nif_entidad' => $this->nifEntidad,
        ];
    }

    /** Quita prefijos (IDCES-, VATES-, NIF…) y valida la letra de control. */
    public static function normalizarNif(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $v = strtoupper(preg_replace('/[\s.-]/', '', $valor));
        $v = (string) preg_replace('/^(IDCES|IDC|VATES|NTRES|PASES|NIF|ES)/', '', $v);

        return self::nifValido($v) ? $v : null;
    }

    public static function nifValido(string $v): bool
    {
        $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
        if (preg_match('/^(\d{8})([A-Z])$/', $v, $m)) {
            return $letras[(int) $m[1] % 23] === $m[2];
        }
        if (preg_match('/^([XYZ])(\d{7})([A-Z])$/', $v, $m)) {
            $num = (int) (strtr($m[1], ['X' => '0', 'Y' => '1', 'Z' => '2']).$m[2]);

            return $letras[$num % 23] === $m[3];
        }

        // Personas jurídicas y entidades: letra + 7 dígitos + control (dígito o letra).
        return (bool) preg_match('/^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$/', $v);
    }

    private static function nifEnTexto(string $texto): ?string
    {
        if (preg_match_all('/\b([XYZ]?\d{7,8}[A-Z])\b/', strtoupper($texto), $m)) {
            foreach ($m[1] as $candidato) {
                if (self::nifValido($candidato)) {
                    return $candidato;
                }
            }
        }

        return null;
    }

    private static function titulo(string $texto): string
    {
        return mb_convert_case(mb_strtolower($texto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
