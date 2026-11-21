@extends('layouts.sede', ['titulo' => 'Acceder'])

@push('scripts')
    <script src="{{ asset(ltrim(config('sede.acceso.autoscript'), '/')) }}" defer></script>
@endpush

@section('contenido')
    <h1>Acceder con certificado electrónico</h1>
    <p class="entradilla">Puede usar el certificado de la FNMT, el DNI electrónico o cualquier otro certificado cualificado. Si actúa en nombre de una empresa, use su certificado de representante.</p>

    <section class="tarjeta" aria-labelledby="titulo-cert">
        <h2 id="titulo-cert">Con AutoFirma</h2>
        <ol>
            <li>Tenga instalada la aplicación gratuita <a href="https://firmaelectronica.gob.es/Home/Descargas.html" rel="noopener">AutoFirma</a>.</li>
            <li>Pulse «Acceder con certificado». Se abrirá AutoFirma y le pedirá que elija su certificado.</li>
            <li>Con eso comprobamos su identidad. No se guarda ninguna firma.</li>
        </ol>
        <form id="form-acceso" method="post" action="{{ route('sede.acceso.acceder') }}">
            @csrf
            <input type="hidden" name="firma" value="">
            <div class="acciones">
                <button type="button" class="boton" id="acceder-certificado" data-reto="{{ route('sede.acceso.reto') }}">Acceder con certificado</button>
            </div>
            <p id="estado-acceso" role="status" aria-live="polite"></p>
        </form>
        <noscript><p class="alerta error">Para acceder con certificado necesita tener JavaScript activado.</p></noscript>
        <p class="ayuda">Comprobamos que el certificado está vigente, que lo emitió un prestador de confianza y que no está revocado.</p>
    </section>

    @if($simulado)
        <section class="tarjeta" aria-labelledby="titulo-demo">
            <h2 id="titulo-demo">Acceso de demostración</h2>
            <p class="alerta">Este acceso sin certificado solo existe en el entorno de demostración. En producción está desactivado.</p>
            <form method="post" action="{{ route('sede.acceso.simulado') }}">
                @csrf
                <div class="campo"><label for="nif">NIF</label><input type="text" id="nif" name="nif" value="{{ old('nif', '12345678Z') }}" autocomplete="off" required></div>
                <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" value="{{ old('nombre', 'María') }}" required></div>
                <div class="campo"><label for="apellidos">Apellidos</label><input type="text" id="apellidos" name="apellidos" value="{{ old('apellidos', 'Pérez Gómez') }}" required></div>
                <button class="boton secundario" type="submit">Entrar en modo demostración</button>
            </form>
        </section>
    @endif
@endsection
