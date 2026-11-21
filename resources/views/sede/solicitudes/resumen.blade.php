@extends('layouts.sede', ['titulo' => 'Resumen de la solicitud'])

@push('scripts')
    @unless($simulado)
        <script src="{{ asset(ltrim(config('sede.acceso.autoscript'), '/')) }}" defer></script>
    @endunless
@endpush

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li><a href="{{ route('sede.solicitudes.editar', $expediente) }}">Solicitud</a></li><li aria-current="page">Resumen</li></ol></nav>
    <h1>Revise su solicitud</h1>
    <ol class="pasos" aria-label="Pasos de la solicitud">
        <li>Datos y documentos</li>
        <li aria-current="step">Resumen</li>
        <li>Firma y registro</li>
    </ol>

    <section class="tarjeta" aria-labelledby="t-datos">
        <h2 id="t-datos">{{ $expediente->procedimiento->nombre }}</h2>
        <dl class="ficha">
            <dt>Presenta</dt><dd>{{ $expediente->presentador->nombreCompleto() }} ({{ $expediente->presentador->nif }})</dd>
            @if($expediente->titular_id !== $expediente->presentador_id)
                <dt>En representación de</dt><dd>{{ $expediente->titular->nombreCompleto() }} ({{ $expediente->titular->nif }})</dd>
            @endif
            @foreach($expediente->procedimiento->campos() as $campo)
                @php($v = $expediente->datos[$campo['clave']] ?? '')
                <dt>{{ $campo['etiqueta'] }}</dt>
                <dd>{{ ($campo['tipo'] ?? '') === 'si_no' ? ($v ? 'Sí' : 'No') : ($v !== '' ? $v : '—') }}</dd>
            @endforeach
        </dl>
        <p><a href="{{ route('sede.solicitudes.editar', $expediente) }}">Modificar los datos</a></p>
    </section>

    <section class="tarjeta" aria-labelledby="t-docs">
        <h2 id="t-docs">Documentos que aporta</h2>
        @if($documentos->isEmpty())
            <p>Ninguno.</p>
        @else
            <ul>@foreach($documentos as $d)<li>{{ $d->nombre }} ({{ $d->tamanoLegible() }})</li>@endforeach</ul>
        @endif
    </section>

    <section class="tarjeta" aria-labelledby="t-firma">
        <h2 id="t-firma">Firmar y presentar</h2>
        <p>Al pulsar el botón se generará el documento de la solicitud y lo firmará con su certificado en AutoFirma. Después la registraremos y le daremos el justificante con el número, la fecha y la hora de entrada.</p>
        <form id="form-presentar" method="post" action="{{ route('sede.solicitudes.presentar', $expediente) }}">
            @csrf
            <input type="hidden" name="documento" value="">
            <input type="hidden" name="firma" value="">
            <input type="hidden" name="simulada" value="">
            <div class="acciones">
                <button type="button" class="boton" id="firmar-presentar" data-preparar="{{ route('sede.solicitudes.preparar', $expediente) }}" data-simulada="{{ $simulado ? '1' : '0' }}">
                    {{ $simulado ? 'Presentar (firma simulada de demostración)' : 'Firmar y presentar' }}
                </button>
            </div>
            <p id="estado-firma" role="status" aria-live="polite"></p>
        </form>
        <noscript><p class="alerta error">Para firmar necesita tener JavaScript activado.</p></noscript>
    </section>
@endsection
