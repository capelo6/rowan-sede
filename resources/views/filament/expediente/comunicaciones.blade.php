@php($cal = app(\App\Sede\Plazos\Calendario::class))
<div style="display:grid;gap:1.5rem;">
    <div>
        <h3 style="font-weight:600;margin-bottom:.5rem;">Requerimientos</h3>
        @forelse($record->requerimientos()->latest()->get() as $r)
            <div style="border:1px solid rgba(128,128,128,.3);border-radius:.5rem;padding:.75rem;margin-bottom:.5rem;">
                <strong>{{ ['pendiente' => 'Pendiente', 'atendido' => 'Atendido', 'vencido' => 'Vencido', 'anulado' => 'Anulado'][$r->estado] ?? $r->estado }}</strong>
                · emitido el {{ $r->created_at->format('d/m/Y') }}
                @if($r->vence_at) · vence el {{ $r->vence_at->format('d/m/Y') }}@if($r->estado === 'pendiente') ({{ $cal->habilesRestantes($r->vence_at) }} días hábiles)@endif @endif
                @if($r->atendido_at) · atendido el {{ $r->atendido_at->format('d/m/Y H:i') }}@endif
                <ul style="margin:.4rem 0 0 1.2rem;list-style:disc;">
                    @foreach($r->items as $i)<li>{{ $i['descripcion'] }} <span style="opacity:.7;">({{ $i['tipo'] }})</span></li>@endforeach
                </ul>
                @if($r->motivo)<p style="margin-top:.4rem;opacity:.85;">{{ $r->motivo }}</p>@endif
            </div>
        @empty
            <p>Ninguno.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;margin-bottom:.5rem;">Notificaciones</h3>
        @forelse($record->notificaciones()->latest()->get() as $n)
            <div style="border-bottom:1px solid rgba(128,128,128,.2);padding:.35rem 0;">
                <strong>{{ $n->asunto }}</strong> · puesta a disposición {{ $n->puesta_disposicion_at->format('d/m/Y H:i') }} ·
                @if($n->estado === 'pendiente') sin abrir (se rechaza el {{ $n->venceAt()->format('d/m/Y') }})
                @elseif($n->estado === 'accedida') abierta el {{ $n->accedida_at->format('d/m/Y H:i') }}
                @else rechazada el {{ $n->rechazada_at->format('d/m/Y H:i') }} @endif
                @if($n->justificante) · <a href="{{ route('gestion.documentos.descargar', $n->justificante) }}" target="_blank" style="text-decoration:underline;">justificante</a>@endif
            </div>
        @empty
            <p>Ninguna.</p>
        @endforelse
    </div>
    <div>
        <h3 style="font-weight:600;margin-bottom:.5rem;">Avisos por correo enviados</h3>
        <ul style="font-size:.85rem;">
            @forelse(\App\Models\Aviso::query()->where('expediente_id', $record->id)->latest()->limit(30)->get() as $a)
                <li>{{ $a->created_at->format('d/m/Y H:i') }} · {{ $a->asunto }} @if($a->error)<span style="color:#b91c1c;">(error: {{ \Illuminate\Support\Str::limit($a->error, 80) }})</span>@endif</li>
            @empty
                <li>Ninguno.</li>
            @endforelse
        </ul>
    </div>
</div>
