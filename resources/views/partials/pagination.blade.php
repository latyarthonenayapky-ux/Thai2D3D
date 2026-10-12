@if($paginator->hasPages())
    <div class="row" style="justify-content:space-between;margin-top:14px">
        <span class="muted">{{ __('app.pagination.showing', ['from' => number_format($paginator->firstItem()), 'shown' => number_format($paginator->lastItem()), 'count' => number_format($paginator->total())]) }}</span>
        <span class="row" style="gap:8px">
            @if($paginator->onFirstPage())
                <span class="muted">{{ __('app.pagination.previous') }}</span>
            @else
                <a class="button secondary" href="{{ $paginator->previousPageUrl() }}">{{ __('app.pagination.previous') }}</a>
            @endif
            <span class="muted">{{ __('app.pagination.page', ['current' => number_format($paginator->currentPage()), 'last' => number_format($paginator->lastPage())]) }}</span>
            @if($paginator->hasMorePages())
                <a class="button secondary" href="{{ $paginator->nextPageUrl() }}">{{ __('app.pagination.next') }}</a>
            @else
                <span class="muted">{{ __('app.pagination.next') }}</span>
            @endif
        </span>
    </div>
@elseif($paginator->total() > 0)
    <p class="muted" style="margin:12px 0 0">{{ __('app.pagination.rows', ['count' => number_format($paginator->total())]) }}</p>
@endif
