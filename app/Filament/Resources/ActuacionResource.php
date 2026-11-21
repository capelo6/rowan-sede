<?php

namespace App\Filament\Resources;

use App\Enums\EstadoExpediente;
use App\Filament\Resources\ActuacionResource\Pages\ListarActuaciones;
use App\Filament\Support\Permiso;
use App\Models\Actuacion;
use App\Sede\Avisos\ServicioAvisos;
use App\Sede\Documentos\Almacen;
use App\Sede\Expedientes\Tramitador;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/** Servicios programados: seguimiento, marcado como realizados con fotografía y renovaciones. */
class ActuacionResource extends Resource
{
    protected static ?string $model = Actuacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Tramitación';

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'servicios en curso';

    protected static ?string $slug = 'servicios';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return Permiso::tiene('expedientes.ver');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('programada_para')->label('Programado')->dateTime('d/m/Y H:i')->sortable()->placeholder('Sin fecha'),
                TextColumn::make('descripcion')->label('Servicio')->searchable()->wrap(),
                TextColumn::make('expediente.numero')->label('Expediente')->searchable()
                    ->url(fn (Actuacion $a) => ExpedienteResource::getUrl('view', ['record' => $a->expediente_id])),
                TextColumn::make('realizada_at')->label('Realizado')->dateTime('d/m/Y')->placeholder('Pendiente'),
                TextColumn::make('proxima_renovacion')->label('Renovación')->date('d/m/Y')->placeholder('—'),
            ])
            ->defaultSort('programada_para')
            ->filters([
                Filter::make('pendientes')->label('Pendientes')->default()->query(fn (Builder $query) => $query->whereNull('realizada_at')),
                Filter::make('mes')->label('Este mes')->query(fn (Builder $query) => $query->whereBetween('programada_para', [now()->startOfMonth(), now()->endOfMonth()])),
                Filter::make('renovar')->label('Renovación en 30 días')->query(fn (Builder $query) => $query->whereNotNull('proxima_renovacion')->whereDate('proxima_renovacion', '<=', now()->addDays(30))),
            ])
            ->recordActions([
                Action::make('realizada')->label('Marcar como realizado')->icon('heroicon-o-camera')->color('success')
                    ->visible(fn (Actuacion $a) => $a->realizada_at === null && Permiso::tiene('expedientes.tramitar'))
                    ->schema([
                        FileUpload::make('foto')->label('Fotografía (se envía a la persona interesada)')->image()->maxSize(8192)->storeFiles(false),
                        Toggle::make('cerrar')->label('Cerrar el expediente si es un servicio único')->default(false),
                    ])
                    ->action(function (Actuacion $a, array $data) {
                        $foto = $data['foto'] ?? null;
                        if ($foto instanceof TemporaryUploadedFile || $foto instanceof UploadedFile) {
                            $doc = app(Almacen::class)->guardarSubida($foto, $a->expediente, 'evidencia', [
                                'nombre' => 'Fotografía - '.$a->descripcion.'.'.$foto->getClientOriginalExtension(), 'origen' => 'organismo', 'user_id' => auth()->id()]);
                            $a->evidencia_id = $doc->id;
                        }
                        $a->realizada_at = now();
                        $a->realizada_por = auth()->id();
                        if ($a->periodicidad_meses) {
                            $a->proxima_renovacion = now()->addMonths($a->periodicidad_meses)->toDateString();
                        }
                        $a->save();
                        app(ServicioAvisos::class)->actuacionRealizada($a->refresh());
                        if (($data['cerrar'] ?? false) && $a->expediente->estado === EstadoExpediente::EnCurso) {
                            $a->expediente->update(['resultado' => 'aprobada', 'resuelto_at' => now()]);
                            app(Tramitador::class)->cambiarEstado($a->expediente, EstadoExpediente::Cerrada, 'Servicio realizado.', 'usuario', auth()->id());
                        }
                        Notification::make()->title('Servicio marcado como realizado y comunicado.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarActuaciones::route('/')];
    }
}
