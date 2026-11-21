<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificacionResource\Pages\ListarNotificaciones;
use App\Filament\Support\Permiso;
use App\Models\Notificacion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class NotificacionResource extends Resource
{
    protected static ?string $model = Notificacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Registro y pagos';

    protected static ?string $modelLabel = 'notificación';

    protected static ?string $pluralModelLabel = 'notificaciones';

    protected static ?string $slug = 'notificaciones';

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
                TextColumn::make('puesta_disposicion_at')->label('Puesta a disposición')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('asunto')->label('Asunto')->searchable()->wrap(),
                TextColumn::make('expediente.numero')->label('Expediente')->searchable()
                    ->url(fn (Notificacion $n) => $n->expediente_id ? ExpedienteResource::getUrl('view', ['record' => $n->expediente_id]) : null),
                TextColumn::make('persona.nif')->label('Destinatario')->searchable(),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => ['pendiente' => 'Sin abrir', 'accedida' => 'Practicada', 'rechazada' => 'Rechazada'][$state] ?? $state)
                    ->color(fn ($state) => ['pendiente' => 'warning', 'accedida' => 'success', 'rechazada' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('accedida_at')->label('Abierta')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->defaultSort('puesta_disposicion_at', 'desc')
            ->filters([SelectFilter::make('estado')->options(['pendiente' => 'Sin abrir', 'accedida' => 'Practicada', 'rechazada' => 'Rechazada'])])
            ->recordActions([
                Action::make('justificante')->label('Justificante')->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Notificacion $n) => $n->justificante ? route('gestion.documentos.descargar', $n->justificante) : null, shouldOpenInNewTab: true)
                    ->visible(fn (Notificacion $n) => $n->justificante_id !== null),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarNotificaciones::route('/')];
    }
}
