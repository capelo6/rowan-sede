<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlantillaResource\Pages\GestionarPlantillas;
use App\Filament\Support\Permiso;
use App\Models\Plantilla;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlantillaResource extends Resource
{
    protected static ?string $model = Plantilla::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $modelLabel = 'plantilla';

    protected static ?string $pluralModelLabel = 'plantillas de documentos';

    protected static ?int $navigationSort = 40;

    public static function canViewAny(): bool
    {
        return Permiso::tiene('catalogo.gestionar');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('codigo')->label('Código')->required()->unique(ignoreRecord: true)->maxLength(60),
            TextInput::make('nombre')->label('Nombre')->required(),
            Select::make('tipo')->label('Tipo')->options(Plantilla::TIPOS)->required(),
            TextInput::make('asunto')->label('Asunto del documento'),
            RichEditor::make('cuerpo')->label('Texto')->required()->columnSpanFull()
                ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'h3', 'undo', 'redo'])
                ->helperText('Marcadores: {{ expediente.numero }}, {{ expediente.procedimiento }}, {{ expediente.fecha_presentacion }}, {{ titular.nombre }}, {{ titular.nif }}, {{ organismo.nombre }}, {{ fecha }}, {{ motivo }}.'),
            Toggle::make('activa')->label('Activa')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable(),
                TextColumn::make('nombre')->label('Nombre')->searchable(),
                TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn ($state) => Plantilla::TIPOS[$state] ?? $state)->badge(),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([EditAction::make()->slideOver()]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarPlantillas::route('/')];
    }
}
