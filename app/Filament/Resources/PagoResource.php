<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PagoResource\Pages\ListarPagos;
use App\Filament\Support\Permiso;
use App\Models\Pago;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PagoResource extends Resource
{
    protected static ?string $model = Pago::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Registro y pagos';

    protected static ?string $modelLabel = 'pago';

    protected static ?string $pluralModelLabel = 'pagos';

    protected static ?int $navigationSort = 2;

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
                TextColumn::make('pagado_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('liquidacion.expediente.numero')->label('Expediente')->searchable()
                    ->url(fn (Pago $p) => ExpedienteResource::getUrl('view', ['record' => $p->liquidacion->expediente_id])),
                TextColumn::make('liquidacion.referencia')->label('Liquidación')->searchable(),
                TextColumn::make('canal')->label('Medio')->formatStateUsing(fn ($state) => Pago::CANALES[$state] ?? $state)->badge(),
                TextColumn::make('importe')->label('Importe')->money('EUR', locale: 'es')->sortable(),
                TextColumn::make('estado')->label('Estado')->badge()->color(fn ($state) => match ($state) { 'autorizado' => 'success', 'denegado' => 'danger', default => 'gray' }),
                TextColumn::make('pedido')->label('Pedido')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('autorizacion')->label('Autorización')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('estado')->options(['autorizado' => 'Autorizado', 'iniciado' => 'Iniciado', 'denegado' => 'Denegado'])->default('autorizado'),
                SelectFilter::make('canal')->options(Pago::CANALES),
                Filter::make('fechas')->schema([DatePicker::make('desde')->label('Desde'), DatePicker::make('hasta')->label('Hasta')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($query, $v) => $query->whereDate('pagado_at', '>=', $v))
                        ->when($data['hasta'] ?? null, fn ($query, $v) => $query->whereDate('pagado_at', '<=', $v))),
            ])
            ->recordActions([
                Action::make('justificante')->label('Justificante')->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Pago $p) => $p->justificante ? route('gestion.documentos.descargar', $p->justificante) : null, shouldOpenInNewTab: true)
                    ->visible(fn (Pago $p) => $p->justificante_id !== null),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListarPagos::route('/')];
    }
}
