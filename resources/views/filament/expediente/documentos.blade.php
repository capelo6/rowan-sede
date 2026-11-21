@php($docs = $record->documentos()->latest()->get())
@if($docs->isEmpty())
    <p>No hay documentos.</p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
            <thead>
            <tr style="text-align:left;border-bottom:2px solid rgba(128,128,128,.4);">
                <th style="padding:.4rem;">Documento</th><th style="padding:.4rem;">Tipo</th><th style="padding:.4rem;">Origen</th>
                <th style="padding:.4rem;">Fecha</th><th style="padding:.4rem;">CSV / huella</th>
            </tr>
            </thead>
            <tbody>
            @foreach($docs as $d)
                <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                    <td style="padding:.4rem;">
                        <a href="{{ route('gestion.documentos.descargar', $d) }}" target="_blank" style="text-decoration:underline;">{{ $d->nombre }}</a>
                        <span style="opacity:.7;">({{ $d->tamanoLegible() }})</span>
                        @if($d->firmado)<span title="Firmado o sellado"> ✔︎ firmado</span>@endif
                    </td>
                    <td style="padding:.4rem;">{{ $d->etiquetaCategoria() }}</td>
                    <td style="padding:.4rem;">{{ $d->origen === 'organismo' ? 'Organismo' : 'Interesado' }}</td>
                    <td style="padding:.4rem;white-space:nowrap;">{{ $d->created_at->format('d/m/Y H:i') }}</td>
                    <td style="padding:.4rem;font-family:monospace;font-size:.75rem;word-break:break-all;">{{ $d->csv ?? '' }}<br>{{ substr($d->sha256, 0, 24) }}…</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
