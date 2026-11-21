@extends('layouts.sede', ['titulo' => 'Expediente '.$expediente->numero])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li><a href="{{ route('sede.expedientes') }}">Mis expedientes</a></li><li aria-current="page">{{ $expediente->numero }}</li></ol></nav>
    <h1>Expediente {{ $expediente->numero }}</h1>
    <p class="entradilla">{{ $expediente->procedimiento->nombre }}</p>

    <section class="tarjeta" aria-labelledby="t-estado">
        <h2 id="t-estado">Estado: <span class="estado {{ $expediente->estado->color() }}">{{ $expediente->estado->etiqueta() }}</span></h2>
        <p>{{ $expediente->estado->explicacion() }}</p>
        @if($expediente->resultado)
            <p><strong>Resultado:</strong> {{ \App\Models\Expediente::RESULTADOS[$expediente->resultado] ?? $expediente->resultado }}.</p>
        @endif

        @if($requerimiento)
            @php($dias = $requerimiento->vence_at ? $calendario->habilesRestantes($requerimiento->vence_at) : null)
            <div class="aviso {{ $calendario->semaforo($requerimiento->vence_at) }}">
                <div>
                    <span class="nivel">{{ $calendario->semaforo($requerimiento->vence_at) === 'urgente' ? 'Urgente' : 'Pendiente' }}</span>
                    <p style="margin:0.2rem 0 0;">Tiene que aportar documentación o datos.
                        @if($dias !== null)<span class="dias">{{ $dias > 0 ? 'Quedan '.$dias.' días hábiles (hasta el '.$requerimiento->vence_at->format('d/m/Y').').' : 'Plazo vencido el '.$requerimiento->vence_at->format('d/m/Y').'.' }}</span>@endif
                    </p>
                </div>
                <a class="boton" href="{{ route('sede.expedientes.subsanar', $expediente) }}">Aportar documentación</a>
            </div>
        @endif

        @if($liquidacion)
            <div class="aviso {{ $calendario->semaforo($liquidacion->vence_at) }}" id="pago">
                <div>
                    <span class="nivel">{{ $calendario->semaforo($liquidacion->vence_at) === 'urgente' ? 'Urgente' : 'Pendiente' }}</span>
                    <p style="margin:0.2rem 0 0;">Tasa pendiente: <strong>{{ number_format((float) $liquidacion->total, 2, ',', '.') }} €</strong>
                        @if($liquidacion->vence_at)<span class="dias">· pague antes del {{ $liquidacion->vence_at->format('d/m/Y') }}.</span>@endif
                    </p>
                    <ul>
                        @foreach($liquidacion->lineas as $l)
                            <li>{{ $l->concepto }}: {{ number_format((float) $l->total, 2, ',', '.') }} €</li>
                        @endforeach
                    </ul>
                </div>
                <div class="acciones" style="margin:0;">
                    <form method="post" action="{{ route('sede.pagos.iniciar', $expediente) }}">@csrf<input type="hidden" name="canal" value="tarjeta"><button class="boton" type="submit">Pagar con tarjeta</button></form>
                    @if($bizum)
                        <form method="post" action="{{ route('sede.pagos.iniciar', $expediente) }}">@csrf<input type="hidden" name="canal" value="bizum"><button class="boton secundario" type="submit">Pagar con Bizum</button></form>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <section class="tarjeta" aria-labelledby="t-historia">
        <h2 id="t-historia">Historia del expediente</h2>
        <ol class="linea-tiempo">
            @foreach($expediente->historial as $h)
                <li @class(['actual' => $loop->last])>
                    <strong>{{ $h->estado_nuevo->etiqueta() }}</strong>
                    <time datetime="{{ $h->created_at->toIso8601String() }}">{{ $h->created_at->format('d/m/Y H:i') }}</time>
                    <span>{{ $h->estado_nuevo->explicacion() }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    @if($notificaciones->isNotEmpty())
        <section class="tarjeta" aria-labelledby="t-notif">
            <h2 id="t-notif">Notificaciones</h2>
            <ul>
                @foreach($notificaciones as $n)
                    <li><a href="{{ route('sede.notificaciones.ver', $n) }}">{{ $n->asunto }}</a> · {{ $n->puesta_disposicion_at->format('d/m/Y') }} · {{ ['pendiente' => 'Sin abrir', 'accedida' => 'Abierta', 'rechazada' => 'Rechazada'][$n->estado] }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="tarjeta" aria-labelledby="t-docs">
        <h2 id="t-docs">Documentos</h2>
        @if($documentos->isEmpty())
            <p>No hay documentos.</p>
        @else
            <div class="tabla-desplazable"><table class="datos">
                <thead><tr><th scope="col">Documento</th><th scope="col">Tipo</th><th scope="col">Fecha</th></tr></thead>
                <tbody>
                @foreach($documentos as $d)
                    <tr><td><a href="{{ route('sede.documentos.descargar', $d) }}">{{ $d->nombre }}</a></td><td>{{ $d->etiquetaCategoria() }}</td><td>{{ $d->created_at->format('d/m/Y H:i') }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    @unless($expediente->estado->esFinal())
        <p><a href="{{ route('sede.expedientes.retirar', $expediente) }}">Retirar mi solicitud (desistimiento)</a></p>
    @endunless
@endsection
