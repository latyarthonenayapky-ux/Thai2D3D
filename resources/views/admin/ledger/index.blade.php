@extends('layouts.app', ['title' => __('app.ledger.title')])

@section('content')
<main class="main">
    <div class="eyebrow">{{ __('app.settings.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.ledger.title') }}</h1>
    <p class="subtitle">{{ __('app.ledger.subtitle') }}</p>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif

    <div class="grid" style="margin-bottom:20px">
        <div class="card">
            <div class="eyebrow">{{ __('app.ledger.portfolio_in') }}</div>
            <h2 style="font-size:24px;margin-top:8px">{{ number_format($portfolio['in'], 2) }}</h2>
        </div>
        <div class="card">
            <div class="eyebrow">{{ __('app.ledger.portfolio_out') }}</div>
            <h2 style="font-size:24px;margin-top:8px">{{ number_format($portfolio['out'], 2) }}</h2>
        </div>
        <div class="card">
            <div class="eyebrow">{{ __('app.ledger.portfolio_net') }}</div>
            <h2 style="font-size:24px;margin-top:8px">{{ number_format($portfolio['net'], 2) }}</h2>
        </div>
        <div class="card">
            <div class="eyebrow">{{ __('app.ledger.portfolio_agents') }}</div>
            <h2 style="font-size:24px;margin-top:8px">{{ number_format($portfolio['agents']) }}</h2>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px">
        <form method="GET" action="{{ route('admin.ledger.index') }}" class="grid" style="align-items:end">
            <div>
                <label for="q">{{ __('app.ledger.search_label') }}</label>
                <input id="q" name="q" type="text" maxlength="60"
                    value="{{ $search }}"
                    placeholder="{{ __('app.ledger.search_placeholder') }}">
            </div>
            <div><button class="button secondary" type="submit">{{ __('app.ledger.search_apply') }}</button></div>
        </form>
    </section>

    <section class="card">
        @if($agents->isEmpty())
            <p class="muted">{{ __('app.ledger.empty_agents') }}</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('app.ledger.col_agent') }}</th>
                            <th>{{ __('app.ledger.col_paid_in') }}</th>
                            <th>{{ __('app.ledger.col_paid_out') }}</th>
                            <th>{{ __('app.ledger.col_balance') }}</th>
                            <th>{{ __('app.ledger.col_entries') }}</th>
                            <th>{{ __('app.common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($agents as $agent)
                        @php $balance = $balances[$agent->id] ?? ['in' => 0, 'out' => 0, 'balance' => 0, 'entries' => 0]; @endphp
                        <tr>
                            <td>
                                {{ $agent->agent_name }}
                                <span class="muted" style="display:block;font-size:12px">{{ $agent->agent_code }}</span>
                            </td>
                            <td>{{ number_format($balance['in'], 2) }}</td>
                            <td>{{ number_format($balance['out'], 2) }}</td>
                            <td><strong>{{ number_format($balance['balance'], 2) }}</strong></td>
                            <td>{{ number_format($balance['entries']) }}</td>
                            <td>
                                <a class="button secondary" href="{{ route('admin.ledger.show', $agent) }}">
                                    {{ __('app.ledger.open_book') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin-top:14px">{{ __('app.activity.page', ['current' => $agents->currentPage(), 'last' => $agents->lastPage()]) }}</p>
            <div class="row" style="margin-top:10px">
                @if($agents->onFirstPage())
                    <span class="button secondary" aria-disabled="true">{{ __('app.activity.previous') }}</span>
                @else
                    <a class="button secondary" href="{{ $agents->previousPageUrl() }}">{{ __('app.activity.previous') }}</a>
                @endif
                @if($agents->hasMorePages())
                    <a class="button" href="{{ $agents->nextPageUrl() }}">{{ __('app.activity.next') }}</a>
                @endif
            </div>
        @endif
    </section>
</main>
@endsection
