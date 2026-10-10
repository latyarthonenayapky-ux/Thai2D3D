@extends('layouts.app', ['title' => 'Period summaries'])

@section('content')
<main class="main">
    @if($isAdmin)
        <a href="{{ route('admin.settlements.index') }}">← Round settlements</a>
    @else
        <a href="{{ route('dashboard') }}">← Operator dashboard</a>
    @endif
    <div class="eyebrow" style="margin-top:20px">Business reports</div>
    <h1 class="page-title" style="margin-top:8px">{{ $isAdmin ? 'Business' : 'Your' }} summaries</h1>
    <p class="subtitle">Daily, weekly, monthly, or yearly results for completed 2D Rounds. Filter by Agent and Round for a separate breakdown.</p>
    <p class="row"><a class="button secondary" href="{{ route('reports.summary', ['mode' => '3d']) }}">3D Draw summaries</a>@if($isAdmin)<a class="button secondary" href="{{ route('admin.three-digit.results') }}">3D results and settlements</a>@endif</p>

    @if($errors->any())
        <div class="error" role="alert">
            <strong>Please correct the report selection:</strong>
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
                <label for="period">Summary period</label>
                <select id="period" name="period">
                    @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
                        <option value="{{ $key }}" @selected($report['period'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date">Date within period</label>
                <input id="date" name="date" type="date" value="{{ $report['selected_date']->toDateString() }}" required>
            </div>
            <div>
                <label for="agent_id">Agent</label>
                <select id="agent_id" name="agent_id">
                    <option value="">All Agents</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" @selected($selectedAgentId === $agent->id)>{{ $agent->agent_code }} · {{ $agent->agent_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="round_no">Round</label>
                <select id="round_no" name="round_no">
                    <option value="">All Rounds</option>
                    @foreach([1, 2, 3] as $roundNo)
                        <option value="{{ $roundNo }}" @selected($selectedRoundNo === $roundNo)>Round {{ $roundNo }}</option>
                    @endforeach
                </select>
            </div>
            <div><button class="button" type="submit">Show summary</button></div>
        </form>
        <p style="margin-bottom:0">Showing {{ ucfirst($report['period']) }} · {{ $report['start_date']->toDateString() }} to {{ $report['end_date']->toDateString() }} · {{ $report['totals']['round_count'] }} completed Round(s)</p>
    </section>

    <section class="card" style="margin-bottom:20px">
        <h2>2D agent totals · {{ ucfirst($report['period']) }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Agent</th><th>Closed Agent/Rounds</th><th>Accepted stakes</th><th>Winning stakes</th><th>Winnings</th><th>Commission</th><th>Business net</th></tr></thead>
                <tbody>
                @forelse($report['agent_totals'] as $agent)
                    <tr>
                        <td>{{ $agent['agent_code'] }} · {{ $agent['agent_name'] }}</td>
                        <td>{{ $agent['round_count'] }}</td>
                        <td>{{ number_format($agent['total_stake'], 2) }}</td>
                        <td>{{ number_format($agent['winning_stake'], 2) }}</td>
                        <td>{{ number_format($agent['winnings'], 2) }}</td>
                        <td>{{ number_format($agent['commission'], 2) }}</td>
                        <td>{{ number_format($agent['business_net'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No Agent sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid" style="margin-bottom:20px">
        <div class="card"><div class="eyebrow">2D accepted stakes</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['total_stake'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">2D winning stakes</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['winning_stake'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">2D winnings expense</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['winnings'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">2D agent commissions</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['commission'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">2D business net</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($report['totals']['business_net'], 2) }}</h2><p style="margin-bottom:0">Accepted stakes − winnings − commissions</p></div>
    </section>

    <section class="card" style="margin-bottom:20px">
        <h2>Daily breakdown · 2D</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Closed rounds</th><th>Accepted stakes</th><th>Winning stakes</th><th>Winnings</th><th>Commission</th><th>Business net</th></tr></thead>
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
                    <tr><td colspan="7" class="muted">No closed Rounds in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Agent · day · Round details · 2D</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Agent</th><th>Date</th><th>Round</th><th>Round handler</th><th>2D result</th><th>Accepted stakes</th><th>Winning stakes</th><th>Winnings</th><th>Commission</th><th>Business net</th><th></th></tr></thead>
                <tbody>
                @forelse($report['agent_rows'] as $row)
                    <tr>
                        <td>{{ $row['agent_code'] }} · {{ $row['agent_name'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($row['date'])->format('Y-m-d') }}</td>
                        <td>{{ $row['round_no'] }}</td>
                        <td>{{ $row['operator_name'] ?? '—' }}</td>
                        <td>{{ $row['result_2d'] ?? '—' }}</td>
                        <td>{{ number_format($row['total_stake'], 2) }}</td>
                        <td>{{ number_format($row['winning_stake'], 2) }}</td>
                        <td>{{ number_format($row['winnings'], 2) }}</td>
                        <td>{{ number_format($row['commission'], 2) }}</td>
                        <td>{{ number_format($row['business_net'], 2) }}</td>
                        <td>
                            @if($isAdmin)
                                <a href="{{ route('admin.rounds.settlement', $row['round_id']) }}">Settlement</a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">No Agent sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
