@extends('layouts.sede', ['titulo' => 'Aviso legal'])

@section('contenido')
    <h1>Aviso legal</h1>
    @if($texto = \App\Models\Ajuste::obtener('legal.aviso_legal'))
        {!! nl2br(e($texto)) !!}
    @else
        <p>Titular de la sede electrónica: <strong>{{ \App\Sede\Organismo::nombre() }}</strong>@if(\App\Sede\Organismo::nif()), con NIF {{ \App\Sede\Organismo::nif() }}@endif@if(\App\Sede\Organismo::direccion()) y domicilio en {{ \App\Sede\Organismo::direccion() }}@endif.</p>
        <p>La sede electrónica es la dirección electrónica a través de la cual la ciudadanía accede a los trámites y servicios del organismo, de acuerdo con el artículo 38 de la Ley 40/2015, de Régimen Jurídico del Sector Público.</p>
        <p>La fecha y hora oficial de la sede es la que figura al pie de cada página, de acuerdo con el artículo 31 de la Ley 39/2015.</p>
        <p>Los documentos emitidos por la sede incorporan un código seguro de verificación que permite comprobar su autenticidad en la página <a href="{{ route('sede.verificar') }}">Verificar un documento</a>.</p>
    @endif
@endsection
