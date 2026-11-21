@extends('layouts.sede', ['titulo' => 'Mis documentos'])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li aria-current="page">Mis documentos</li></ol></nav>
    <h1>Mis documentos</h1>
    <p>Todos los documentos que ha presentado y los que ha recibido. Los de una notificación aparecen cuando la abre.</p>
    @if($documentos->isEmpty())
        <p>No hay documentos.</p>
    @else
        <div class="tabla-desplazable">
            <table class="datos">
                <caption class="oculto-visual">Documentos</caption>
                <thead><tr><th scope="col">Documento</th><th scope="col">Tipo</th><th scope="col">Expediente</th><th scope="col">Fecha</th></tr></thead>
                <tbody>
                @foreach($documentos as $d)
                    <tr>
                        <td><a href="{{ route('sede.documentos.descargar', $d) }}">{{ $d->nombre }}</a> <span class="ayuda">{{ $d->tamanoLegible() }}</span></td>
                        <td>{{ $d->etiquetaCategoria() }}</td>
                        <td>@if($d->expediente)<a href="{{ route('sede.expedientes.ver', $d->expediente) }}">{{ $d->expediente->referencia() }}</a>@endif</td>
                        <td>{{ $d->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $documentos->links() }}
    @endif
@endsection
