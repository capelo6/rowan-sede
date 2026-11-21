<?php

namespace App\Filament\Resources\ExpedienteResource\Pages;

use App\Enums\EstadoExpediente;
use App\Filament\Resources\ExpedienteResource;
use App\Filament\Support\Permiso;
use App\Models\Actuacion;
use App\Models\Expediente;
use App\Models\Plantilla;
use App\Models\Tasa;
use App\Sede\Expedientes\Tramitador;
use App\Sede\Pagos\Liquidador;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Throwable;

/** Ficha del expediente con las acciones de tramitación. */
class VerExpediente extends ViewRecord
{
    protected static string $resource = ExpedienteResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        activity('expedientes')->causedBy(auth()->user())->performedOn($this->getRecord())->event('consulta')->log('Consulta del expediente');
    }

    private function exp(): Expediente
    {
        /** @var Expediente $e */
        $e = $this->getRecord()->refresh();

        return $e;
    }

    private function ejecutar(callable $accion, string $exito): void
    {
        try {
            $accion();
            Notification::make()->title($exito)->success()->send();
        } catch (DomainException $e) {
            Notification::make()->title('No se ha podido completar')->body($e->getMessage())->danger()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Error inesperado')->body($e->getMessage())->danger()->send();
        }
        $this->refreshFormData([]);
    }

    protected function getHeaderActions(): array
    {
        $tramitar = fn () => Permiso::tiene('expedientes.tramitar');

        return [
            Action::make('revisar')->label('Revisar solicitud')->icon('heroicon-o-hand-raised')
                ->visible(fn () => $tramitar() && in_array($this->exp()->estado, [EstadoExpediente::Nueva, EstadoExpediente::EnRevision], true) && $this->exp()->asignado_a !== auth()->id())
                ->requiresConfirmation()->modalDescription('El expediente quedará asignado a usted.')
                ->action(fn () => $this->ejecutar(fn () => app(Tramitador::class)->asignar($this->exp(), auth()->user()), 'Expediente asignado.')),

            Action::make('lista')->label('Lista de revisión')->icon('heroicon-o-clipboard-document-check')->color('gray')
                ->visible(fn () => $tramitar() && $this->exp()->estado === EstadoExpediente::EnRevision)
                ->fillForm(fn () => ['comprobado' => $this->exp()->datos['_revision'] ?? []])
                ->schema(fn () => [CheckboxList::make('comprobado')->label('Comprobaciones hechas')->options($this->opcionesRevision())->columns(1)])
                ->action(function (array $data) {
                    $e = $this->exp();
                    $e->update(['datos' => array_merge($e->datos ?? [], ['_revision' => array_values($data['comprobado'] ?? [])])]);
                    activity('expedientes')->causedBy(auth()->user())->performedOn($e)->event('revision')->withProperties(['comprobado' => $data['comprobado'] ?? []])->log('Lista de revisión actualizada');
                    Notification::make()->title('Revisión guardada.')->success()->send();
                }),

            Action::make('requerir')->label('Requerir subsanación')->icon('heroicon-o-document-magnifying-glass')->color('warning')
                ->visible(fn () => $tramitar() && $this->exp()->estado === EstadoExpediente::EnRevision)
                ->schema([
                    Repeater::make('items')->label('Qué tiene que aportar')->minItems(1)->defaultItems(1)->schema([
                        Select::make('tipo')->label('Tipo')->options(['documento' => 'Documento', 'dato' => 'Dato'])->default('documento')->required(),
                        TextInput::make('descripcion')->label('Descripción')->required()->columnSpan(2),
                    ])->columns(3),
                    Textarea::make('motivo')->label('Motivo o instrucciones')->rows(3),
                    TextInput::make('plazo')->label('Plazo (días hábiles)')->numeric()->default((int) config('sede.plazos.subsanacion_dias', 10))->minValue(1)->required(),
                ])
                ->action(function (array $data) {
                    $items = [];
                    foreach (array_values($data['items']) as $i => $it) {
                        $items[] = ['clave' => 'req'.($i + 1), 'tipo' => $it['tipo'], 'descripcion' => $it['descripcion']];
                    }
                    $this->ejecutar(fn () => app(Tramitador::class)->requerir($this->exp(), auth()->user(), $items, $data['motivo'] ?? null, (int) $data['plazo']),
                        'Requerimiento sellado, registrado y notificado.');
                }),

            Action::make('tasas')->label('Asignar tasas')->icon('heroicon-o-currency-euro')
                ->visible(fn () => $tramitar() && $this->exp()->estado === EstadoExpediente::EnRevision && ! $this->exp()->liquidacionPendiente())
                ->schema([
                    Repeater::make('tasas')->label('Tasas del catálogo')->defaultItems(1)->schema([
                        Select::make('tasa_id')->label('Tasa')->required()->searchable()
                            ->options(fn () => Tasa::query()->where('activa', true)->where('ejercicio', (int) now()->format('Y'))->get()->mapWithKeys(fn (Tasa $t) => [$t->id => $t->etiqueta()])->all()),
                        TextInput::make('cantidad')->label('Cantidad')->numeric()->default(1)->minValue(0.01)->required(),
                    ])->columns(2),
                    Repeater::make('manuales')->label('Líneas manuales')->defaultItems(0)->schema([
                        TextInput::make('concepto')->label('Concepto')->required(),
                        TextInput::make('importe')->label('Importe sin IVA')->numeric()->required()->suffix('€'),
                        TextInput::make('iva_porcentaje')->label('IVA')->numeric()->default(0)->suffix('%'),
                        TextInput::make('motivo')->label('Motivo (obligatorio)')->required()->columnSpanFull(),
                    ])->columns(3),
                    TextInput::make('plazo')->label('Plazo de pago (días hábiles)')->numeric()->default((int) config('sede.plazos.pago_dias', 10))->required(),
                ])
                ->action(function (array $data) {
                    $lineas = array_merge(array_values($data['tasas'] ?? []), array_values($data['manuales'] ?? []));
                    $this->ejecutar(fn () => app(Liquidador::class)->emitir($this->exp(), auth()->user(), $lineas, (int) $data['plazo']), 'Tasas asignadas y comunicadas.');
                }),

            Action::make('ventanilla')->label('Registrar pago en ventanilla')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn () => Permiso::tiene('pagos.registrar') && $this->exp()->liquidacionPendiente() !== null)
                ->schema([
                    Select::make('canal')->label('Medio')->options(['ventanilla' => 'Ventanilla (tarjeta o efectivo)', 'transferencia' => 'Transferencia'])->default('ventanilla')->required(),
                    TextInput::make('referencia')->label('Referencia del cobro'),
                ])
                ->modalDescription(fn () => 'Importe: '.number_format((float) $this->exp()->liquidacionPendiente()?->total, 2, ',', '.').' €')
                ->action(fn (array $data) => $this->ejecutar(fn () => app(Liquidador::class)->registrarPago($this->exp()->liquidacionPendiente(), $data['canal'], auth()->user(), null, [], $data['referencia'] ?? null), 'Pago registrado. Se ha generado el justificante.')),

            Action::make('en_curso')->label('Programar servicio')->icon('heroicon-o-calendar')
                ->visible(fn () => $tramitar() && in_array($this->exp()->estado, [EstadoExpediente::Pagado, EstadoExpediente::EnRevision], true) && ! ($this->exp()->procedimiento->requiere_tasa && $this->exp()->estado === EstadoExpediente::EnRevision))
                ->schema([
                    TextInput::make('descripcion')->label('Servicio')->required(),
                    DateTimePicker::make('programada_para')->label('Fecha programada')->native(false)->seconds(false),
                    TextInput::make('periodicidad_meses')->label('Se repite cada (meses)')->numeric()->helperText('Déjelo vacío si es un servicio único.'),
                ])
                ->action(function (array $data) {
                    $this->ejecutar(function () use ($data) {
                        $e = $this->exp();
                        Actuacion::query()->create(['expediente_id' => $e->id, 'descripcion' => $data['descripcion'], 'programada_para' => $data['programada_para'] ?? null,
                            'periodicidad_meses' => $data['periodicidad_meses'] ?: null]);
                        app(Tramitador::class)->cambiarEstado($e, EstadoExpediente::EnCurso, 'Servicio programado: '.$data['descripcion'].'.', 'usuario', auth()->id());
                    }, 'Servicio programado.');
                }),

            Action::make('resolver')->label('Resolver')->icon('heroicon-o-check-badge')->color('primary')
                ->visible(fn () => $tramitar() && ! $this->exp()->estado->esFinal() && $this->exp()->estado !== EstadoExpediente::Nueva)
                ->schema([
                    Select::make('resultado')->label('Resultado')->options(['aprobada' => 'Estimar / aprobar', 'denegada' => 'Denegar (motivada)', 'anulada' => 'Anular'])->required()->live(),
                    Select::make('plantilla_id')->label('Plantilla')->required()
                        ->options(fn (Get $get) => Plantilla::query()->where('activa', true)
                            ->whereIn('tipo', $get('resultado') === 'denegada' ? ['denegacion', 'otro'] : ['resolucion', 'otro'])->pluck('nombre', 'id')->all()),
                    Textarea::make('motivo')->label('Motivación')->rows(4)->required(fn (Get $get) => $get('resultado') === 'denegada'),
                ])
                ->modalDescription('Se generará el documento con sello electrónico, se asentará en el registro de salida y se notificará.')
                ->action(fn (array $data) => $this->ejecutar(fn () => app(Tramitador::class)->resolver($this->exp(), auth()->user(),
                    Plantilla::query()->findOrFail($data['plantilla_id']), $data['resultado'], $data['motivo'] ?? null), 'Expediente resuelto y notificado.')),

            Action::make('desistimiento')->label('Confirmar desistimiento')->icon('heroicon-o-archive-box-x-mark')->color('danger')
                ->visible(fn () => $tramitar() && $this->exp()->desistimiento_propuesto_at !== null && ! $this->exp()->estado->esFinal())
                ->requiresConfirmation()->modalDescription('El plazo ha vencido sin respuesta. Se emitirá la resolución de desistimiento y se archivará el expediente.')
                ->action(fn () => $this->ejecutar(fn () => app(Tramitador::class)->confirmarDesistimiento($this->exp(), auth()->user()), 'Desistimiento confirmado.')),

            ActionGroup::make([
                Action::make('nota')->label('Añadir nota interna')->icon('heroicon-o-pencil-square')
                    ->schema([Textarea::make('texto')->label('Nota')->required()->rows(4)])
                    ->action(function (array $data) {
                        $this->exp()->notas()->create(['user_id' => auth()->id(), 'texto' => $data['texto']]);
                        Notification::make()->title('Nota añadida.')->success()->send();
                    }),
                Action::make('zip')->label('Descargar expediente (ZIP con índice)')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn () => route('gestion.expedientes.zip', $this->exp()), shouldOpenInNewTab: true),
            ])->label('Más')->icon('heroicon-o-ellipsis-vertical')->color('gray')->button(),
        ];
    }

    /** @return array<string, string> */
    private function opcionesRevision(): array
    {
        $e = $this->exp();
        $op = [
            'certificado' => 'Certificado y firma de la solicitud comprobados',
            'datos' => 'Datos de la solicitud completos y coherentes',
        ];
        if ($e->representacion_id) {
            $op['representacion'] = 'Representación comprobada';
        }
        foreach ($e->procedimiento->documentosRequeridos() as $doc) {
            $op['doc_'.$doc['clave']] = 'Documento: '.$doc['nombre'];
        }
        $op['programa_gestion'] = 'Comprobado en el programa de gestión del organismo';

        return $op;
    }
}
