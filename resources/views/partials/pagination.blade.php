@if($paginator->hasPages())
    <div class="row" style="justify-content:space-between;margin-top:14px">
        <span class="muted">Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }} rows</span>
        <span class="row" style="gap:8px">
            @if($paginator->onFirstPage())
                <span class="muted">← Previous</span>
            @else
                <a class="button secondary" href="{{ $paginator->previousPageUrl() }}">← Previous</a>
            @endif
            <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
            @if($paginator->hasMorePages())
                <a class="button secondary" href="{{ $paginator->nextPageUrl() }}">Next →</a>
            @else
                <span class="muted">Next →</span>
            @endif
        </span>
    </div>
@elseif($paginator->total() > 0)
    <p class="muted" style="margin:12px 0 0">{{ number_format($paginator->total()) }} row(s)</p>
@endif
