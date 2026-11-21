@extends('layouts.sede', ['titulo' => 'Retirar la solicitud'])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li><a href="{{ route('sede.expedientes.ver', $expediente) }}">{{ $expediente->numero }}</a></li><li aria-current="page">Retirar</li></ol></nav>
    <h1>Retirar la solicitud</h1>
    <p class="entradilla">Expediente {{ $expediente->numero }} · {{ $expediente->procedimiento->nombre }}</p>
    <div class="alerta error">Si retira la solicitud, el expediente se cerrará y no podrá continuarlo. Si quiere volver a pedirlo, tendrá que presentar una solicitud nueva.</div>
    <form method="post" action="{{ route('sede.expedientes.retirar.enviar', $expediente) }}" class="tarjeta">
        @csrf
        <div class="campo"><label for="motivo">Motivo (opcional)</label><textarea id="motivo" name="motivo">{{ old('motivo') }}</textarea></div>
        <div class="casilla"><input type="checkbox" id="confirmo" name="confirmo" value="1" required aria-required="true"><label for="confirmo">Confirmo que quiero retirar mi solicitud.</label></div>
        <div class="acciones">
            <button class="boton peligro" type="submit">Retirar la solicitud</button>
            <a class="boton secundario" href="{{ route('sede.expedientes.ver', $expediente) }}">Volver sin retirar</a>
        </div>
    </form>
@endsection
