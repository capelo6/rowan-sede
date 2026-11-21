<?php

namespace Tests\Support;

/**
 * Genera una autoridad de certificación de pruebas y certificados con los perfiles
 * de los prestadores españoles (persona física, representante, sello).
 */
class Certificados
{
    /** @var array{cert: \OpenSSLCertificate, key: \OpenSSLAsymmetricKey, pem: string}|null */
    private static ?array $ca = null;

    /** @return array<string, mixed> */
    public static function opciones(): array
    {
        $opciones = ['digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $cnf = getenv('OPENSSL_CONF') ?: (PHP_OS_FAMILY === 'Windows' ? dirname(PHP_BINARY).'/extras/ssl/openssl.cnf' : '');
        if ($cnf !== '' && is_file($cnf)) {
            $opciones['config'] = $cnf;
        }

        return $opciones;
    }

    /** @return array{cert: \OpenSSLCertificate, key: \OpenSSLAsymmetricKey, pem: string} */
    public static function ca(): array
    {
        if (self::$ca !== null) {
            return self::$ca;
        }
        $o = self::opciones();
        $key = openssl_pkey_new($o);
        $csr = openssl_csr_new(['countryName' => 'ES', 'organizationName' => 'PRESTADOR DE PRUEBAS', 'commonName' => 'AC PRUEBAS USUARIOS'], $key, $o);
        $cert = openssl_csr_sign($csr, null, $key, 3650, $o + ['x509_extensions' => 'v3_ca'], 1);
        openssl_x509_export($cert, $pem);

        return self::$ca = ['cert' => $cert, 'key' => $key, 'pem' => $pem];
    }

    /**
     * @param  array<string, string>  $dn
     * @return array{cert: string, key: string}
     */
    public static function emitir(array $dn, int $dias = 365, ?int $serie = null): array
    {
        $o = self::opciones();
        $ca = self::ca();
        $key = openssl_pkey_new($o);
        $csr = openssl_csr_new($dn, $key, $o);
        $cert = openssl_csr_sign($csr, $ca['cert'], $ca['key'], $dias, $o, $serie ?? random_int(1000, 999999));
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, null, $o);

        return ['cert' => $certPem, 'key' => $keyPem];
    }

    /** @return array{cert: string, key: string} */
    public static function personaFisica(string $nif = '12345678Z', string $nombre = 'MARÍA', string $apellidos = 'PÉREZ GÓMEZ'): array
    {
        return self::emitir([
            'countryName' => 'ES',
            'serialNumber' => 'IDCES-'.$nif,
            'givenName' => $nombre,
            'surname' => $apellidos,
            'commonName' => $apellidos.' '.$nombre.' - '.$nif,
        ]);
    }

    /** @return array{cert: string, key: string} */
    public static function representante(string $nif = '00000000T', string $nifEntidad = 'B12345674', string $empresa = 'EMPRESA DE PRUEBA SL'): array
    {
        return self::emitir([
            'countryName' => 'ES',
            'serialNumber' => 'IDCES-'.$nif,
            'givenName' => 'JUAN',
            'surname' => 'LÓPEZ RUIZ',
            'commonName' => $nif.' JUAN LÓPEZ RUIZ (R: '.$nifEntidad.')',
            'organizationName' => $empresa,
            'organizationIdentifier' => 'VATES-'.$nifEntidad,
        ]);
    }

    /** Firma CAdES separada (como AutoFirma en modo explícito) y la devuelve en base64. */
    public static function firmarCades(string $contenido, array $credenciales): string
    {
        $dir = sys_get_temp_dir();
        $in = tempnam($dir, 'cms');
        $out = tempnam($dir, 'cms');
        file_put_contents($in, $contenido);
        $ok = openssl_cms_sign($in, $out, $credenciales['cert'], $credenciales['key'], [],
            OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER);
        $der = (string) file_get_contents($out);
        @unlink($in);
        @unlink($out);
        if (! $ok) {
            throw new \RuntimeException('No se pudo firmar: '.openssl_error_string());
        }

        return base64_encode($der);
    }

    /** Escribe la CA de pruebas en una carpeta de confianza temporal y la devuelve. */
    public static function carpetaConfianza(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sede-confianza-'.getmypid();
        @mkdir($dir);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'ac-pruebas.pem', self::ca()['pem']);

        return $dir;
    }

    /** Crea un PKCS#12 de sello para las pruebas y devuelve su ruta. */
    public static function selloP12(string $clave = 'prueba'): string
    {
        $c = self::emitir(['countryName' => 'ES', 'organizationName' => 'ORGANISMO DE PRUEBA', 'organizationIdentifier' => 'VATES-P0000000J', 'commonName' => 'SELLO DE PRUEBA']);
        $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sello-'.getmypid().'.p12';
        openssl_pkcs12_export_to_file($c['cert'], $ruta, $c['key'], $clave, ['extracerts' => [self::ca()['pem']]]);

        return $ruta;
    }
}
