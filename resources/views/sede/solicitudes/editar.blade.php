@extends('layouts.sede', ['titulo' => 'Solicitud: '.$expediente->procedimiento->nombre])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li aria-current="page">Solicitud</li></ol></nav>
    <h1>{{ $expediente->procedimiento->nombre }}</h1>
    <ol class="pasos" aria-label="Pasos de la solicitud">
        <li aria-current="step">Datos y documentos</li>
        <li>Resumen</li>
        <li>Firma y registro</li>
    </ol>
    @if($expediente->titular_id !== $expediente->presentador_id)
        <p class="alerta">Presenta esta solicitud en representación de <strong>{{ $expediente->titular->nombreCompleto() }}</strong> ({{ $expediente->titular->nif }}).</p>
    @endif

    <form method="post" action="{{ route('sede.solicitudes.guardar', $expediente) }}" data-autoguardado="{{ route('sede.solicitudes.guardar', $expediente) }}" class="tarjeta" novalidate>
        @csrf
        <h2>Datos de la solicitud</h2>
        @forelse($expediente->procedimiento->campos() as $campo)
            @include('sede.partials.campo', ['campo' => $campo, 'valores' => $expediente->datos ?? []])
        @empty
            <p>Este trámite no pide datos adicionales.</p>
        @endforelse
        <p id="estado-guardado" class="estado-guardado" role="status" aria-live="polite">Los cambios se guardan automáticamente como borrador.</p>
        <div class="acciones">
            <button class="boton secundario" type="submit" name="siguiente" value="">Guardar borrador</button>
        </div>
    </form>

    <section class="tarjeta" aria-labelledby="titulo-docs">
        <h2 id="titulo-docs">Documentos</h2>
        @forelse($expediente->procedimiento->documentosRequeridos() as $doc)
            @php($subidos = $documentos[$doc['clave']] ?? collect())
            <div id="doc-{{ $doc['clave'] }}" class="campo" style="border-top:1px solid var(--borde);padding-top:1rem;">
                <h3 style="margin-top:0;">{{ $doc['nombre'] }} @if($doc['obligatorio'] ?? false)<span class="obligatorio">(obligatorio)</span>@else<span class="ayuda" style="display:inline;">(opcional)</span>@endif</h3>
                @if(!empty($doc['ayuda']))<p class="ayuda">{{ $doc['ayuda'] }}</p>@endif
                @error('doc-'.$doc['clave'])<span class="error-campo">{{ $message }}</span>@enderror
                @if($subidos->isNotEmpty())
                    <ul>
                        @foreach($subidos as $d)
                            <li>
                                {{ $d->nombre_original ?? $d->nombre }} ({{ $d->tamanoLegible() }})
                                <form method="post" action="{{ route('sede.solicitudes.documentos.quitar', [$expediente, $d]) }}" style="display:inline;">
                                    @csrf @method('delete')
                                    <button type="submit" class="boton secundario" style="padding:0.2rem 0.6rem;" aria-label="Quitar {{ $d->nombre_original ?? $d->nombre }}">Quitar</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('sede.solicitudes.documentos', $expediente) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clave" value="{{ $doc['clave'] }}">
                    <label for="f-{{ $doc['clave'] }}">{{ $subidos->isEmpty() ? 'Elegir el fichero' : 'Añadir otro fichero' }}</label>
                    <span class="ayuda" id="ayuda-f-{{ $doc['clave'] }}">{{ strtoupper(implode(', ', $doc['formatos'] ?? config('sede.documentos.formatos'))) }}, hasta {{ $doc['max_mb'] ?? config('sede.documentos.max_mb') }} MB.</span>
                    <input type="file" id="f-{{ $doc['clave'] }}" name="fichero" aria-describedby="ayuda-f-{{ $doc['clave'] }}"
                           accept="{{ collect($doc['formatos'] ?? config('sede.documentos.formatos'))->map(fn ($f) => '.'.$f)->implode(',') }}" required>
                    <button class="boton secundario" type="submit">Subir</button>
                </form>
            </div>
        @empty
            <p>No tiene que aportar documentos.</p>
        @endforelse
    </section>

    <div class="acciones">
        <form method="post" action="{{ route('sede.solicitudes.guardar', $expediente) }}">
            @csrf
            <input type="hidden" name="siguiente" value="resumen">
            <button class="boton" type="submit">Continuar al resumen</button>
        </form>
        <form method="post" action="{{ route('sede.solicitudes.descartar', $expediente) }}" data-confirmar="¿Seguro que quiere descartar este borrador? Se borrarán los datos y documentos.">
            @csrf @method('delete')
            <button class="boton secundario" type="submit">Descartar borrador</button>
        </form>
    </div>
@endsection
