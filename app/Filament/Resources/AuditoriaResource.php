<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditoriaResource\Pages\ListarAuditoria;
use App\Filament\Support\Permiso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/** Registro de actividad: accesos, consultas, descargas, cambios de estado, exportaciones. */
class AuditoriaResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'actividad';

    protected static ?string $pluralModelLabel = 'auditoría';

    protected static ?string $slug = 'auditoria';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return Permiso::tiene('auditoria.ver');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Fecha y hora')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('log_name')->label('Ámbito')->badge(),
                TextColumn::make('event')->label('Evento'),
                TextColumn::make('description')->label('Descripción')->wrap()->searchable(),
                TextColumn::make('quien')->label('Quién')->state(fn (Activity $a) => $a->causer ? ($a->causer->name ?? (method_exists($a->causer, 'nombreCompleto') ? $a->causer->nombreCompleto().' ('.$a->causer->nif.')' : '')) : 'Sistema'),
                TextColumn::make('subject_type')->label('Objeto')->formatStateUsing(fn ($state, Activity $record) => $state ? class_basename($state).' #'.$record->subject_id : '—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('log_name')->label('Ámbito')->options(fn () => Activity::query()->distinct()->pluck('log_name', 'log_name')->filter()->all()),
                SelectFilter::make('event')->label('Evento')->options(fn () => Activity::query()->distinct()->pluck('event', 'event')->filter()->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarAuditoria::route('/')];
    }
}
