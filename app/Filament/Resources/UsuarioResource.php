<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UsuarioResource\Pages\GestionarUsuarios;
use App\Filament\Support\Permiso;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use UnitEnum;

class UsuarioResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'personal';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Permiso::tiene('usuarios.gestionar');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required(),
            TextInput::make('email')->label('Correo')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->label('Contraseña')->password()->revealable()->rule('min:12')
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('Mínimo 12 caracteres. Déjela vacía para no cambiarla.'),
            Toggle::make('activo')->label('Activo')->default(true),
            CheckboxList::make('roles')->label('Roles')->relationship('roles', 'name')
                ->getOptionLabelFromRecordUsing(fn (Role $r) => ['administracion' => 'Administración', 'tramitacion' => 'Tramitación', 'consulta' => 'Consulta'][$r->name] ?? $r->name)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                IconColumn::make('app_authentication_secret')->label('2FA')->state(fn (User $u) => filled($u->app_authentication_secret))->boolean(),
                IconColumn::make('activo')->label('Activo')->boolean(),
                TextColumn::make('ultimo_acceso_at')->label('Último acceso')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('reiniciar2fa')->label('Reiniciar 2FA')->icon('heroicon-o-key')->color('gray')->requiresConfirmation()
                    ->visible(fn (User $u) => filled($u->app_authentication_secret))
                    ->action(fn (User $u) => $u->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => GestionarUsuarios::route('/')];
    }
}
