@extends('layouts.sede', ['titulo' => 'Trámites'])

@section('contenido')
    <h1>Trámites en línea</h1>
    <p class="entradilla">Presente sus solicitudes, aporte documentación, consulte el estado de sus expedientes, reciba notificaciones y pague tasas sin desplazarse.</p>

    @guest('sede')
        <div class="alerta" role="note">
            Para presentar una solicitud necesita un <strong>certificado electrónico</strong> (de la FNMT, el DNI electrónico u otro cualificado) y la aplicación gratuita <strong>AutoFirma</strong>.
            <a href="{{ route('sede.acceso') }}">Acceder con certificado</a>.
        </div>
    @endguest

    <h2>Catálogo de trámites</h2>
    @if($procedimientos->isEmpty())
        <p>Todavía no hay trámites disponibles.</p>
    @else
        <ul class="rejilla" role="list" style="list-style:none;padding:0;margin:0;">
            @foreach($procedimientos as $p)
                <li class="tarjeta">
                    <h3><a href="{{ route('sede.tramite', $p) }}">{{ $p->nombre }}</a></h3>
                    @if($p->resumen)<p>{{ $p->resumen }}</p>@endif
                    <a class="boton secundario" href="{{ route('sede.tramite', $p) }}" aria-label="Ver información y documentación de «{{ $p->nombre }}»">Información y documentación</a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
