@extends('layouts.sede', ['titulo' => 'Resultado del pago'])

@section('contenido')
    <h1>Resultado del pago</h1>
    @if($pago->estado === 'autorizado')
        <div class="alerta ok" role="status">Pago completado: {{ number_format((float) $pago->importe, 2, ',', '.') }} €. Tiene el justificante en el expediente.</div>
    @elseif($pago->estado === 'denegado' || $resultado === 'ko')
        <div class="alerta error" role="alert">El pago no se ha completado. No se le ha cobrado nada. Puede intentarlo de nuevo desde el expediente.</div>
    @else
        <div class="alerta" role="status">Estamos esperando la confirmación del banco. Suele tardar unos segundos: actualice esta página o vuelva al expediente.</div>
    @endif
    <div class="acciones"><a class="boton" href="{{ route('sede.expedientes.ver', $expediente) }}">Volver al expediente {{ $expediente->numero }}</a></div>
@endsection
