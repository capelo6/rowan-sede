<?php

namespace App\Sede\Identidad;

use Com\Tecnick\Pdf\Sign\Cms\SignedDataVerifier;
use Throwable;

/**
 * Verifica firmas CAdES generadas con AutoFirma:
 * - el reto de acceso (prueba de posesión de la clave del certificado),
 * - la firma de la solicitud (CAdES explícita sobre el PDF de la solicitud).
 */
class VerificadorFirma
{
    public function __construct(private readonly ValidadorCertificado $validador) {}

    /**
     * @param  string  $firmaB64  Firma CAdES en base64 (la que devuelve AutoScript.sign).
     * @param  string  $contenido  Bytes firmados (firma explícita / separada).
     */
    public function verificar(string $firmaB64, string $contenido, bool $validarCertificado = true): CertificadoEspanol
    {
        $cms = base64_decode(preg_replace('/\s+/', '', $firmaB64) ?? '', true);
        if ($cms === false || $cms === '') {
            throw new AccesoDenegado('La firma recibida no es válida.');
        }
        try {
            $certDer = (new SignedDataVerifier)->verify($cms, $contenido);
        } catch (Throwable) {
            throw new AccesoDenegado('La firma no corresponde a los datos enviados o está dañada.');
        }

        $certificado = CertificadoEspanol::desdeDer($certDer);
        if ($validarCertificado) {
            $this->validador->validar($certificado);
        }

        return $certificado;
    }
}
