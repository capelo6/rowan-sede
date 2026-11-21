@if($expedientes->isEmpty())
    <p>Todavía no tiene expedientes. Puede iniciar uno desde el <a href="{{ route('sede.inicio') }}">catálogo de trámites</a>.</p>
@else
    <div class="tabla-desplazable">
        <table class="datos">
            <caption class="oculto-visual">Expedientes</caption>
            <thead><tr><th scope="col">Expediente</th><th scope="col">Trámite</th><th scope="col">Estado</th><th scope="col">Última actualización</th></tr></thead>
            <tbody>
            @foreach($expedientes as $e)
                <tr>
                    <td>
                        @if($e->estado === \App\Enums\EstadoExpediente::Borrador)
                            <a href="{{ route('sede.solicitudes.editar', $e) }}">Borrador sin enviar</a>
                        @else
                            <a href="{{ route('sede.expedientes.ver', $e) }}">{{ $e->numero }}</a>
                        @endif
                    </td>
                    <td>{{ $e->procedimiento->nombre }}</td>
                    <td><span class="estado {{ $e->estado->color() }}">{{ $e->estado->etiqueta() }}</span></td>
                    <td>{{ $e->updated_at->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
