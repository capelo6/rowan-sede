@extends('layouts.sede', ['titulo' => $notificacion->asunto])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li><a href="{{ route('sede.notificaciones') }}">Notificaciones</a></li><li aria-current="page">{{ $notificacion->asunto }}</li></ol></nav>
    <h1>{{ $notificacion->asunto }}</h1>
    <dl class="ficha tarjeta">
        @if($notificacion->expediente)<dt>Expediente</dt><dd><a href="{{ route('sede.expedientes.ver', $notificacion->expediente) }}">{{ $notificacion->expediente->numero }}</a></dd>@endif
        <dt>Puesta a disposición</dt><dd>{{ $notificacion->puesta_disposicion_at->format('d/m/Y H:i:s') }}</dd>
        @if($notificacion->estado === 'pendiente')
            <dt>Plazo para abrirla</dt><dd>Hasta el {{ $notificacion->venceAt()->format('d/m/Y') }}</dd>
        @elseif($notificacion->estado === 'accedida')
            <dt>Abierta</dt><dd>{{ $notificacion->accedida_at->format('d/m/Y H:i:s') }}</dd>
        @else
            <dt>Rechazada</dt><dd>{{ $notificacion->rechazada_at->format('d/m/Y H:i:s') }} (no se abrió en plazo)</dd>
        @endif
    </dl>

    @if($notificacion->estado === 'pendiente')
        <section class="tarjeta">
            <h2>Acceder al contenido</h2>
            <p>Al acceder al contenido, la notificación quedará <strong>practicada</strong> con la fecha y la hora de este momento (art. 43 de la Ley 39/2015). Recibirá un justificante.</p>
            <form method="post" action="{{ route('sede.notificaciones.acceder', $notificacion) }}">
                @csrf
                <button class="boton" type="submit">Acceder al contenido de la notificación</button>
            </form>
        </section>
    @else
        <section class="tarjeta">
            <h2>Documentos</h2>
            <ul>
                @if($notificacion->documento)<li><a href="{{ route('sede.documentos.descargar', $notificacion->documento) }}">{{ $notificacion->documento->nombre }}</a></li>@endif
                @if($notificacion->justificante)<li><a href="{{ route('sede.documentos.descargar', $notificacion->justificante) }}">{{ $notificacion->justificante->nombre }}</a></li>@endif
            </ul>
            @if($notificacion->cuerpo)<p>{!! nl2br(e($notificacion->cuerpo)) !!}</p>@endif
        </section>
    @endif
@endsection
