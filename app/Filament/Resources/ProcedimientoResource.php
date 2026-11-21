<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcedimientoResource\Pages;
use App\Filament\Support\Permiso;
use App\Models\Procedimiento;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProcedimientoResource extends Resource
{
    protected static ?string $model = Procedimiento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Catálogo de trámites';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'procedimiento';

    protected static ?string $pluralModelLabel = 'Catálogo de trámites';

    protected static ?int $navigationSort = 10;

    public const TIPOS_CAMPO = [
        'texto' => 'Texto corto', 'texto_largo' => 'Texto largo', 'email' => 'Correo electrónico', 'telefono' => 'Teléfono',
        'fecha' => 'Fecha', 'numero' => 'Número', 'nif' => 'NIF/NIE', 'opcion' => 'Lista de opciones', 'si_no' => 'Casilla sí/no',
    ];

    public static function canViewAny(): bool
    {
        return Permiso::tiene('catalogo.gestionar');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ficha del trámite')->columns(2)->schema([
                TextInput::make('codigo')->label('Código')->required()->unique(ignoreRecord: true)->maxLength(40)
                    ->helperText('Aparece en la dirección web del trámite. Solo letras, números y guiones.')->regex('/^[A-Za-z0-9-]+$/'),
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(255),
                TextInput::make('resumen')->label('Resumen')->maxLength(500)->columnSpanFull(),
                Textarea::make('informacion')->label('Información previa')->rows(5)->columnSpanFull()
                    ->helperText('Qué es, quién puede pedirlo y qué pasa después. Lenguaje claro.'),
                TextInput::make('plazo_resolucion_dias')->label('Plazo máximo de resolución (días)')->numeric()->minValue(1),
                TextInput::make('orden')->label('Orden en el catálogo')->numeric()->default(0),
                Toggle::make('requiere_tasa')->label('Lleva tasa'),
                Toggle::make('permite_representacion')->label('Admite representación')->default(true),
                Toggle::make('activo')->label('Publicado en la sede')->default(true),
            ]),
            Section::make('Formulario')->description('Datos que la persona tiene que rellenar.')->schema([
                Repeater::make('definicion.campos')->label('Campos')->defaultItems(0)->reorderable()->collapsible()
                    ->itemLabel(fn (array $state) => $state['etiqueta'] ?? null)
                    ->schema([
                        TextInput::make('clave')->label('Clave interna')->required()->regex('/^[a-z0-9_]+$/')->helperText('Minúsculas y guion bajo.'),
                        TextInput::make('etiqueta')->label('Pregunta o etiqueta')->required(),
                        Select::make('tipo')->label('Tipo')->options(self::TIPOS_CAMPO)->default('texto')->required()->live(),
                        Toggle::make('obligatorio')->label('Obligatorio')->default(true),
                        TextInput::make('ayuda')->label('Texto de ayuda')->columnSpanFull(),
                        TagsInput::make('opciones')->label('Opciones')->visible(fn (Get $get) => $get('tipo') === 'opcion')->columnSpanFull(),
                    ])->columns(2),
            ]),
            Section::make('Documentos')->description('Documentos que la persona tiene que aportar.')->schema([
                Repeater::make('definicion.documentos')->label('Documentos')->defaultItems(0)->reorderable()->collapsible()
                    ->itemLabel(fn (array $state) => $state['nombre'] ?? null)
                    ->schema([
                        TextInput::make('clave')->label('Clave interna')->required()->regex('/^[a-z0-9_]+$/'),
                        TextInput::make('nombre')->label('Nombre del documento')->required(),
                        Toggle::make('obligatorio')->label('Obligatorio')->default(true),
                        TextInput::make('max_mb')->label('Tamaño máximo (MB)')->numeric()->default(10)->minValue(1)->maxValue(50),
                        CheckboxList::make('formatos')->label('Formatos admitidos')->options(['pdf' => 'PDF', 'jpg' => 'JPG', 'jpeg' => 'JPEG', 'png' => 'PNG', 'xml' => 'XML'])->default(['pdf'])->columns(5)->columnSpanFull(),
                        TextInput::make('ayuda')->label('Texto de ayuda')->columnSpanFull(),
                    ])->columns(2),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orden')->label('Orden')->sortable(),
                TextColumn::make('codigo')->label('Código')->searchable(),
                TextColumn::make('nombre')->label('Nombre')->searchable()->wrap(),
                TextColumn::make('expedientes_count')->label('Expedientes')->counts('expedientes'),
                IconColumn::make('requiere_tasa')->label('Tasa')->boolean(),
                IconColumn::make('activo')->label('Publicado')->boolean(),
            ])
            ->defaultSort('orden')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListarProcedimientos::route('/'),
            'create' => Pages\CrearProcedimiento::route('/crear'),
            'edit' => Pages\EditarProcedimiento::route('/{record}/editar'),
        ];
    }
}
