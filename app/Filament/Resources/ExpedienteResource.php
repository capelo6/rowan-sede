<?php

namespace App\Filament\Resources;

use App\Enums\EstadoExpediente;
use App\Filament\Resources\ExpedienteResource\Pages;
use App\Filament\Support\Permiso;
use App\Models\Expediente;
use App\Models\Procedimiento;
use App\Models\User;
use App\Sede\Plazos\Calendario;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExpedienteResource extends Resource
{
    protected static ?string $model = Expediente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Tramitación';

    protected static ?string $modelLabel = 'expediente';

    protected static ?string $pluralModelLabel = 'bandeja de expedientes';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Permiso::tiene('expedientes.ver');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('estado', '!=', EstadoExpediente::Borrador->value);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Expediente::query()->where('estado', EstadoExpediente::Nueva->value)->count();

        return $n > 0 ? (string) $n : null;
    }

    /** Fecha de vencimiento de lo que esté pendiente (requerimiento o pago). */
    public static function vencimiento(Expediente $e): ?\Carbon\CarbonInterface
    {
        return match ($e->estado) {
            EstadoExpediente::EsperandoDocumentacion => $e->requerimientoPendiente()?->vence_at,
            EstadoExpediente::PendientePago => $e->liquidacionPendiente()?->vence_at,
            default => null,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('Expediente')->searchable()->sortable()->weight('bold'),
                TextColumn::make('procedimiento.nombre')->label('Trámite')->wrap()->toggleable(),
                TextColumn::make('titular.nif')->label('NIF')->searchable(),
                TextColumn::make('titular_nombre')->label('Titular')
                    ->state(fn (Expediente $r) => $r->titular->nombreCompleto())
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('titular', fn ($t) => $t->where('nombre', 'like', "%$search%")
                        ->orWhere('apellido1', 'like', "%$search%")->orWhere('apellido2', 'like', "%$search%")->orWhere('razon_social', 'like', "%$search%"))),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (EstadoExpediente $state) => $state->etiqueta())
                    ->color(fn (EstadoExpediente $state) => $state->color()),
                TextColumn::make('plazo')->label('Plazo')
                    ->state(function (Expediente $r) {
                        $v = self::vencimiento($r);
                        if (! $v) {
                            return null;
                        }
                        $d = app(Calendario::class)->habilesRestantes($v);

                        return $v->format('d/m/Y').' · '.($d < 0 ? 'vencido' : $d.' d. háb.');
                    })
                    ->color(fn (Expediente $r) => app(Calendario::class)->semaforo(self::vencimiento($r)) === 'urgente' ? 'danger' : 'warning'),
                IconColumn::make('desistimiento_propuesto_at')->label('Desist.')->tooltip('Desistimiento propuesto por el sistema')
                    ->state(fn (Expediente $r) => $r->desistimiento_propuesto_at !== null)->boolean()->trueIcon(Heroicon::OutlinedExclamationTriangle)->trueColor('danger')->falseIcon(null),
                TextColumn::make('asignado.name')->label('Asignado a')->placeholder('Sin asignar')->toggleable(),
                TextColumn::make('presentado_at')->label('Presentado')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('presentado_at', 'desc')
            ->filters([
                SelectFilter::make('estado')->label('Estado')->multiple()->options(EstadoExpediente::opciones()),
                SelectFilter::make('procedimiento_id')->label('Trámite')->options(fn () => Procedimiento::query()->orderBy('nombre')->pluck('nombre', 'id')->all()),
                SelectFilter::make('asignado_a')->label('Asignado a')->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('mios')->label('Asignados a mí')->query(fn (Builder $query) => $query->where('asignado_a', auth()->id())),
                Filter::make('desistimiento')->label('Desistimiento propuesto')->query(fn (Builder $query) => $query->whereNotNull('desistimiento_propuesto_at')),
                Filter::make('abiertos')->label('Solo abiertos')->default()->query(fn (Builder $query) => $query->whereNotIn('estado', [EstadoExpediente::Cerrada->value, EstadoExpediente::Desistida->value])),
            ])
            ->recordActions([ViewAction::make()->label('Abrir')])
            ->recordUrl(fn (Expediente $r) => self::getUrl('view', ['record' => $r]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                Section::make('Expediente')->columnSpan(2)->columns(2)->schema([
                    TextEntry::make('numero')->label('Número'),
                    TextEntry::make('procedimiento.nombre')->label('Trámite'),
                    TextEntry::make('estado')->label('Estado')->badge()
                        ->formatStateUsing(fn (EstadoExpediente $state) => $state->etiqueta())->color(fn (EstadoExpediente $state) => $state->color()),
                    TextEntry::make('resultado')->label('Resultado')->formatStateUsing(fn ($state) => Expediente::RESULTADOS[$state] ?? $state)->placeholder('—'),
                    TextEntry::make('presentado_at')->label('Presentado')->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('asignado.name')->label('Asignado a')->placeholder('Sin asignar'),
                    TextEntry::make('plazo')->label('Plazo en curso')->state(function (Expediente $r) {
                        $v = self::vencimiento($r);

                        return $v ? $v->format('d/m/Y').' ('.app(Calendario::class)->habilesRestantes($v).' días hábiles)' : null;
                    })->placeholder('—'),
                    TextEntry::make('desistimiento_propuesto_at')->label('Desistimiento propuesto')->dateTime('d/m/Y H:i')->placeholder('No')->color('danger'),
                ]),
                Section::make('Persona interesada')->columnSpan(1)->schema([
                    TextEntry::make('titular_nombre')->label('Titular')->state(fn (Expediente $r) => $r->titular->nombreCompleto().' · '.$r->titular->nif),
                    TextEntry::make('titular.email')->label('Correo')->placeholder('—')->copyable(),
                    TextEntry::make('titular.telefono')->label('Teléfono')->placeholder('—'),
                    TextEntry::make('presentador_nombre')->label('Presentado por (representante)')
                        ->state(fn (Expediente $r) => $r->presentador_id !== $r->titular_id ? $r->presentador->nombreCompleto().' · '.$r->presentador->nif : null)->placeholder('El propio titular'),
                ]),
            ]),
            Tabs::make('Detalle')->columnSpanFull()->tabs([
                Tab::make('Solicitud y revisión')->schema([View::make('filament.expediente.datos')]),
                Tab::make('Documentos')->schema([View::make('filament.expediente.documentos')]),
                Tab::make('Historial')->schema([View::make('filament.expediente.historial')]),
                Tab::make('Requerimientos y notificaciones')->schema([View::make('filament.expediente.comunicaciones')]),
                Tab::make('Tasas y pagos')->schema([View::make('filament.expediente.pagos')]),
                Tab::make('Servicios y notas')->schema([View::make('filament.expediente.notas')]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListarExpedientes::route('/'),
            'view' => Pages\VerExpediente::route('/{record}'),
        ];
    }
}
