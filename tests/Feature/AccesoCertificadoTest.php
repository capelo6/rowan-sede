<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Representacion;
use App\Sede\Identidad\AccesoDenegado;
use App\Sede\Identidad\ServicioAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Certificados;
use Tests\TestCase;

class AccesoCertificadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sede.acceso.confianza' => Certificados::carpetaConfianza(),
            'sede.acceso.ocsp.activo' => false,
        ]);
    }

    public function test_accede_con_el_reto_firmado_y_crea_la_persona(): void
    {
        $servicio = app(ServicioAcceso::class);
        $reto = $servicio->nuevoReto();
        $firma = Certificados::firmarCades($reto, Certificados::personaFisica());

        $persona = $servicio->acceder($firma);

        $this->assertSame('12345678Z', $persona->nif);
        $this->assertSame('María', $persona->nombre);
        $this->assertSame('persona_fisica', $persona->ultimo_certificado['tipo']);
    }

    public function test_el_reto_solo_vale_una_vez(): void
    {
        $servicio = app(ServicioAcceso::class);
        $reto = $servicio->nuevoReto();
        $firma = Certificados::firmarCades($reto, Certificados::personaFisica());
        $servicio->acceder($firma);

        $this->expectException(AccesoDenegado::class);
        $servicio->acceder($firma);
    }

    public function test_rechaza_una_firma_de_otros_datos(): void
    {
        $servicio = app(ServicioAcceso::class);
        $servicio->nuevoReto();
        $firma = Certificados::firmarCades('otro contenido', Certificados::personaFisica());

        $this->expectException(AccesoDenegado::class);
        $servicio->acceder($firma);
    }

    public function test_rechaza_un_emisor_que_no_es_de_confianza(): void
    {
        config(['sede.acceso.confianza' => sys_get_temp_dir().'/no-existe-'.uniqid()]);
        $servicio = app(ServicioAcceso::class);
        $reto = $servicio->nuevoReto();
        $firma = Certificados::firmarCades($reto, Certificados::personaFisica());

        $this->expectException(AccesoDenegado::class);
        $servicio->acceder($firma);
    }

    public function test_el_certificado_de_representante_acredita_la_representacion(): void
    {
        $servicio = app(ServicioAcceso::class);
        $reto = $servicio->nuevoReto();
        $persona = $servicio->acceder(Certificados::firmarCades($reto, Certificados::representante()));

        $entidad = Persona::query()->where('nif', 'B12345674')->firstOrFail();
        $this->assertSame('juridica', $entidad->tipo_persona);
        $representacion = Representacion::query()->where('representante_id', $persona->id)->firstOrFail();
        $this->assertSame($entidad->id, $representacion->representado_id);
        $this->assertTrue($representacion->vigente());
    }
}
