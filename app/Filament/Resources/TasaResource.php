<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TasaResource\Pages\GestionarTasas;
use App\Filament\Support\Permiso;
use App\Models\Tasa;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TasaResource extends Resource
{
    protected static ?string $model = Tasa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'tasa';

    protected static ?string $pluralModelLabel = 'catálogo de tasas';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        return Permiso::tiene('catalogo.gestionar');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->label('Código')->required()->maxLength(40),
            TextInput::make('ejercicio')->label('Ejercicio')->numeric()->required()->default((int) now()->format('Y')),
            TextInput::make('concepto')->label('Concepto')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('importe')->label('Importe (sin IVA)')->numeric()->required()->suffix('€')->minValue(0),
            TextInput::make('iva_porcentaje')->label('IVA')->numeric()->default(0)->suffix('%')->helperText('0 si la tasa no lleva IVA.'),
            Toggle::make('activa')->label('Activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ejercicio')->label('Ejercicio')->sortable(),
                TextColumn::make('codigo')->label('Código')->searchable(),
                TextColumn::make('concepto')->label('Concepto')->searchable()->wrap(),
                TextColumn::make('importe')->label('Importe')->money('EUR', locale: 'es')->sortable(),
                TextColumn::make('iva_porcentaje')->label('IVA')->suffix(' %'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->defaultSort('ejercicio', 'desc')
            ->filters([SelectFilter::make('ejercicio')->options(fn () => Tasa::query()->distinct()->orderByDesc('ejercicio')->pluck('ejercicio', 'ejercicio')->all())])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()->label('Copiar al ejercicio siguiente')
                    ->beforeReplicaSaved(fn (Tasa $replica) => $replica->ejercicio = $replica->ejercicio + 1),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarTasas::route('/')];
    }
}
