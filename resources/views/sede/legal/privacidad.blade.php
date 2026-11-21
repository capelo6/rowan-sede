@extends('layouts.sede', ['titulo' => 'Política de privacidad'])

@section('contenido')
    <h1>Política de privacidad</h1>
    @if($texto = \App\Models\Ajuste::obtener('legal.privacidad'))
        {!! nl2br(e($texto)) !!}
    @else
        <h2>Responsable del tratamiento</h2>
        <p>{{ \App\Sede\Organismo::nombre() }}@if(\App\Sede\Organismo::nif()) (NIF {{ \App\Sede\Organismo::nif() }})@endif@if(\App\Sede\Organismo::email()) · <a href="mailto:{{ \App\Sede\Organismo::email() }}">{{ \App\Sede\Organismo::email() }}</a>@endif.</p>
        <h2>Finalidad</h2>
        <p>Tramitar las solicitudes presentadas en la sede electrónica, practicar las notificaciones, gestionar los pagos de tasas y precios públicos y avisarle por correo electrónico del estado de sus expedientes.</p>
        <h2>Base jurídica</h2>
        <p>El cumplimiento de una misión realizada en interés público o en el ejercicio de poderes públicos (art. 6.1.e del Reglamento General de Protección de Datos) y el cumplimiento de obligaciones legales (art. 6.1.c), de acuerdo con la Ley 39/2015 del Procedimiento Administrativo Común.</p>
        <h2>Conservación</h2>
        <p>Los datos se conservarán el tiempo necesario para tramitar el expediente y, después, durante los plazos previstos en la normativa de archivos y documentación.</p>
        <h2>Derechos</h2>
        <p>Puede ejercer sus derechos de acceso, rectificación, supresión, oposición, limitación y portabilidad ante el responsable, y presentar una reclamación ante la Agencia Española de Protección de Datos.</p>
    @endif
@endsection
