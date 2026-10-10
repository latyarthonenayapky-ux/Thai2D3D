@extends('layouts.app', ['title' => '3D summaries'])

@section('content')
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
        <div><h1>{{ $isAdmin ? '3D business summaries' : 'Your 3D summaries' }}</h1><p>Completed 3D Draws · Draw dates on the 1st and 16th · Asia/Yangon.</p></div>
        <nav class="three-report-actions" aria-label="Report navigation">
            <a class="button secondary" href="{{ route('reports.summary', ['mode' => '2d']) }}">2D summaries</a>
            @if($isAdmin)<a class="button secondary" href="{{ route('admin.three-digit.results') }}">3D results</a>@endif
            <a class="button secondary" href="{{ route('three-digit.sales.workspace') }}">3D Sale Entry</a>
        </nav>
    </header>
    @if($errors->any())<div class="error" role="alert"><strong>Check the report selection:</strong> {{ $errors->first() }}</div>@endif
    <section class="card" style="margin-bottom:18px">
        <form method="GET" action="{{ route('reports.summary') }}" class="grid" style="align-items:end">
            <input type="hidden" name="mode" value="3d">
            <div>
                <label for="period">Summary period</label>
                <select id="period" name="period">
                    @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
                        <option value="{{ $key }}" @selected($report['period'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="date">Date within period</label><input id="date" name="date" type="date" value="{{ $report['selected_date']->toDateString() }}" required></div>
            <div>
                <label for="agent_id">Agent</label>
                <select id="agent_id" name="agent_id">
                    <option value="">All Agents</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" @selected($selectedAgentId === $agent->id)>{{ $agent->agent_code }} · {{ $agent->agent_name }}</option>
                    @endforeach
                </select>
            </div>
            <div><button class="button" type="submit">Show 3D summary</button></div>
        </form>
        <p style="margin-bottom:0">Showing {{ ucfirst($report['period']) }} · {{ $report['start_date']->toDateString() }} to {{ $report['end_date']->toDateString() }} · {{ $report['draw_count'] }} completed Draw(s)</p>
    </section>
    @if($report['draw_rows']->contains(fn ($draw) => $draw['result'] === null))
        <div class="notice" role="status">At least one completed Draw is missing its official result. Winnings and business net for that Draw remain provisional until the result is entered.</div>
    @endif
    <section class="three-report-totals" aria-label="3D financial totals">
        <div><span>Accepted stakes</span><strong>{{ number_format($report['totals']['total_stake'], 2) }}</strong></div>
        <div><span>Winning stakes</span><strong>{{ number_format($report['totals']['winning_stake'], 2) }}</strong></div>
        <div><span>Winnings</span><strong>{{ number_format($report['totals']['winnings'], 2) }}</strong></div>
        <div><span>Agent commissions</span><strong>{{ number_format($report['totals']['commission'], 2) }}</strong></div>
        <div><span>Business net</span><strong>{{ number_format($report['totals']['business_net'], 2) }}</strong></div>
    </section>
    @if(! $report['payout_configured'])
        <div class="error" role="alert">The business 3D payout multiplier is not configured. Set it in 3D Settings before relying on winnings.</div>
    @endif
    <section class="card" style="margin-bottom:18px">
        <h2>3D Agent totals · {{ ucfirst($report['period']) }}</h2>
        <div class="table-wrap">
            <table class="three-report-table">
                <thead><tr><th>Agent</th><th>Draws</th><th>Accepted stakes</th><th>Winning stakes</th><th>Winnings</th><th>Commission</th><th>Business net</th></tr></thead>
                <tbody>
                @forelse($report['agent_totals'] as $agent)
                    <tr><td>{{ $agent['agent_code'] }} · {{ $agent['agent_name'] }}</td><td>{{ $agent['draw_count'] }}</td><td>{{ number_format($agent['total_stake'], 2) }}</td><td>{{ number_format($agent['winning_stake'], 2) }}</td><td>{{ number_format($agent['winnings'], 2) }}</td><td>{{ number_format($agent['commission'], 2) }}</td><td>{{ number_format($agent['business_net'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="muted">No assigned Agent sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card" style="margin-bottom:18px">
        <h2>Draw-date breakdown</h2>
        <div class="table-wrap">
            <table class="three-report-table">
                <thead><tr><th>Draw date</th><th>Draws</th><th>Accepted stakes</th><th>Winning stakes</th><th>Winnings</th><th>Commission</th><th>Business net</th></tr></thead>
                <tbody>
                @forelse($report['daily_rows'] as $day)
                    <tr><td>{{ $day['date'] }}</td><td>{{ $day['draw_count'] }}</td><td>{{ number_format($day['total_stake'], 2) }}</td><td>{{ number_format($day['winning_stake'], 2) }}</td><td>{{ number_format($day['winnings'], 2) }}</td><td>{{ number_format($day['commission'], 2) }}</td><td>{{ number_format($day['business_net'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="muted">No completed 3D Draws in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h2>Agent · Draw details</h2>
        <div class="table-wrap">
            <table class="three-report-table">
                <thead><tr><th>Agent</th><th>Draw date</th><th>Winning number</th><th>Draw handler</th><th>Accepted stakes</th><th>Winning stake</th><th>Winnings</th><th>Commission</th><th>Business net</th><th></th></tr></thead>
                <tbody>
                @forelse($report['agent_rows'] as $row)
                    <tr><td>{{ $row['agent_code'] }} · {{ $row['agent_name'] }}</td><td>{{ $row['draw_date'] }}</td><td>{{ $row['result'] ?? 'Not entered' }}</td><td>{{ $row['handler_name'] ?? '—' }}</td><td>{{ number_format($row['total_stake'], 2) }}</td><td>{{ number_format($row['winning_stake'], 2) }}</td><td>{{ number_format($row['winnings'], 2) }}</td><td>{{ number_format($row['commission'], 2) }}</td><td>{{ number_format($row['business_net'], 2) }}</td><td>@if($isAdmin)<a href="{{ route('admin.three-digit.results.show', $row['draw_id']) }}">Settlement</a>@else—@endif</td></tr>
                @empty
                    <tr><td colspan="10" class="muted">No Agent sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
