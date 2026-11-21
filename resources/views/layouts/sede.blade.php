<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($titulo) ? $titulo.' · ' : '' }}Sede electrónica · {{ \App\Sede\Organismo::nombre() }}</title>
    <link rel="stylesheet" href="{{ asset('css/sede.css') }}?v={{ @filemtime(public_path('css/sede.css')) }}">
    @stack('cabecera')
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>

<header class="cabecera">
    <div class="contenedor">
        <a class="marca" href="{{ route('sede.inicio') }}">
            <strong>{{ \App\Sede\Organismo::nombre() }}</strong>
            <span>Sede electrónica</span>
        </a>
        <nav class="menu" aria-label="Menú principal">
            <ul>
                <li><a href="{{ route('sede.inicio') }}" @if(request()->routeIs('sede.inicio', 'sede.tramite')) aria-current="page" @endif>Trámites</a></li>
                @auth('sede')
                    @php($pendientes = auth('sede')->user()->notificaciones()->where('estado', 'pendiente')->count())
                    <li><a href="{{ route('sede.carpeta') }}" @if(request()->routeIs('sede.carpeta', 'sede.expedientes*', 'sede.documentos', 'sede.solicitudes*')) aria-current="page" @endif>Mi carpeta</a></li>
                    <li><a href="{{ route('sede.notificaciones') }}" @if(request()->routeIs('sede.notificaciones*')) aria-current="page" @endif>Notificaciones @if($pendientes)<span class="insignia">{{ $pendientes }}<span class="oculto-visual"> sin abrir</span></span>@endif</a></li>
                    <li>
                        <form method="post" action="{{ route('sede.salir') }}">@csrf<button type="submit">Salir</button></form>
                    </li>
                @else
                    <li><a href="{{ route('sede.verificar') }}">Verificar documentos</a></li>
                    <li><a href="{{ route('sede.acceso') }}" @if(request()->routeIs('sede.acceso')) aria-current="page" @endif>Acceder con certificado</a></li>
                @endauth
            </ul>
        </nav>
    </div>
</header>

<main id="contenido" tabindex="-1">
    <div class="contenedor">
        @if(session('aviso'))
            <div class="alerta ok" role="status">{{ session('aviso') }}</div>
        @endif
        @if($errors->any())
            <div class="alerta error" role="alert">
                <strong>Revise lo siguiente:</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @yield('contenido')
    </div>
</main>

<footer class="pie">
    <div class="contenedor">
        <ul>
            <li><a href="{{ route('sede.accesibilidad') }}">Accesibilidad</a></li>
            <li><a href="{{ route('sede.aviso-legal') }}">Aviso legal</a></li>
            <li><a href="{{ route('sede.privacidad') }}">Privacidad</a></li>
            <li><a href="{{ route('sede.cookies') }}">Cookies</a></li>
            <li><a href="{{ route('sede.verificar') }}">Verificar un documento (CSV)</a></li>
        </ul>
        <p>Sede electrónica de {{ \App\Sede\Organismo::nombre() }}@if(\App\Sede\Organismo::nif()) · NIF {{ \App\Sede\Organismo::nif() }}@endif</p>
        <p>Fecha y hora oficial de la sede: <time datetime="{{ now()->toIso8601String() }}">{{ now()->format('d/m/Y H:i') }}</time> (hora peninsular).</p>
    </div>
</footer>

@stack('scripts')
<script src="{{ asset('js/sede.js') }}?v={{ @filemtime(public_path('js/sede.js')) }}" defer></script>
</body>
</html>
