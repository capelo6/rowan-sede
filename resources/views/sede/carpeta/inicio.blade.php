@extends('layouts.sede', ['titulo' => 'Mi carpeta'])

@section('contenido')
    <h1>Mi carpeta</h1>
    <p class="entradilla">Hola, {{ auth('sede')->user()->nombre ?: auth('sede')->user()->nombreCompleto() }}. Aquí tiene lo que necesita su atención.</p>

    <nav aria-label="Secciones de mi carpeta" class="acciones">
        <a class="boton secundario" href="{{ route('sede.expedientes') }}">Mis expedientes</a>
        <a class="boton secundario" href="{{ route('sede.notificaciones') }}">Buzón de notificaciones</a>
        <a class="boton secundario" href="{{ route('sede.documentos') }}">Mis documentos</a>
        <a class="boton secundario" href="{{ route('sede.autorizaciones') }}">Autorizaciones</a>
        <a class="boton secundario" href="{{ route('sede.datos') }}">Mis datos</a>
    </nav>

    <section aria-labelledby="titulo-avisos">
        <h2 id="titulo-avisos">Avisos</h2>
        @forelse($avisos as $a)
            <div class="aviso {{ $a['nivel'] }}">
                <div>
                    <span class="nivel">{{ $a['nivel'] === 'urgente' ? 'Urgente' : 'Pendiente' }}</span>
                    <p style="margin:0.2rem 0 0;">{{ $a['texto'] }}
                        @if($a['vence'])
                            @php($dias = $calendario->habilesRestantes($a['vence']))
                            <span class="dias">
                                @if($dias < 0) Plazo vencido el {{ $a['vence']->format('d/m/Y') }}.
                                @elseif($dias === 0) Vence hoy.
                                @else Quedan {{ $dias }} {{ $dias === 1 ? 'día hábil' : 'días hábiles' }} (hasta el {{ $a['vence']->format('d/m/Y') }}).
                                @endif
                            </span>
                        @endif
                    </p>
                </div>
                <a class="boton" href="{{ $a['url'] }}">{{ $a['accion'] }}</a>
            </div>
        @empty
            <div class="aviso en_orden"><div><span class="nivel">Todo en orden</span><p style="margin:0.2rem 0 0;">No tiene ninguna acción pendiente.</p></div></div>
        @endforelse
    </section>

    <section aria-labelledby="titulo-recientes">
        <h2 id="titulo-recientes">Expedientes recientes</h2>
        @include('sede.partials.tabla-expedientes', ['expedientes' => $expedientes])
        <p><a href="{{ route('sede.expedientes') }}">Ver todos mis expedientes</a></p>
    </section>
@endsection
