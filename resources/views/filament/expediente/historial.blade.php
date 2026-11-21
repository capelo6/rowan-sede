@php($usuarios = \App\Models\User::query()->pluck('name', 'id'))
@php($actividad = \Spatie\Activitylog\Models\Activity::query()->where('subject_type', $record->getMorphClass())->where('subject_id', $record->id)->latest()->limit(50)->get())
<div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr));">
    <div>
        <h3 style="font-weight:600;margin-bottom:.5rem;">Estados</h3>
        <ol style="list-style:none;padding-left:1rem;border-left:3px solid rgba(128,128,128,.35);margin:0;">
            @foreach($record->historial as $h)
                <li style="margin-bottom:.8rem;">
                    <strong>{{ $h->estado_nuevo->etiqueta() }}</strong>
                    <span style="opacity:.75;"> · {{ $h->created_at->format('d/m/Y H:i:s') }}</span><br>
                    <span>{{ $h->motivo }}</span>
                    <span style="opacity:.75;"> ({{ $h->actor_tipo === 'usuario' ? ($usuarios[$h->actor_id] ?? 'Personal') : ($h->actor_tipo === 'persona' ? 'Persona interesada' : 'Sistema') }})</span>
                </li>
            @endforeach
        </ol>
    </div>
    <div>
        <h3 style="font-weight:600;margin-bottom:.5rem;">Trazabilidad (consultas, descargas y cambios)</h3>
        <ul style="list-style:none;padding:0;margin:0;font-size:.85rem;">
            @forelse($actividad as $a)
                <li style="border-bottom:1px solid rgba(128,128,128,.2);padding:.3rem 0;">
                    {{ $a->created_at->format('d/m/Y H:i:s') }} · {{ $a->description }}
                    @if($a->causer) · {{ $a->causer->name ?? (method_exists($a->causer, 'nombreCompleto') ? $a->causer->nombreCompleto() : '') }}@endif
                </li>
            @empty
                <li>Sin actividad registrada.</li>
            @endforelse
        </ul>
    </div>
</div>
