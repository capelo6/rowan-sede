<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;">
        <label>Trámite
            <select wire:model.live="procedimiento" style="margin-left:.4rem;border-radius:.4rem;padding:.3rem;">
                <option value="">Todos</option>
                @foreach($this->getProcedimientos() as $id => $nombre)<option value="{{ $id }}">{{ $nombre }}</option>@endforeach
            </select>
        </label>
        <label><input type="checkbox" wire:model.live="mios"> Solo asignados a mí</label>
    </div>

    <div style="display:grid;grid-auto-flow:column;grid-auto-columns:minmax(15rem,1fr);gap:1rem;overflow-x:auto;padding-bottom:1rem;">
        @foreach($this->getColumnas() as $estado => $col)
            <section aria-label="{{ $col['etiqueta'] }}" style="background:rgba(128,128,128,.08);border-radius:.6rem;padding:.6rem;min-height:12rem;">
                <h2 style="font-weight:700;margin-bottom:.6rem;">{{ $col['etiqueta'] }} <span style="opacity:.7;">({{ count($col['tarjetas']) }})</span></h2>
                @forelse($col['tarjetas'] as $t)
                    <a href="{{ $t['url'] }}" style="display:block;background:var(--color-white, #fff);color:inherit;border:1px solid rgba(128,128,128,.3);border-left:5px solid {{ $t['semaforo'] === 'urgente' ? '#b91c1c' : ($t['vence'] ? '#b45309' : '#64748b') }};border-radius:.5rem;padding:.55rem .65rem;margin-bottom:.5rem;text-decoration:none;">
                        <strong>{{ $t['numero'] }}</strong>
                        @if($t['desistimiento'])<span style="color:#b91c1c;font-weight:700;"> · desistimiento propuesto</span>@endif
                        <div style="font-size:.85rem;">{{ $t['tramite'] }}</div>
                        <div style="font-size:.85rem;opacity:.8;">{{ $t['titular'] }}</div>
                        @if($t['vence'])<div style="font-size:.8rem;font-weight:600;">Vence: {{ $t['vence'] }}</div>@endif
                        <div style="font-size:.8rem;opacity:.75;">{{ $t['asignado'] ?? 'Sin asignar' }}</div>
                    </a>
                @empty
                    <p style="opacity:.7;font-size:.9rem;">Vacío</p>
                @endforelse
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
