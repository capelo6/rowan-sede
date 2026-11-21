<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RepresentacionResource\Pages\ListarRepresentaciones;
use App\Filament\Support\Permiso;
use App\Models\Documento;
use App\Models\Representacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RepresentacionResource extends Resource
{
    protected static ?string $model = Representacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Tramitación';

    protected static ?string $modelLabel = 'representación';

    protected static ?string $pluralModelLabel = 'representaciones';

    protected static ?string $slug = 'representaciones';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return Permiso::tiene('representaciones.validar');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Representacion::query()->where('estado', 'pendiente')->count();

        return $n ? (string) $n : null;
    }

    public static function documento(Representacion $r): ?Documento
    {
        return Documento::query()->where('categoria', 'representacion')->get()->first(fn (Documento $d) => ($d->metadatos['representacion_id'] ?? null) === $r->id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Solicitada')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('representante')->label('Representante')->state(fn (Representacion $r) => $r->representante->nombreCompleto().' · '.$r->representante->nif),
                TextColumn::make('representado')->label('Representado')->state(fn (Representacion $r) => $r->representado->nombreCompleto().' · '.$r->representado->nif),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn ($state) => Representacion::ESTADOS[$state] ?? $state)
                    ->color(fn ($state) => ['pendiente' => 'warning', 'validada' => 'success'][$state] ?? 'danger'),
                TextColumn::make('vigente_hasta')->label('Vigente hasta')->date('d/m/Y')->placeholder('Indefinida'),
                TextColumn::make('observaciones')->label('Observaciones')->wrap()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('estado')->options(Representacion::ESTADOS)->default('pendiente')])
            ->recordActions([
                Action::make('documento')->label('Documento')->icon('heroicon-o-document-magnifying-glass')
                    ->url(fn (Representacion $r) => ($d = self::documento($r)) ? route('gestion.documentos.descargar', $d) : null, shouldOpenInNewTab: true)
                    ->visible(fn (Representacion $r) => self::documento($r) !== null),
                Action::make('validar')->label('Validar')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Representacion $r) => $r->estado === 'pendiente')
                    ->schema([DatePicker::make('vigente_hasta')->label('Vigente hasta (opcional)'), Textarea::make('observaciones')->label('Observaciones')])
                    ->action(function (Representacion $r, array $data) {
                        $r->update(['estado' => 'validada', 'validada_por' => auth()->id(), 'validada_at' => now(),
                            'vigente_hasta' => $data['vigente_hasta'] ?? null, 'observaciones' => $data['observaciones'] ?? null]);
                        activity('representaciones')->causedBy(auth()->user())->performedOn($r)->event('validacion')->log('Representación validada');
                        Notification::make()->title('Representación validada.')->success()->send();
                    }),
                Action::make('rechazar')->label('Rechazar')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (Representacion $r) => $r->estado === 'pendiente')
                    ->schema([Textarea::make('observaciones')->label('Motivo')->required()])
                    ->action(function (Representacion $r, array $data) {
                        $r->update(['estado' => 'rechazada', 'validada_por' => auth()->id(), 'validada_at' => now(), 'observaciones' => $data['observaciones']]);
                        activity('representaciones')->causedBy(auth()->user())->performedOn($r)->event('rechazo')->log('Representación rechazada');
                        Notification::make()->title('Representación rechazada.')->warning()->send();
                    }),
                Action::make('revocar')->label('Revocar')->icon('heroicon-o-no-symbol')->color('gray')
                    ->visible(fn (Representacion $r) => $r->estado === 'validada')->requiresConfirmation()
                    ->action(fn (Representacion $r) => $r->update(['estado' => 'revocada'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarRepresentaciones::route('/')];
    }
}
