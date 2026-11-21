@extends('layouts.sede', ['titulo' => $primerAcceso ? 'Primer acceso' : 'Mis datos'])

@section('contenido')
    <h1>{{ $primerAcceso ? 'Bienvenida o bienvenido a la sede' : 'Mis datos' }}</h1>
    @if($primerAcceso)
        <p class="entradilla">Hemos tomado su nombre y su NIF del certificado. Para avisarle de cada novedad necesitamos un correo electrónico.</p>
    @endif

    <dl class="ficha tarjeta">
        <dt>Nombre</dt><dd>{{ $persona->nombreCompleto() }}</dd>
        <dt>NIF</dt><dd>{{ $persona->nif }}</dd>
        @if($persona->ultimo_certificado)
            <dt>Certificado</dt><dd>{{ $persona->ultimo_certificado['emisor'] ?? '' }}@if(!empty($persona->ultimo_certificado['valido_hasta'])), válido hasta el {{ \Carbon\Carbon::parse($persona->ultimo_certificado['valido_hasta'])->format('d/m/Y') }}@endif</dd>
        @endif
    </dl>

    <form method="post" action="{{ route('sede.perfil.guardar') }}" class="tarjeta" novalidate>
        @csrf
        <h2>Datos de contacto</h2>
        @foreach([
            ['email', 'Correo electrónico', 'email', true, 'email', 'Le avisaremos aquí de cada novedad. Nunca enviamos el contenido de las notificaciones por correo.'],
            ['telefono', 'Teléfono', 'tel', false, 'tel', null],
            ['direccion', 'Dirección postal', 'text', false, 'street-address', null],
            ['codigo_postal', 'Código postal', 'text', false, 'postal-code', null],
            ['municipio', 'Municipio', 'text', false, 'address-level2', null],
            ['provincia', 'Provincia', 'text', false, 'address-level1', null],
        ] as [$campo, $etiqueta, $tipo, $obligatorio, $autocompletar, $ayuda])
            <div class="campo">
                <label for="{{ $campo }}">{{ $etiqueta }} @if($obligatorio)<span class="obligatorio">(obligatorio)</span>@endif</label>
                @if($ayuda)<span class="ayuda" id="ayuda-{{ $campo }}">{{ $ayuda }}</span>@endif
                @error($campo)<span class="error-campo" id="error-{{ $campo }}">{{ $message }}</span>@enderror
                <input type="{{ $tipo }}" id="{{ $campo }}" name="{{ $campo }}" value="{{ old($campo, $persona->$campo) }}" autocomplete="{{ $autocompletar }}"
                       @if($obligatorio) required aria-required="true" @endif
                       @error($campo) aria-invalid="true" aria-describedby="error-{{ $campo }}" @elseif($ayuda) aria-describedby="ayuda-{{ $campo }}" @enderror>
            </div>
        @endforeach

        <h2>Notificaciones y privacidad</h2>
        <div class="casilla">
            <input type="checkbox" id="notificaciones_electronicas" name="notificaciones_electronicas" value="1" @checked(old('notificaciones_electronicas', $persona->consentimiento_notificaciones_at !== null || $primerAcceso))>
            <label for="notificaciones_electronicas">Quiero recibir las notificaciones por medios electrónicos en esta sede. Si no abro una notificación en 10 días naturales, se entenderá rechazada.</label>
        </div>
        <div class="casilla">
            <input type="checkbox" id="privacidad" name="privacidad" value="1" @checked(old('privacidad', $persona->privacidad_aceptada_at !== null)) required aria-required="true" @error('privacidad') aria-invalid="true" aria-describedby="error-privacidad" @enderror>
            <label for="privacidad">He leído la <a href="{{ route('sede.privacidad') }}">política de privacidad</a>. <span class="obligatorio">(obligatorio)</span></label>
        </div>
        @error('privacidad')<span class="error-campo" id="error-privacidad">{{ $message }}</span>@enderror

        <div class="acciones"><button class="boton" type="submit">Guardar</button></div>
    </form>
@endsection
