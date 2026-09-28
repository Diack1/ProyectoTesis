@if ($paginator->hasPages())
<nav class="parkeo-pagination" aria-label="Paginación">
    <p class="parkeo-pagination__summary">
        Página {{ $paginator->currentPage() }}
        @if(method_exists($paginator, 'lastPage'))
            de {{ $paginator->lastPage() }} · {{ $paginator->total() }} registros
        @endif
    </p>
    <div class="parkeo-pagination__controls">
        @if ($paginator->onFirstPage())
            <span class="parkeo-pagination__button" aria-disabled="true">← Anterior</span>
        @else
            <a class="parkeo-pagination__button" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Anterior</a>
        @endif
        @isset($elements)
        <div class="parkeo-pagination__pages">
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="parkeo-pagination__gap">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="parkeo-pagination__button" aria-current="page" aria-label="Página {{ $page }}">{{ $page }}</span>
                    @else
                        <a class="parkeo-pagination__button" href="{{ $url }}" aria-label="Ir a página {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        </div>
        @endisset
        @if ($paginator->hasMorePages())
            <a class="parkeo-pagination__button" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente →</a>
        @else
            <span class="parkeo-pagination__button" aria-disabled="true">Siguiente →</span>
        @endif
    </div>
</nav>
@endif
