@extends('layouts.sede', ['titulo' => 'Pago de la tasa'])

@section('contenido')
    <h1>Pago de la tasa</h1>
    <p class="entradilla">Liquidación {{ $liquidacion->referencia }} · <strong>{{ number_format((float) $liquidacion->total, 2, ',', '.') }} €</strong> · {{ $pago->canal === 'bizum' ? 'Bizum' : 'tarjeta bancaria' }}.</p>
    <p>Le llevamos a la pasarela de pago segura del banco. Si no avanza sola, pulse el botón.</p>
    <form id="form-pasarela" method="post" action="{{ $url }}" data-auto="1">
        @foreach($campos as $nombre => $valor)
            <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
        @endforeach
        <div class="acciones"><button class="boton" type="submit">Ir a la pasarela de pago</button></div>
    </form>
@endsection
