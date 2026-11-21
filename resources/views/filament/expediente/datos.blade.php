@php($e = $record)
@php($revision = $e->datos['_revision'] ?? [])
<div style="display:grid;gap:1rem;">
    <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
        <tbody>
        @foreach($e->procedimiento->campos() as $campo)
            @php($v = $e->datos[$campo['clave']] ?? null)
            <tr style="border-bottom:1px solid rgba(128,128,128,.25);">
                <th scope="row" style="text-align:left;padding:.45rem .5rem;width:35%;font-weight:600;">{{ $campo['etiqueta'] }}</th>
                <td style="padding:.45rem .5rem;white-space:pre-line;">{{ ($campo['tipo'] ?? '') === 'si_no' ? ($v ? 'Sí' : 'No') : ($v ?: '—') }}</td>
            </tr>
        @endforeach
        @foreach(collect($e->datos ?? [])->except(array_merge(collect($e->procedimiento->campos())->pluck('clave')->all(), ['_revision'])) as $clave => $v)
            <tr style="border-bottom:1px solid rgba(128,128,128,.25);">
                <th scope="row" style="text-align:left;padding:.45rem .5rem;font-weight:600;">{{ $clave }} <span style="opacity:.7;font-weight:400;">(aportado en subsanación)</span></th>
                <td style="padding:.45rem .5rem;white-space:pre-line;">{{ is_scalar($v) ? $v : json_encode($v) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div>
        <strong>Lista de revisión:</strong>
        @if(empty($revision))
            <span style="opacity:.75;">sin comprobaciones registradas.</span>
        @else
            {{ count($revision) }} comprobaciones registradas ({{ implode(', ', $revision) }}).
        @endif
    </div>
</div>
