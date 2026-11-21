<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegistroResource\Pages\ListarRegistros;
use App\Filament\Support\Permiso;
use App\Models\Registro;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RegistroResource extends Resource
{
    protected static ?string $model = Registro::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Registro y pagos';

    protected static ?string $modelLabel = 'asiento';

    protected static ?string $pluralModelLabel = 'registro de entrada y salida';

    protected static ?int $navigationSort = 1;

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
                TextColumn::make('codigo')->label('Número')->state(fn (Registro $r) => $r->codigo())->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('numero', (int) preg_replace('/\D/', '', substr($search, -6)))),
                TextColumn::make('registrado_at')->label('Fecha y hora')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn ($state) => $state === 'entrada' ? 'Entrada' : 'Salida')->color(fn ($state) => $state === 'entrada' ? 'info' : 'success'),
                TextColumn::make('asunto')->label('Asunto')->searchable()->wrap(),
                TextColumn::make('expediente.numero')->label('Expediente')->searchable()
                    ->url(fn (Registro $r) => $r->expediente ? ExpedienteResource::getUrl('view', ['record' => $r->expediente]) : null),
                TextColumn::make('persona.nif')->label('NIF')->searchable(),
            ])
            ->defaultSort('registrado_at', 'desc')
            ->filters([
                SelectFilter::make('tipo')->options(['entrada' => 'Entrada', 'salida' => 'Salida']),
                SelectFilter::make('ejercicio')->options(fn () => Registro::query()->distinct()->orderByDesc('ejercicio')->pluck('ejercicio', 'ejercicio')->all()),
                Filter::make('fechas')->schema([DatePicker::make('desde')->label('Desde'), DatePicker::make('hasta')->label('Hasta')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($query, $v) => $query->whereDate('registrado_at', '>=', $v))
                        ->when($data['hasta'] ?? null, fn ($query, $v) => $query->whereDate('registrado_at', '<=', $v))),
            ])
            ->recordActions([
                Action::make('justificante')->label('Justificante')->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Registro $r) => $r->justificante ? route('gestion.documentos.descargar', $r->justificante) : null, shouldOpenInNewTab: true)
                    ->visible(fn (Registro $r) => $r->justificante_id !== null),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarRegistros::route('/')];
    }
}
