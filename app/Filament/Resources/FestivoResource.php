<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FestivoResource\Pages\GestionarFestivos;
use App\Filament\Support\Permiso;
use App\Models\Festivo;
use App\Sede\Plazos\Calendario;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FestivoResource extends Resource
{
    protected static ?string $model = Festivo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Calendario de festivos';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'festivo';

    protected static ?string $pluralModelLabel = 'Calendario de festivos';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return Permiso::tiene('catalogo.gestionar');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('fecha')->label('Fecha')->required()->native(false)->displayFormat('d/m/Y'),
            Select::make('ambito')->label('Ámbito')->options(Festivo::AMBITOS)->default('local')->required(),
            TextInput::make('descripcion')->label('Descripción')->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->label('Fecha')->date('d/m/Y (l)')->sortable(),
                TextColumn::make('descripcion')->label('Descripción')->searchable(),
                TextColumn::make('ambito')->label('Ámbito')->formatStateUsing(fn ($state) => Festivo::AMBITOS[$state] ?? $state)->badge(),
            ])
            ->defaultSort('fecha', 'desc')
            ->recordActions([
                EditAction::make()->after(fn () => app(Calendario::class)->olvidar()),
                DeleteAction::make()->after(fn () => app(Calendario::class)->olvidar()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarFestivos::route('/')];
    }
}
