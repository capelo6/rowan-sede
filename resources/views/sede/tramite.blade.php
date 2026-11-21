@extends('layouts.sede', ['titulo' => $procedimiento->nombre])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.inicio') }}">Trámites</a></li><li aria-current="page">{{ $procedimiento->nombre }}</li></ol></nav>
    <h1>{{ $procedimiento->nombre }}</h1>
    @if($procedimiento->resumen)<p class="entradilla">{{ $procedimiento->resumen }}</p>@endif

    @if($procedimiento->informacion)
        <section class="tarjeta" aria-labelledby="info">
            <h2 id="info">Antes de empezar</h2>
            {!! nl2br(e($procedimiento->informacion)) !!}
        </section>
    @endif

    <section class="tarjeta" aria-labelledby="docs">
        <h2 id="docs">Documentación que necesita</h2>
        @if(empty($procedimiento->documentosRequeridos()))
            <p>No tiene que aportar documentos.</p>
        @else
            <ul>
                @foreach($procedimiento->documentosRequeridos() as $doc)
                    <li>
                        <strong>{{ $doc['nombre'] }}</strong>{{ ($doc['obligatorio'] ?? false) ? '' : ' (opcional)' }}.
                        @if(!empty($doc['ayuda'])) {{ $doc['ayuda'] }} @endif
                        <span class="ayuda">Formatos: {{ strtoupper(implode(', ', $doc['formatos'] ?? config('sede.documentos.formatos'))) }} · hasta {{ $doc['max_mb'] ?? config('sede.documentos.max_mb') }} MB.</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if($procedimiento->requiere_tasa)
            <p>Este trámite tiene una <strong>tasa</strong>. Se la indicaremos cuando revisemos su solicitud y podrá pagarla con tarjeta o Bizum.</p>
        @endif
        @if($procedimiento->plazo_resolucion_dias)
            <p>Plazo máximo para resolver: {{ $procedimiento->plazo_resolucion_dias }} días.</p>
        @endif
    </section>

    @auth('sede')
        <form method="post" action="{{ route('sede.solicitudes.crear', $procedimiento) }}" class="tarjeta">
            @csrf
            <h2>Iniciar la solicitud</h2>
            @if($procedimiento->permite_representacion && $representaciones->isNotEmpty())
                <fieldset class="campo">
                    <legend>¿En nombre de quién presenta la solicitud?</legend>
                    <div class="casilla"><input type="radio" id="rep-0" name="representacion_id" value="" checked><label for="rep-0">En mi propio nombre</label></div>
                    @foreach($representaciones as $r)
                        <div class="casilla"><input type="radio" id="rep-{{ $r->id }}" name="representacion_id" value="{{ $r->id }}"><label for="rep-{{ $r->id }}">En representación de {{ $r->representado->nombreCompleto() }} ({{ $r->representado->nif }})</label></div>
                    @endforeach
                </fieldset>
            @elseif($procedimiento->permite_representacion)
                <p class="ayuda">Si actúa en nombre de otra persona, primero <a href="{{ route('sede.autorizaciones') }}">aporte el documento de representación</a>.</p>
            @endif
            <div class="acciones"><button class="boton" type="submit">Empezar la solicitud</button></div>
            <p class="ayuda">Se guarda como borrador mientras la rellena. Puede continuar más tarde desde «Mi carpeta».</p>
        </form>
    @else
        <div class="acciones">
            <a class="boton" href="{{ route('sede.acceso') }}">Acceder con certificado para solicitarlo</a>
        </div>
    @endauth
@endsection
