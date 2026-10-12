@extends('layouts.app', ['title' => __('app.reports.title_3d')])

@section('content')
@php $currency = auth()->user()->currencyLabel(); @endphp
<style>
    .three-report{max-width:1440px;margin:24px auto;padding:0 20px}
    .three-report-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;margin-bottom:18px}
    .three-report-heading h1{margin:0;font-size:26px}
    .three-report-heading p{margin:6px 0 0;color:var(--muted)}
    .three-report-actions{display:flex;flex-wrap:wrap;gap:8px}
    .three-report-totals{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:10px;margin:16px 0}
    .three-report-totals>div{padding:12px;border:1px solid var(--border);background:var(--surface)}
    .three-report-totals span{display:block;color:var(--muted);font-size:11px}
    .three-report-totals strong{display:block;margin-top:5px;font-size:18px;font-variant-numeric:tabular-nums}
    .three-report-table{width:100%;border-collapse:collapse}
    .three-report-table th,.three-report-table td{padding:8px 10px;border:1px solid var(--gridline);text-align:left}
    .three-report-table th{background:var(--surface-soft);font-size:11px;color:var(--muted)}
    @media(max-width:760px){.three-report{margin:14px auto;padding:0 10px}.three-report-heading{align-items:flex-start;flex-direction:column}.three-report-totals{grid-template-columns:repeat(2,minmax(0,1fr))}.three-report-table th,.three-report-table td{padding:7px 6px;font-size:12px}}
</style>
<main class="three-report">
    <header class="three-report-heading">
        <div><h1>{{ $isAdmin ? __('app.reports.business_3d') : __('app.reports.your_3d') }}</h1><p>{{ __('app.reports.subtitle_3d', ['currency' => $currency]) }}</p></div>
        <nav class="three-report-actions" aria-label="{{ __('app.reports.nav_aria') }}">
            <a class="button secondary" href="{{ route('reports.summary', ['mode' => '2d']) }}">{{ __('app.rounds.summaries_2d') }}</a>
            @if($isAdmin)<a class="button secondary" href="{{ route('admin.three-digit.results') }}">{{ __('app.reports.results_3d_short') }}</a>@endif
            <a class="button secondary" href="{{ route('three-digit.sales.workspace') }}">{{ __('app.sale.entry_3d') }}</a>
        </nav>
    </header>
    @if($errors->any())<div class="error" role="alert"><strong>{{ __('app.reports.errors_3d') }}</strong> {{ $errors->first() }}</div>@endif
    <section class="card" style="margin-bottom:18px">
        <form method="GET" action="{{ route('reports.summary') }}" class="grid" style="align-items:end">
            <input type="hidden" name="mode" value="3d">
            <div>
                <label for="period">{{ __('app.reports.period') }}</label>
                <select id="period" name="period">
                    @foreach(['daily', 'weekly', 'monthly', 'yearly'] as $key)
                        <option value="{{ $key }}" @selected($report['period'] === $key)>{{ __('app.reports.period_'.$key) }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="date">{{ __('app.reports.date') }}</label><input id="date" name="date" type="date" value="{{ $report['selected_date']->toDateString() }}" required></div>
            <div>
                <label for="agent_id">{{ __('app.common.agent') }}</label>
                <select id="agent_id" name="agent_id">
                    <option value="">{{ __('app.reports.all_agents') }}</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" @selected($selectedAgentId === $agent->id)>{{ $agent->agent_code }} · {{ $agent->agent_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="row" style="gap:8px;grid-column:1/-1"><button class="button" type="submit">{{ __('app.reports.show_3d') }}</button><a class="button secondary" href="{{ route('reports.summary.export', request()->query()) }}">{{ __('app.reports.export_csv') }}</a></div>
        </form>
        <p style="margin-bottom:0">{{ __('app.reports.showing_draws', ['period' => __('app.reports.period_'.$report['period']), 'start' => $report['start_date']->toDateString(), 'end' => $report['end_date']->toDateString(), 'count' => $report['draw_count']]) }}</p>
    </section>
    @if($report['draw_rows']->contains(fn ($draw) => $draw['result'] === null))
        <div class="notice" role="status">{{ __('app.reports.missing_result') }}</div>
    @endif
    <section class="three-report-totals" aria-label="{{ __('app.reports.totals_aria') }}">
        <div><span>{{ __('app.reports.stat_accepted', ['currency' => $currency]) }}</span><strong>{{ number_format($report['totals']['total_stake'], 2) }}</strong></div>
        <div><span>{{ __('app.reports.stat_winning', ['currency' => $currency]) }}</span><strong>{{ number_format($report['totals']['winning_stake'], 2) }}</strong></div>
        <div><span>{{ __('app.reports.stat_winnings', ['currency' => $currency]) }}</span><strong>{{ number_format($report['totals']['winnings'], 2) }}</strong></div>
        <div><span>{{ __('app.reports.stat_commission', ['currency' => $currency]) }}</span><strong>{{ number_format($report['totals']['commission'], 2) }}</strong></div>
        <div><span>{{ __('app.reports.stat_business_net', ['currency' => $currency]) }}</span><strong>{{ number_format($report['totals']['business_net'], 2) }}</strong></div>
    </section>
    @if(! $report['payout_configured'])
        <div class="error" role="alert">{{ __('app.reports.payout_missing') }}</div>
    @endif
    <section class="card" style="margin-bottom:18px">
        <h2>{{ __('app.reports.agent_totals_3d', ['period' => __('app.reports.period_'.$report['period'])]) }}</h2>
        <div class="table-wrap">
            <table class="three-report-table">
                <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.reports.col_draws') }}</th><th>{{ __('app.reports.col_accepted_stakes') }}</th><th>{{ __('app.reports.col_winning_stakes') }}</th><th>{{ __('app.reports.col_winnings') }}</th><th>{{ __('app.reports.col_commission') }}</th><th>{{ __('app.reports.col_business_net') }}</th></tr></thead>
                <tbody>
                @forelse($report['agent_totals'] as $agent)
                    <tr><td>{{ $agent['agent_code'] }} · {{ $agent['agent_name'] }}</td><td>{{ $agent['draw_count'] }}</td><td>{{ number_format($agent['total_stake'], 2) }}</td><td>{{ number_format($agent['winning_stake'], 2) }}</td><td>{{ number_format($agent['winnings'], 2) }}</td><td>{{ number_format($agent['commission'], 2) }}</td><td>{{ number_format($agent['business_net'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('app.reports.no_agent_sales_3d') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card" style="margin-bottom:18px">
        <h2>{{ __('app.reports.draw_breakdown') }}</h2>
        <div class="table-wrap">
            <table class="three-report-table">
                <thead><tr><th>{{ __('app.reports.col_draw_date') }}</th><th>{{ __('app.reports.col_draws') }}</th><th>{{ __('app.reports.col_accepted_stakes') }}</th><th>{{ __('app.reports.col_winning_stakes') }}</th><th>{{ __('app.reports.col_winnings') }}</th><th>{{ __('app.reports.col_commission') }}</th><th>{{ __('app.reports.col_business_net') }}</th></tr></thead>
                <tbody>
                @forelse($report['daily_rows'] as $day)
                    <tr><td>{{ $day['date'] }}</td><td>{{ $day['draw_count'] }}</td><td>{{ number_format($day['total_stake'], 2) }}</td><td>{{ number_format($day['winning_stake'], 2) }}</td><td>{{ number_format($day['winnings'], 2) }}</td><td>{{ number_format($day['commission'], 2) }}</td><td>{{ number_format($day['business_net'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('app.reports.no_completed_draws') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h2>{{ __('app.reports.detail_3d') }}</h2>
        <div class="table-wrap">
            <table class="three-report-table">
                <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.reports.col_draw_date') }}</th><th>{{ __('app.reports.col_winning_number') }}</th><th>{{ __('app.reports.col_draw_handler') }}</th><th>{{ __('app.reports.col_accepted_stakes') }}</th><th>{{ __('app.reports.col_winning_stake') }}</th><th>{{ __('app.reports.col_winnings') }}</th><th>{{ __('app.reports.col_commission') }}</th><th>{{ __('app.reports.col_business_net') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($detailRows as $row)
                    <tr><td>{{ $row['agent_code'] }} · {{ $row['agent_name'] }}</td><td>{{ $row['draw_date'] }}</td><td>{{ $row['result'] ?? __('app.rounds.not_entered') }}</td><td>{{ $row['handler_name'] ?? '—' }}</td><td>{{ number_format($row['total_stake'], 2) }}</td><td>{{ number_format($row['winning_stake'], 2) }}</td><td>{{ number_format($row['winnings'], 2) }}</td><td>{{ number_format($row['commission'], 2) }}</td><td>{{ number_format($row['business_net'], 2) }}</td><td>@if($isAdmin)<a href="{{ route('admin.three-digit.results.show', $row['draw_id']) }}">{{ __('app.rounds.col_settlement') }}</a>@else—@endif</td></tr>
                @empty
                    <tr><td colspan="10" class="muted">{{ __('app.reports.no_agent_sales') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $detailRows])
    </section>
</main>
@endsection
