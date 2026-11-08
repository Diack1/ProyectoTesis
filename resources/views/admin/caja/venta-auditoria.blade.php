<details class="sale-audit">
    <summary>Historial de correcciones</summary>
    @forelse($venta->correcciones as $correccion)
        <div>
            <strong>{{ $correccion->tipo === 'modificar_pagos' ? 'Pagos modificados' : 'Anulada y corregida' }}</strong>
            · {{ $correccion->created_at->format('d/m/Y H:i') }} · {{ $correccion->usuario?->name ?? 'Usuario no disponible' }}
            <br>Motivo: {{ $correccion->motivo }}
            <details>
                <summary>Ver valores anteriores y nuevos</summary>
                @php($changes = app(\App\Services\VentaCorreccionPresenter::class)->changes($correccion))
                @if($changes)
                    <dl class="sale-audit-changes">
                        @foreach($changes as $change)
                            <div><dt>{{ $change['label'] }}</dt><dd><span>{{ $change['before'] }}</span><span aria-hidden="true">→</span><strong>{{ $change['after'] }}</strong></dd></div>
                        @endforeach
                    </dl>
                @else
                    <p>No hubo cambios en los datos visibles del comprobante.</p>
                @endif
            </details>
        </div>
    @empty
        <p>Sin correcciones.</p>
    @endforelse
</details>
