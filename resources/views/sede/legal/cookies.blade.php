@extends('layouts.sede', ['titulo' => 'Política de cookies'])

@section('contenido')
    <h1>Política de cookies</h1>
    <p>Esta sede electrónica solo utiliza <strong>cookies técnicas</strong>, imprescindibles para su funcionamiento. No usa cookies de análisis ni de publicidad, por lo que no necesita su consentimiento (art. 22.2 de la Ley 34/2002, de servicios de la sociedad de la información).</p>
    <div class="tabla-desplazable"><table class="datos">
        <thead><tr><th scope="col">Cookie</th><th scope="col">Finalidad</th><th scope="col">Duración</th></tr></thead>
        <tbody>
            <tr><td>{{ config('session.cookie') }}</td><td>Mantener la sesión iniciada con su certificado.</td><td>{{ config('session.lifetime') }} minutos de inactividad</td></tr>
            <tr><td>XSRF-TOKEN</td><td>Proteger los formularios frente a envíos fraudulentos.</td><td>La de la sesión</td></tr>
        </tbody>
    </table></div>
@endsection
