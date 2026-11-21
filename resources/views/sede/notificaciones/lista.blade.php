@extends('layouts.sede', ['titulo' => 'Buzón de notificaciones'])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li aria-current="page">Notificaciones</li></ol></nav>
    <h1>Buzón de notificaciones</h1>
    <p>Las notificaciones se entienden practicadas cuando accede a su contenido. Si no lo hace en 10 días naturales desde que están a su disposición, se entienden rechazadas.</p>
    @if($notificaciones->isEmpty())
        <p>No tiene notificaciones.</p>
    @else
        <div class="tabla-desplazable"><table class="datos">
            <thead><tr><th scope="col">Asunto</th><th scope="col">Expediente</th><th scope="col">Puesta a disposición</th><th scope="col">Estado</th></tr></thead>
            <tbody>
            @foreach($notificaciones as $n)
                <tr>
                    <td><a href="{{ route('sede.notificaciones.ver', $n) }}">{{ $n->asunto }}</a></td>
                    <td>{{ $n->expediente?->numero }}</td>
                    <td>{{ $n->puesta_disposicion_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($n->estado === 'pendiente')<span class="estado warning">Sin abrir · hasta el {{ $n->venceAt()->format('d/m/Y') }}</span>
                        @elseif($n->estado === 'accedida')<span class="estado success">Abierta el {{ $n->accedida_at->format('d/m/Y') }}</span>
                        @else<span class="estado danger">Rechazada por plazo</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $notificaciones->links() }}
    @endif
@endsection
