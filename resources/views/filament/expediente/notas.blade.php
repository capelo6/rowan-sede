<div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr));">
    <div>
        <h3 style="font-weight:600;margin-bottom:.5rem;">Servicios programados</h3>
        @forelse($record->actuaciones()->latest()->get() as $a)
            <div style="border-bottom:1px solid rgba(128,128,128,.2);padding:.35rem 0;">
                <strong>{{ $a->descripcion }}</strong>
                @if($a->programada_para) · {{ $a->programada_para->format('d/m/Y H:i') }}@endif
                · {{ $a->realizada_at ? 'realizado el '.$a->realizada_at->format('d/m/Y') : 'pendiente' }}
                @if($a->proxima_renovacion) · renovación {{ $a->proxima_renovacion->format('d/m/Y') }}@endif
            </div>
        @empty
            <p>Ninguno.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;margin-bottom:.5rem;">Notas internas</h3>
        @forelse($record->notas()->with('usuario')->latest()->get() as $n)
            <div style="border-bottom:1px solid rgba(128,128,128,.2);padding:.35rem 0;">
                <span style="opacity:.75;">{{ $n->created_at->format('d/m/Y H:i') }} · {{ $n->usuario->name }}</span><br>
                <span style="white-space:pre-line;">{{ $n->texto }}</span>
            </div>
        @empty
            <p>Sin notas. Las notas no las ve la persona interesada.</p>
        @endforelse
    </div>
</div>
