@extends('layouts.sede', ['titulo' => 'Verificar un documento'])

@section('contenido')
    <h1>Verificar un documento</h1>
    <p class="entradilla">Compruebe la autenticidad de un documento emitido por esta sede con su código seguro de verificación (CSV), que aparece al pie.</p>
    <form method="post" action="{{ route('sede.verificar.comprobar') }}" class="tarjeta">
        @csrf
        <div class="campo">
            <label for="csv">Código seguro de verificación</label>
            <span class="ayuda" id="ayuda-csv">Por ejemplo: {{ config('sede.documentos.prefijo_csv') }}-ABCD-2345-EFGH-6789</span>
            <input type="text" id="csv" name="csv" value="{{ old('csv', request('csv')) }}" aria-describedby="ayuda-csv" autocomplete="off" spellcheck="false" required>
        </div>
        <button class="boton" type="submit">Verificar</button>
    </form>

    @isset($buscado)
        @if($documento)
            <section class="tarjeta" aria-labelledby="t-res">
                <h2 id="t-res">Documento auténtico</h2>
                <dl class="ficha">
                    <dt>Documento</dt><dd>{{ $documento->nombre }}</dd>
                    <dt>Tipo</dt><dd>{{ $documento->etiquetaCategoria() }}</dd>
                    <dt>Fecha</dt><dd>{{ $documento->created_at->format('d/m/Y H:i:s') }}</dd>
                    <dt>Sello electrónico</dt><dd>{{ $documento->firmado ? 'Sí' : 'No' }}</dd>
                    <dt>Huella SHA-256</dt><dd style="word-break:break-all;">{{ $documento->sha256 }}</dd>
                </dl>
                <p><a class="boton" href="{{ route('sede.verificar.descargar', $documento->csv) }}">Descargar el original</a></p>
            </section>
        @else
            <div class="alerta error" role="alert">No hay ningún documento con ese código. Compruebe que lo ha escrito bien.</div>
        @endif
    @endisset
@endsection
