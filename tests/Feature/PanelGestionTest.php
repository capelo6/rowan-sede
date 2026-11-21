<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Filament\Pages\Ajustes;
use App\Filament\Pages\Tablero;
use App\Filament\Resources\ExpedienteResource;
use App\Filament\Resources\ExpedienteResource\Pages\VerExpediente;
use App\Models\Expediente;
use App\Models\Persona;
use App\Models\Plantilla;
use App\Models\Procedimiento;
use App\Models\Tasa;
use App\Models\User;
use App\Sede\Expedientes\Tramitador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Certificados;
use Tests\TestCase;

class PanelGestionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tramitacion;

    private User $consulta;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['sede.sello.p12' => Certificados::selloP12('prueba'), 'sede.sello.clave' => 'prueba']);
        $this->artisan('sede:demo')->assertSuccessful();
        $this->admin = User::query()->where('email', 'admin@valdeloria.example')->firstOrFail();
        $this->tramitacion = User::query()->where('email', 'tramitacion@valdeloria.example')->firstOrFail();
        $this->consulta = User::query()->where('email', 'consulta@valdeloria.example')->firstOrFail();

        $persona = Persona::query()->create(['nif' => '12345678Z', 'nombre' => 'María', 'apellido1' => 'Pérez Gómez', 'email' => 'maria@example.com', 'privacidad_aceptada_at' => now()]);
        $t = app(Tramitador::class);
        $this->expediente = $t->crearBorrador(Procedimiento::query()->where('codigo', 'CERTIFICADO')->firstOrFail(), $persona);
        $this->expediente->update(['datos' => ['tipo' => 'Certificado', 'motivo' => 'Prueba']]);
        $t->presentar($this->expediente, []);
    }

    public function test_paginas_del_panel_para_administracion(): void
    {
        $this->actingAs($this->admin);
        foreach (['/gestion', '/gestion/expedientes', '/gestion/tablero', '/gestion/registros', '/gestion/pagos', '/gestion/notificaciones',
            '/gestion/representaciones', '/gestion/servicios', '/gestion/procedimientos', '/gestion/procedimientos/crear', '/gestion/tasas',
            '/gestion/festivos', '/gestion/plantillas', '/gestion/usuarios', '/gestion/auditoria', '/gestion/ajustes'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(ExpedienteResource::getUrl('view', ['record' => $this->expediente]))->assertOk()->assertSee($this->expediente->numero);
        $this->get('/gestion/procedimientos/'.Procedimiento::query()->value('id').'/editar')->assertOk();
    }

    public function test_consulta_no_ve_la_configuracion_ni_tramita(): void
    {
        $this->actingAs($this->consulta);
        $this->get('/gestion/expedientes')->assertOk();
        $this->get('/gestion/tasas')->assertForbidden();
        $this->get('/gestion/usuarios')->assertForbidden();
        Livewire::test(VerExpediente::class, ['record' => $this->expediente->getRouteKey()])->assertActionHidden('revisar')->assertActionHidden('resolver');
    }

    public function test_tramitacion_completa_desde_el_panel(): void
    {
        $this->actingAs($this->tramitacion);
        $pagina = fn () => Livewire::test(VerExpediente::class, ['record' => $this->expediente->getRouteKey()]);

        $pagina()->callAction('revisar')->assertHasNoActionErrors();
        $this->assertSame(EstadoExpediente::EnRevision, $this->expediente->refresh()->estado);
        $this->assertSame($this->tramitacion->id, $this->expediente->asignado_a);

        $pagina()->callAction('lista', data: ['comprobado' => ['certificado', 'datos', 'programa_gestion']])->assertHasNoActionErrors();
        $this->assertSame(['certificado', 'datos', 'programa_gestion'], $this->expediente->refresh()->datos['_revision']);

        $tasa = Tasa::query()->where('codigo', 'T01')->where('ejercicio', now()->year)->firstOrFail();
        $pagina()->callAction('tasas', data: ['tasas' => [['tasa_id' => $tasa->id, 'cantidad' => 1]], 'manuales' => [], 'plazo' => 10])->assertHasNoActionErrors();
        $this->assertSame(EstadoExpediente::PendientePago, $this->expediente->refresh()->estado);

        $pagina()->callAction('ventanilla', data: ['canal' => 'ventanilla', 'referencia' => 'TPV-1'])->assertHasNoActionErrors();
        $this->assertSame(EstadoExpediente::Pagado, $this->expediente->refresh()->estado);

        $pagina()->callAction('en_curso', data: ['descripcion' => 'Emisión del certificado', 'programada_para' => now()->addDay()->format('Y-m-d H:i'), 'periodicidad_meses' => null])->assertHasNoActionErrors();
        $this->assertSame(EstadoExpediente::EnCurso, $this->expediente->refresh()->estado);

        $plantilla = Plantilla::query()->where('codigo', 'RES-ESTIMATORIA')->firstOrFail();
        $pagina()->callAction('resolver', data: ['resultado' => 'aprobada', 'plantilla_id' => $plantilla->id, 'motivo' => 'Correcto.'])->assertHasNoActionErrors();
        $this->assertSame(EstadoExpediente::Cerrada, $this->expediente->refresh()->estado);

        // Descargas registradas en la auditoría
        $doc = $this->expediente->documentos()->where('categoria', 'resolucion')->firstOrFail();
        $this->get(route('gestion.documentos.descargar', $doc))->assertOk();
        $zip = $this->get(route('gestion.expedientes.zip', $this->expediente))->assertOk();
        $this->assertStringContainsString('.zip', (string) $zip->headers->get('content-disposition'));
        $this->assertDatabaseHas('activity_log', ['event' => 'descarga']);
        $this->assertDatabaseHas('activity_log', ['event' => 'consulta']);
    }

    public function test_requerimiento_desde_el_panel(): void
    {
        $this->actingAs($this->tramitacion);
        app(Tramitador::class)->asignar($this->expediente, $this->tramitacion);
        Livewire::test(VerExpediente::class, ['record' => $this->expediente->getRouteKey()])
            ->callAction('requerir', data: ['items' => [['tipo' => 'documento', 'descripcion' => 'Copia del DNI']], 'motivo' => 'Falta el DNI.', 'plazo' => 10])
            ->assertHasNoActionErrors();
        $this->assertSame(EstadoExpediente::EsperandoDocumentacion, $this->expediente->refresh()->estado);
        $this->assertSame(1, $this->expediente->notificaciones()->count());
    }

    public function test_tablero_y_ajustes(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(Tablero::class)->assertSee($this->expediente->numero)->set('mios', true)->assertDontSee($this->expediente->numero);
        Livewire::test(Ajustes::class)->set('datos.organismo.nombre', 'Organismo cambiado')->call('guardar')->assertHasNoErrors();
        $this->assertSame('Organismo cambiado', \App\Sede\Organismo::nombre());
    }
}
