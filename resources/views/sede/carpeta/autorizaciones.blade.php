@extends('layouts.sede', ['titulo' => 'Autorizaciones'])

@section('contenido')
    <nav class="migas" aria-label="Estás aquí"><ol><li><a href="{{ route('sede.carpeta') }}">Mi carpeta</a></li><li aria-current="page">Autorizaciones</li></ol></nav>
    <h1>Representaciones y autorizaciones</h1>

    <h2>Personas o entidades a las que represento</h2>
    @if($comoRepresentante->isEmpty())
        <p>No tiene representaciones.</p>
    @else
        <div class="tabla-desplazable"><table class="datos">
            <thead><tr><th scope="col">Representado</th><th scope="col">Estado</th><th scope="col">Vigencia</th><th scope="col">Observaciones</th></tr></thead>
            <tbody>
            @foreach($comoRepresentante as $r)
                <tr>
                    <td>{{ $r->representado->nombreCompleto() }} ({{ $r->representado->nif }})</td>
                    <td><span class="estado {{ $r->estado === 'validada' ? 'success' : ($r->estado === 'pendiente' ? 'warning' : 'danger') }}">{{ \App\Models\Representacion::ESTADOS[$r->estado] ?? $r->estado }}</span></td>
                    <td>{{ $r->vigente_hasta ? 'Hasta el '.$r->vigente_hasta->format('d/m/Y') : 'Indefinida' }}</td>
                    <td>{{ $r->observaciones }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif

    <h2>Quién me representa</h2>
    @if($comoRepresentado->isEmpty())
        <p>Nadie actúa en su nombre.</p>
    @else
        <ul>
            @foreach($comoRepresentado as $r)
                <li>{{ $r->representante->nombreCompleto() }} ({{ $r->representante->nif }}) · {{ \App\Models\Representacion::ESTADOS[$r->estado] ?? $r->estado }}</li>
            @endforeach
        </ul>
    @endif

    <form method="post" action="{{ route('sede.autorizaciones.solicitar') }}" enctype="multipart/form-data" class="tarjeta">
        @csrf
        <h2>Actuar en nombre de otra persona o entidad</h2>
        <p>Aporte el documento que acredita la representación (poder notarial, modelo de otorgamiento firmado…). El personal lo validará y le avisaremos. Si tiene certificado de representante de una empresa, no hace falta: la representación se reconoce al acceder.</p>
        <div class="campo"><label for="nif">NIF de la persona o entidad representada</label><input type="text" id="nif" name="nif" value="{{ old('nif') }}" required></div>
        <div class="campo"><label for="nombre">Nombre completo o razón social</label><input type="text" id="nombre" name="nombre" value="{{ old('nombre') }}" required></div>
        <div class="campo"><label for="documento">Documento de representación</label><span class="ayuda" id="ayuda-documento">PDF, JPG o PNG, hasta {{ config('sede.documentos.max_mb') }} MB.</span><input type="file" id="documento" name="documento" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="ayuda-documento" required></div>
        <div class="acciones"><button class="boton" type="submit">Enviar para validar</button></div>
    </form>
@endsection
