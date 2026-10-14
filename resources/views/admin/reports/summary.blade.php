@extends('layouts.app', ['title' => __('app.reports.title_2d')])

@section('content')
@php
    $currency = auth()->user()->currencyLabel();
    $figureKeys = ['total_stake', 'winning_stake', 'winnings', 'commission', 'business_net'];
    $figureLabels = [
        'total_stake' => __('app.reports.col_accepted_stakes'),
        'winning_stake' => __('app.reports.col_winning_stakes'),
        'winnings' => __('app.reports.col_winnings'),
        'commission' => __('app.reports.col_commission'),
        'business_net' => __('app.reports.col_business_net'),
    ];
    // Clicking an Agent Total re-filters the report onto that Agent and scrolls
    // to the Day/Round detail, so the page number must not travel with it.
    $drillQuery = request()->query();
    unset($drillQuery['page'], $drillQuery['figure']);
@endphp
<style>
    .figure-link{display:block;text-align:right;color:inherit;text-decoration:underline;text-decoration-style:dotted}
    .figure-focus{background:var(--gridline)}
</style>
<main class="main">
    @if($isAdmin)
        <a href="{{ route('admin.settlements.index') }}">{{ __('app.reports.back_settlements') }}</a>
    @else
        <a href="{{ route('dashboard') }}">{{ __('app.reports.back_operator_dashboard') }}</a>
    @endif
    <div class="eyebrow" style="margin-top:20px">{{ __('app.reports.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ $isAdmin ? __('app.reports.business_summaries') : __('app.reports.your_summaries') }}</h1>
    <p class="subtitle">{{ __('app.reports.subtitle_2d', ['currency' => $currency]) }}</p>
    <p class="row"><a class="button secondary" href="{{ route('reports.summary', ['mode' => '3d']) }}">{{ __('app.reports.summaries_3d') }}</a>@if($isAdmin)<a class="button secondary" href="{{ route('admin.three-digit.results') }}">{{ __('app.reports.results_3d') }}</a>@endif</p>

    @if($errors->any())
        <div class="error" role="alert">
            <strong>{{ __('app.reports.errors_2d') }}</strong>
            <ul style="margin:8px 0 0;padding-left:20px">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card" style="margin-bottom:20px">
        <form method="GET" action="{{ route('reports.summary') }}" class="grid" style="align-items:end">
            <input type="hidden" name="mode" value="2d">
            <div>
                <label for="period">{{ __('app.reports.period') }}</label>
                <select id="period" name="period">
                    @foreach(['daily', 'weekly', 'monthly', 'yearly'] as $key)
                        <option value="{{ $key }}" @selected($report['period'] === $key)>{{ __('app.reports.period_'.$key) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date">{{ __('app.reports.date') }}</label>
                <input id="date" name="date" type="date" value="{{ $report['selected_date']->toDateString() }}" required>
            </div>
            <div>
                <label for="agent_id">{{ __('app.common.agent') }}</label>
                <select id="agent_id" name="agent_id">
                    <option value="">{{ __('app.reports.all_agents') }}</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" @selected($selectedAgentId === $agent->id)>{{ $agent->agent_code }} · {{ $agent->agent_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="round_no">{{ __('app.common.round') }}</label>
                <select id="round_no" name="round_no">
                    <option value="">{{ __('app.reports.all_rounds') }}</option>
                    @foreach([1, 2, 3] as $roundNo)
                        <option value="{{ $roundNo }}" @selected($selectedRoundNo === $roundNo)>{{ __('app.reports.round_option', ['no' => $roundNo]) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="row" style="gap:8px;grid-column:1/-1"><button class="button" type="submit">{{ __('app.reports.show_2d') }}</button><a class="button secondary" href="{{ route('reports.summary.export', request()->query()) }}">{{ __('app.reports.export_csv') }}</a></div>
        </form>
        <p style="margin-bottom:0">{{ __('app.reports.showing', ['period' => __('app.reports.period_'.$report['period']), 'start' => $report['start_date']->toDateString(), 'end' => $report['end_date']->toDateString(), 'count' => $report['totals']['round_count']]) }}</p>
    </section>

    <section class="card" style="margin-bottom:20px">
        <h2>{{ __('app.reports.agent_totals_2d', ['period' => __('app.reports.period_'.$report['period'])]) }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.reports.col_closed_agent_rounds') }}</th><th>{{ __('app.reports.col_accepted_stakes') }}</th><th>{{ __('app.reports.col_winning_stakes') }}</th><th>{{ __('app.reports.col_winnings') }}</th><th>{{ __('app.reports.col_commission') }}</th><th>{{ __('app.reports.col_business_net') }}</th></tr></thead>
                <tbody>
                @forelse($report['agent_totals'] as $agent)
                    <tr>
                        <td>{{ $agent['agent_code'] }} · {{ $agent['agent_name'] }}</td>
                        <td>{{ $agent['round_count'] }}</td>
                        @foreach($figureKeys as $key)
                            <td @if($selectedFigure === $key) class="figure-focus" @endif>
                                <a class="figure-link" title="{{ __('app.reports.drill_hint') }}"
                                   href="{{ route('reports.summary', array_merge($drillQuery, ['agent_id' => $agent['agent_id'], 'figure' => $key])) }}#report-detail">
                                    {{ number_format($agent[$key], 2) }}
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('app.reports.no_agent_sales') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid" style="margin-bottom:20px">
        <div class="card"><div class="eyebrow">{{ __('app.reports.stat_accepted_2d', ['currency' => $currency]) }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['total_stake'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">{{ __('app.reports.stat_winning_2d', ['currency' => $currency]) }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['winning_stake'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">{{ __('app.reports.stat_winnings_2d', ['currency' => $currency]) }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['winnings'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">{{ __('app.reports.stat_commission_2d', ['currency' => $currency]) }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['commission'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">{{ __('app.reports.stat_business_net_2d', ['currency' => $currency]) }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['business_net'], 2) }}</h2><p style="margin-bottom:0">{{ __('app.reports.net_formula') }}</p></div>
    </section>

    <section class="card" style="margin-bottom:20px">
        <h2>{{ __('app.reports.daily_breakdown_2d') }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('app.common.date') }}</th><th>{{ __('app.reports.col_closed_rounds') }}</th><th>{{ __('app.reports.col_accepted_stakes') }}</th><th>{{ __('app.reports.col_winning_stakes') }}</th><th>{{ __('app.reports.col_winnings') }}</th><th>{{ __('app.reports.col_commission') }}</th><th>{{ __('app.reports.col_business_net') }}</th></tr></thead>
                <tbody>
                @forelse($report['daily_rows'] as $day)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($day['date'])->format('Y-m-d') }}</td>
                        <td>{{ $day['round_count'] }}</td>
                        <td>{{ number_format($day['total_stake'], 2) }}</td>
                        <td>{{ number_format($day['winning_stake'], 2) }}</td>
                        <td>{{ number_format($day['winnings'], 2) }}</td>
                        <td>{{ number_format($day['commission'], 2) }}</td>
                        <td>{{ number_format($day['business_net'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('app.reports.no_closed_rounds') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" id="report-detail">
        <h2>{{ __('app.reports.detail_2d') }}@if($selectedFigure) · {{ $figureLabels[$selectedFigure] }}@endif</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.common.date') }}</th><th>{{ __('app.common.round') }}</th><th>{{ __('app.reports.col_round_handler') }}</th><th>{{ __('app.rounds.col_result_2d') }}</th>@foreach($figureKeys as $key)<th @if($selectedFigure === $key) class="figure-focus" @endif>{{ $figureLabels[$key] }}</th>@endforeach<th></th></tr></thead>
                <tbody>
                @forelse($detailRows as $row)
                    <tr>
                        <td>{{ $row['agent_code'] }} · {{ $row['agent_name'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($row['date'])->format('Y-m-d') }}</td>
                        <td>{{ $row['round_no'] }}</td>
                        <td>{{ $row['operator_name'] ?? '—' }}</td>
                        <td>{{ $row['result_2d'] ?? '—' }}</td>
                        @foreach($figureKeys as $key)
                            <td @if($selectedFigure === $key) class="figure-focus" @endif>{{ number_format($row[$key], 2) }}</td>
                        @endforeach
                        <td>
                            @if($isAdmin)
                                <a href="{{ route('admin.rounds.settlement', $row['round_id']) }}">{{ __('app.rounds.col_settlement') }}</a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">{{ __('app.reports.no_agent_sales') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $detailRows])
    </section>
</main>
@endsection
