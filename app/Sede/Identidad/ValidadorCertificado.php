<?php

namespace App\Sede\Identidad;

use Com\Tecnick\Pdf\Sign\Exception as FirmaException;
use Com\Tecnick\Pdf\Sign\Ocsp\Client as ClienteOcsp;
use Com\Tecnick\Pdf\Sign\RevokedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Comprueba que el certificado está vigente, que lo emitió un prestador de confianza
 * y que no está revocado (OCSP, con la URL del propio certificado).
 */
class ValidadorCertificado
{
    /** @var list<string>|null DER de los certificados de confianza */
    private ?array $confianza = null;

    public function validar(CertificadoEspanol $cert): void
    {
        if (! $cert->vigente()) {
            throw new AccesoDenegado('El certificado está caducado o todavía no es válido.');
        }
        if ($cert->tipo === CertificadoEspanol::SELLO) {
            throw new AccesoDenegado('Los certificados de sello no sirven para identificar a una persona.');
        }
        if ($cert->nif === null) {
            throw new AccesoDenegado('El certificado no contiene un NIF válido.');
        }

        $emisor = $this->buscarEmisor($cert);
        if ($emisor === null) {
            if ($this->confianza() !== [] || config('sede.acceso.exigir_confianza', true)) {
                throw new AccesoDenegado('El certificado no lo ha emitido un prestador de confianza reconocido por esta sede.');
            }
        }

        if (config('sede.acceso.ocsp.activo', true)) {
            $this->comprobarRevocacion($cert, $emisor);
        }
    }

    private function comprobarRevocacion(CertificadoEspanol $cert, ?string $emisorDer): void
    {
        $info = openssl_x509_parse($cert->pem) ?: [];
        $aia = (string) ($info['extensions']['authorityInfoAccess'] ?? '');
        $url = preg_match('/OCSP\s*-\s*URI:(\S+)/i', $aia, $m) ? $m[1] : null;
        if ($emisorDer === null && preg_match('/CA Issuers\s*-\s*URI:(\S+)/i', $aia, $m2)) {
            $emisorDer = $this->descargarEmisor($m2[1]);
        }
        if ($url === null || $emisorDer === null) {
            $this->sinRespuesta('El certificado no indica un servicio OCSP o no se encuentra su emisor.');

            return;
        }

        try {
            (new ClienteOcsp)->fetch($url, $emisorDer, $cert->der(), function (string $u, string $peticion): string {
                return Http::withBody($peticion, 'application/ocsp-request')
                    ->accept('application/ocsp-response')
                    ->timeout((int) config('sede.acceso.ocsp.timeout', 8))
                    ->post($u)->throw()->body();
            });
        } catch (RevokedException) {
            throw new AccesoDenegado('El certificado está revocado.');
        } catch (FirmaException|Throwable $e) {
            Log::warning('OCSP sin respuesta válida', ['url' => $url, 'error' => $e->getMessage()]);
            $this->sinRespuesta('No se ha podido comprobar si el certificado está revocado. Inténtelo de nuevo en unos minutos.');
        }
    }

    private function sinRespuesta(string $mensaje): void
    {
        if (! config('sede.acceso.ocsp.permitir_si_no_responde', false)) {
            throw new AccesoDenegado($mensaje);
        }
    }

    /** Devuelve el DER del certificado emisor si está entre los de confianza y lo firmó. */
    private function buscarEmisor(CertificadoEspanol $cert): ?string
    {
        $hoja = $cert->der();
        foreach ($this->confianza() as $der) {
            if ($this->emitido($der, $hoja)) {
                return $der;
            }
        }

        return null;
    }

    private function emitido(string $emisorDer, string $hojaDer): bool
    {
        try {
            return (new \Com\Tecnick\Pdf\Sign\Cms\Certificate)->isIssuerOf($emisorDer, $hojaDer);
        } catch (Throwable) {
            return false;
        }
    }

    private function descargarEmisor(string $url): ?string
    {
        try {
            $cuerpo = Http::timeout((int) config('sede.acceso.ocsp.timeout', 8))->get($url)->throw()->body();
        } catch (Throwable) {
            return null;
        }

        return str_contains($cuerpo, 'BEGIN CERTIFICATE') ? $this->pemADer($cuerpo) : $cuerpo;
    }

    /** @return list<string> */
    private function confianza(): array
    {
        if ($this->confianza !== null) {
            return $this->confianza;
        }
        $this->confianza = [];
        $dir = (string) config('sede.acceso.confianza');
        if ($dir === '' || ! is_dir($dir)) {
            return $this->confianza;
        }
        foreach (glob(rtrim($dir, '/\\').'/*.{pem,crt,cer,der}', GLOB_BRACE) ?: [] as $fichero) {
            $contenido = (string) file_get_contents($fichero);
            if (str_contains($contenido, 'BEGIN CERTIFICATE')) {
                preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $contenido, $m);
                foreach ($m[0] as $pem) {
                    $this->confianza[] = $this->pemADer($pem);
                }
            } else {
                $this->confianza[] = $contenido;
            }
        }

        return $this->confianza;
    }

    private function pemADer(string $pem): string
    {
        return (string) base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem));
    }
}
