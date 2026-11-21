<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Liquidacion;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\Plantilla;
use App\Models\Procedimiento;
use App\Models\Registro;
use App\Models\Tasa;
use App\Models\User;
use App\Sede\Expedientes\Tramitador;
use App\Sede\Notificaciones\Notificador;
use App\Sede\Pagos\Liquidador;
use App\Sede\Pagos\Redsys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Certificados;
use Tests\TestCase;

/** Recorre el procedimiento completo por HTTP y con los servicios de tramitación. */
class FlujoSedeTest extends TestCase
{
    use RefreshDatabase;

    private Persona $persona;

    private User $tramitador;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'sede.acceso.simulado' => true,
            'sede.acceso.confianza' => Certificados::carpetaConfianza(),
            'sede.acceso.ocsp.activo' => false,
            'sede.sello.p12' => Certificados::selloP12('prueba'),
            'sede.sello.clave' => 'prueba',
        ]);
        $this->artisan('sede:demo')->assertSuccessful();
        $this->tramitador = User::query()->where('email', 'tramitacion@valdeloria.example')->firstOrFail();
    }

    private function entrar(): void
    {
        $this->post(route('sede.acceso.simulado'), ['nif' => '12345678Z', 'nombre' => 'María', 'apellidos' => 'Pérez Gómez'])
            ->assertRedirect(route('sede.carpeta'));
        $this->persona = Persona::query()->where('nif', '12345678Z')->firstOrFail();
        $this->get(route('sede.carpeta'))->assertRedirect(route('sede.perfil'));
        $this->post(route('sede.perfil.guardar'), ['email' => 'maria@example.com', 'privacidad' => '1', 'notificaciones_electronicas' => '1'])
            ->assertRedirect(route('sede.carpeta'));
    }

    public function test_paginas_publicas(): void
    {
        $this->get('/')->assertOk()->assertSee('Solicitud general')->assertHeader('Content-Security-Policy');
        $this->get(route('sede.tramite', 'CERTIFICADO'))->assertOk()->assertSee('Documento de identidad');
        $this->get(route('sede.acceso'))->assertOk()->assertSee('Acceder con certificado');
        foreach (['accesibilidad', 'aviso-legal', 'privacidad', 'cookies', 'verificar'] as $r) {
            $this->get(route('sede.'.$r))->assertOk();
        }
        $this->get(route('sede.carpeta'))->assertRedirect(route('sede.acceso'));
    }

    public function test_solicitud_completa_con_requerimiento_tasa_pago_y_resolucion(): void
    {
        $this->entrar();
        $procedimiento = Procedimiento::query()->where('codigo', 'CERTIFICADO')->firstOrFail();

        // Borrador, guardado automático y documento
        $this->post(route('sede.solicitudes.crear', $procedimiento))->assertRedirect();
        $expediente = Expediente::query()->latest('id')->firstOrFail();
        $this->get(route('sede.solicitudes.editar', $expediente))->assertOk()->assertSee('Documento que solicita');
        $this->postJson(route('sede.solicitudes.guardar', $expediente), ['datos' => ['tipo' => 'Certificado', 'motivo' => 'Trámite bancario']])
            ->assertOk()->assertJsonStructure(['guardado']);

        // Sin el documento obligatorio no deja continuar
        $this->get(route('sede.solicitudes.resumen', $expediente))->assertRedirect(route('sede.solicitudes.editar', $expediente));
        $this->post(route('sede.solicitudes.documentos', $expediente), ['clave' => 'identidad', 'fichero' => UploadedFile::fake()->create('dni.pdf', 120, 'application/pdf')])
            ->assertRedirect();
        $this->get(route('sede.solicitudes.resumen', $expediente))->assertOk()->assertSee('Trámite bancario');

        // Firma simulada y registro de entrada
        $preparado = $this->postJson(route('sede.solicitudes.preparar', $expediente))->assertOk()->json();
        $this->assertStringStartsWith('%PDF', base64_decode($preparado['datos']));
        $this->post(route('sede.solicitudes.presentar', $expediente), ['documento' => $preparado['documento'], 'simulada' => '1'])
            ->assertRedirect(route('sede.expedientes.ver', $expediente));
        $expediente->refresh();
        $this->assertSame(EstadoExpediente::Nueva, $expediente->estado);
        $this->assertMatchesRegularExpression('#^\d{4}/000001$#', (string) $expediente->numero);
        $registro = Registro::query()->where('tipo', 'entrada')->firstOrFail();
        $this->assertSame(1, $registro->numero);
        $justificante = $registro->justificante;
        $this->assertNotNull($justificante->csv);
        $this->assertTrue($justificante->firmado);
        $this->assertStringContainsString('ETSI.CAdES.detached', $justificante->contenido());

        // El personal revisa y requiere documentación
        $t = app(Tramitador::class);
        $t->asignar($expediente, $this->tramitador);
        $req = $t->requerir($expediente->refresh(), $this->tramitador, [['clave' => 'poder', 'tipo' => 'documento', 'descripcion' => 'Copia legible del documento de identidad']], 'La copia aportada no se lee.');
        $this->assertSame(EstadoExpediente::EsperandoDocumentacion, $expediente->refresh()->estado);
        $this->assertNotNull($req->vence_at);

        // La persona ve el aviso, abre la notificación y subsana
        $this->get(route('sede.carpeta'))->assertOk()->assertSee('tiene que aportar documentación');
        $notificacion = Notificacion::query()->where('persona_id', $this->persona->id)->firstOrFail();
        $documentoNotificado = Documento::query()->findOrFail($notificacion->documento_id);
        $this->get(route('sede.documentos.descargar', $documentoNotificado))->assertForbidden();
        $this->post(route('sede.notificaciones.acceder', $notificacion))->assertRedirect();
        $this->assertSame('accedida', $notificacion->refresh()->estado);
        $this->assertNotNull($notificacion->justificante_id);
        $this->get(route('sede.documentos.descargar', $documentoNotificado))->assertOk();

        $this->get(route('sede.expedientes.subsanar', $expediente))->assertOk()->assertSee('Copia legible');
        $this->post(route('sede.expedientes.subsanar.enviar', $expediente), ['ficheros' => ['poder' => [UploadedFile::fake()->create('dni2.pdf', 80, 'application/pdf')]]])
            ->assertRedirect(route('sede.expedientes.ver', $expediente));
        $this->assertSame(EstadoExpediente::EnRevision, $expediente->refresh()->estado);

        // Tasa y pago con Redsys (notificación firmada)
        $tasa = Tasa::query()->where('codigo', 'T01')->where('ejercicio', now()->year)->firstOrFail();
        $liq = app(Liquidador::class)->emitir($expediente, $this->tramitador, [['tasa_id' => $tasa->id, 'cantidad' => 2], ['concepto' => 'Envío postal', 'importe' => 3.10, 'iva_porcentaje' => 21, 'motivo' => 'Lo pide la persona interesada']]);
        $this->assertSame('28.75', (string) $liq->total); // 25,00 + 3,10 + 0,65 de IVA
        $this->assertSame(EstadoExpediente::PendientePago, $expediente->refresh()->estado);

        $this->post(route('sede.pagos.iniciar', $expediente), ['canal' => 'bizum'])->assertOk()->assertSee('Ds_MerchantParameters', false);
        $pago = Pago::query()->latest('id')->firstOrFail();
        $redsys = app(Redsys::class);
        $params = base64_encode(json_encode(['Ds_Order' => $pago->pedido, 'Ds_Response' => '0000', 'Ds_Amount' => '2875', 'Ds_AuthorisationCode' => '123456', 'Ds_ProcessedPayMethod' => '68']));
        $this->post(route('sede.pagos.notificacion'), ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $params, 'Ds_Signature' => 'firma-falsa'])->assertStatus(400);
        $this->post(route('sede.pagos.notificacion'), ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $params, 'Ds_Signature' => $redsys->firmar($pago->pedido, $params)])->assertOk();
        $this->assertSame('autorizado', $pago->refresh()->estado);
        $this->assertSame('bizum', $pago->canal);
        $this->assertSame('pagada', $liq->refresh()->estado);
        $this->assertSame(EstadoExpediente::Pagado, $expediente->refresh()->estado);

        // Resolución con plantilla y sello, registro de salida y notificación
        $t->cambiarEstado($expediente, EstadoExpediente::EnCurso, 'Se prepara el certificado.', 'usuario', $this->tramitador->id);
        $doc = $t->resolver($expediente->refresh(), $this->tramitador, Plantilla::query()->where('codigo', 'RES-ESTIMATORIA')->firstOrFail(), 'aprobada', 'Se expide el certificado solicitado.');
        $this->assertTrue($doc->firmado);
        $this->assertSame(EstadoExpediente::Cerrada, $expediente->refresh()->estado);
        $this->assertSame('aprobada', $expediente->resultado);
        $this->assertGreaterThanOrEqual(2, Registro::query()->where('tipo', 'salida')->count());

        // Verificación pública por CSV
        $this->post(route('sede.verificar.comprobar'), ['csv' => $doc->csv])->assertOk()->assertSee('Documento auténtico');

        // La carpeta muestra la historia completa
        $this->get(route('sede.expedientes.ver', $expediente))->assertOk()->assertSee('Cerrada')->assertSee('Pendiente de pago');
    }

    public function test_desistimiento_por_plazo_y_vuelta_a_revision_si_aporta_antes_de_confirmar(): void
    {
        $this->entrar();
        $t = app(Tramitador::class);
        $expediente = $t->crearBorrador(Procedimiento::query()->where('codigo', 'SOLICITUD-GENERAL')->firstOrFail(), $this->persona);
        $t->presentar($expediente, []);
        $t->asignar($expediente->refresh(), $this->tramitador);
        $req = $t->requerir($expediente->refresh(), $this->tramitador, [['clave' => 'dato', 'tipo' => 'dato', 'descripcion' => 'Indique el número de referencia']]);

        $this->travel(30)->days();
        $this->artisan('sede:tareas')->assertSuccessful();
        $this->assertNotNull($expediente->refresh()->desistimiento_propuesto_at);
        $this->assertSame('rechazada', Notificacion::query()->latest('id')->first()->estado);

        // Aporta antes de que el personal confirme: vuelve a revisión
        $t->atenderRequerimiento($req, $this->persona, [], ['dato' => 'REF-1']);
        $this->assertSame(EstadoExpediente::EnRevision, $expediente->refresh()->estado);
        $this->assertNull($expediente->desistimiento_propuesto_at);
    }

    public function test_otra_persona_no_ve_el_expediente(): void
    {
        $this->entrar();
        $t = app(Tramitador::class);
        $ajeno = $t->crearBorrador(Procedimiento::query()->firstOrFail(), Persona::query()->create(['nif' => '00000000T', 'nombre' => 'Otra']));
        $t->presentar($ajeno, []);

        $this->get(route('sede.expedientes.ver', $ajeno))->assertNotFound();
    }

    public function test_el_acceso_simulado_no_existe_en_produccion(): void
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->post(route('sede.acceso.simulado'), ['nif' => '12345678Z', 'nombre' => 'A', 'apellidos' => 'B'])->assertNotFound();
    }
}
