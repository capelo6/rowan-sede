@extends('layouts.sede', ['titulo' => 'Aportar documentación'])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li><a href="{{ route('sede.expedientes.ver', $expediente) }}">{{ $expediente->numero }}</a></li><li aria-current="page">Aportar documentación</li></ol></nav>
    <h1>Aportar la documentación requerida</h1>
    <p class="entradilla">Expediente {{ $expediente->numero }}. Solo tiene que aportar lo que se indica.
        @if($requerimiento->vence_at) Plazo: hasta el <strong>{{ $requerimiento->vence_at->format('d/m/Y') }}</strong> ({{ max(0, $calendario->habilesRestantes($requerimiento->vence_at)) }} días hábiles).@endif
    </p>
    @if($requerimiento->motivo)<div class="alerta">{!! nl2br(e($requerimiento->motivo)) !!}</div>@endif

    <form method="post" action="{{ route('sede.expedientes.subsanar.enviar', $expediente) }}" enctype="multipart/form-data" class="tarjeta" novalidate>
        @csrf
        @foreach($requerimiento->itemsDocumento() as $item)
            <div class="campo">
                <label for="f-{{ $item['clave'] }}">{{ $item['descripcion'] }} <span class="obligatorio">(obligatorio)</span></label>
                <span class="ayuda" id="ayuda-f-{{ $item['clave'] }}">{{ strtoupper(implode(', ', config('sede.documentos.formatos'))) }}, hasta {{ config('sede.documentos.max_mb') }} MB por fichero. Puede elegir varios.</span>
                @error('ficheros.'.$item['clave'])<span class="error-campo">{{ $message }}</span>@enderror
                <input type="file" multiple id="f-{{ $item['clave'] }}" name="ficheros[{{ $item['clave'] }}][]" aria-describedby="ayuda-f-{{ $item['clave'] }}" aria-required="true">
            </div>
        @endforeach
        @foreach($requerimiento->itemsDato() as $item)
            <div class="campo">
                <label for="d-{{ $item['clave'] }}">{{ $item['descripcion'] }} <span class="obligatorio">(obligatorio)</span></label>
                @error('datos.'.$item['clave'])<span class="error-campo">{{ $message }}</span>@enderror
                <textarea id="d-{{ $item['clave'] }}" name="datos[{{ $item['clave'] }}]" aria-required="true">{{ old('datos.'.$item['clave']) }}</textarea>
            </div>
        @endforeach
        <div class="acciones"><button class="boton" type="submit">Enviar la documentación</button></div>
        <p class="ayuda">La registraremos y recibirá el justificante de registro de entrada.</p>
    </form>
@endsection
