{{ $organismo }} · Sede electrónica

{{ $asunto }}

@foreach($lineas as $linea)
{{ $linea }}

@endforeach
@if($enlace)
Acceda a la sede electrónica: {{ $enlace }}
@endif

Este es un aviso automático. No responda a este correo.
