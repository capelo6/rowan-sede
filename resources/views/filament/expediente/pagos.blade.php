@forelse($record->liquidaciones()->with(['lineas', 'pagos'])->latest()->get() as $l)
    <div style="border:1px solid rgba(128,128,128,.3);border-radius:.5rem;padding:.75rem;margin-bottom:.75rem;">
        <strong>{{ $l->referencia }}</strong> · {{ ['pendiente' => 'Pendiente de pago', 'pagada' => 'Pagada', 'anulada' => 'Anulada'][$l->estado] ?? $l->estado }}
        · total {{ number_format((float) $l->total, 2, ',', '.') }} €
        @if($l->vence_at) · vence el {{ $l->vence_at->format('d/m/Y') }}@endif
        <table style="width:100%;border-collapse:collapse;font-size:.85rem;margin-top:.5rem;">
            <thead><tr style="text-align:left;border-bottom:1px solid rgba(128,128,128,.4);"><th>Concepto</th><th>Cant.</th><th>Base</th><th>IVA</th><th>Total</th><th>Motivo</th></tr></thead>
            <tbody>
            @foreach($l->lineas as $ln)
                <tr style="border-bottom:1px solid rgba(128,128,128,.2);">
                    <td>{{ $ln->concepto }}</td><td>{{ rtrim(rtrim(number_format((float) $ln->cantidad, 2, ',', '.'), '0'), ',') }}</td>
                    <td>{{ number_format((float) $ln->base, 2, ',', '.') }}</td><td>{{ number_format((float) $ln->iva, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $ln->total, 2, ',', '.') }}</td><td>{{ $ln->motivo }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @foreach($l->pagos as $p)
            <p style="margin-top:.4rem;font-size:.85rem;">Pago {{ \App\Models\Pago::CANALES[$p->canal] ?? $p->canal }} · {{ $p->estado }}
                @if($p->pedido) · pedido {{ $p->pedido }}@endif @if($p->autorizacion) · autorización {{ $p->autorizacion }}@endif
                @if($p->pagado_at) · {{ $p->pagado_at->format('d/m/Y H:i') }}@endif
                @if($p->justificante) · <a href="{{ route('gestion.documentos.descargar', $p->justificante) }}" target="_blank" style="text-decoration:underline;">justificante</a>@endif
            </p>
        @endforeach
    </div>
@empty
    <p>Sin tasas asignadas.</p>
@endforelse
