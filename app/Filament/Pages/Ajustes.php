<?php

namespace App\Filament\Pages;

use App\Filament\Support\Permiso;
use App\Models\Ajuste;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Datos del organismo, declaración de accesibilidad y textos legales de la sede. */
class Ajustes extends Page
{
    protected string $view = 'filament.pages.ajustes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $title = 'Ajustes de la sede';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public ?array $datos = [];

    private const CLAVES = [
        'organismo.nombre', 'organismo.nif', 'organismo.direccion', 'organismo.email', 'organismo.telefono', 'organismo.web',
        'accesibilidad.situacion', 'accesibilidad.contenido_no_accesible', 'accesibilidad.fecha', 'accesibilidad.metodo',
        'legal.aviso_legal', 'legal.privacidad',
    ];

    public static function canAccess(): bool
    {
        return Permiso::tiene('usuarios.gestionar');
    }

    public function mount(): void
    {
        $valores = [];
        foreach (self::CLAVES as $clave) {
            data_set($valores, $clave, Ajuste::obtener($clave, config('sede.'.$clave)));
        }
        $this->form->fill($valores);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('datos')->components([
            Section::make('Organismo')->columns(2)->schema([
                TextInput::make('organismo.nombre')->label('Nombre')->required(),
                TextInput::make('organismo.nif')->label('NIF'),
                TextInput::make('organismo.direccion')->label('Dirección')->columnSpanFull(),
                TextInput::make('organismo.email')->label('Correo de contacto')->email(),
                TextInput::make('organismo.telefono')->label('Teléfono'),
                TextInput::make('organismo.web')->label('Web corporativa')->url(),
            ]),
            Section::make('Declaración de accesibilidad (RD 1112/2018)')->columns(2)->schema([
                TextInput::make('accesibilidad.situacion')->label('Situación de cumplimiento')->placeholder('parcialmente conforme'),
                TextInput::make('accesibilidad.fecha')->label('Fecha de la declaración')->placeholder('dd/mm/aaaa'),
                TextInput::make('accesibilidad.metodo')->label('Método de evaluación')->columnSpanFull(),
                Textarea::make('accesibilidad.contenido_no_accesible')->label('Contenido no accesible')->rows(4)->columnSpanFull(),
            ]),
            Section::make('Textos legales')->description('Si se dejan vacíos, se usa el texto genérico.')->schema([
                Textarea::make('legal.aviso_legal')->label('Aviso legal')->rows(6),
                Textarea::make('legal.privacidad')->label('Política de privacidad')->rows(8),
            ]),
        ]);
    }

    public function guardar(): void
    {
        $estado = $this->form->getState();
        foreach (self::CLAVES as $clave) {
            Ajuste::guardar($clave, data_get($estado, $clave));
        }
        activity('ajustes')->causedBy(auth()->user())->event('cambio')->log('Ajustes de la sede actualizados');
        Notification::make()->title('Ajustes guardados.')->success()->send();
    }
}
