<?php

namespace App\Sede\Identidad;

use App\Models\Persona;
use App\Models\Representacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Acceso con certificado electrónico: el servidor genera un reto de un solo uso, la persona
 * lo firma con AutoFirma y aquí se verifica la firma, el certificado y su revocación.
 * La interfaz está pensada para añadir después otros proveedores de identidad (Cl@ve).
 */
class ServicioAcceso
{
    private const CLAVE_SESION = 'sede.reto';

    public function __construct(private readonly VerificadorFirma $verificador) {}

    public function nuevoReto(): string
    {
        $reto = 'SEDE-ACCESO|'.now()->toIso8601String().'|'.Str::random(40);
        session()->put(self::CLAVE_SESION, ['reto' => $reto, 'caduca' => now()->addSeconds((int) config('sede.acceso.reto_ttl', 300))->timestamp]);

        return $reto;
    }

    public function acceder(string $firmaB64): Persona
    {
        $guardado = session()->pull(self::CLAVE_SESION); // un solo uso
        if (! is_array($guardado) || ($guardado['caduca'] ?? 0) < time()) {
            throw new AccesoDenegado('La sesión de acceso ha caducado. Vuelva a intentarlo.');
        }

        $certificado = $this->verificador->verificar($firmaB64, (string) $guardado['reto']);

        return $this->personaDesdeCertificado($certificado);
    }

    /** Alta o actualización de la persona con los datos del certificado. */
    public function personaDesdeCertificado(CertificadoEspanol $c): Persona
    {
        return DB::transaction(function () use ($c) {
            $persona = Persona::query()->firstOrNew(['nif' => $c->nif]);
            $persona->tipo_persona = 'fisica';
            if ($c->nombre) {
                $persona->nombre = $c->nombre;
            }
            if ($c->apellidos) {
                $persona->apellido1 = $c->apellidos;
                $persona->apellido2 = null;
            }
            $persona->ultimo_acceso_at = now();
            $persona->ultimo_certificado = $c->resumen();
            $persona->save();

            // El certificado de representante de persona jurídica acredita la representación.
            if ($c->tipo === CertificadoEspanol::REPRESENTANTE_JURIDICA && $c->nifEntidad) {
                $entidad = Persona::query()->firstOrCreate(['nif' => $c->nifEntidad], [
                    'tipo_persona' => 'juridica',
                    'razon_social' => $c->razonSocial ?? $c->nifEntidad,
                ]);
                Representacion::query()->updateOrCreate(
                    ['representante_id' => $persona->id, 'representado_id' => $entidad->id, 'estado' => 'validada'],
                    [
                        'ambito' => 'todos',
                        'validada_at' => now(),
                        'vigente_hasta' => date('Y-m-d', $c->validoHasta),
                        'observaciones' => 'Acreditada con certificado de representante emitido por '.$c->emisor.'.',
                    ],
                );
            }

            return $persona;
        });
    }
}
