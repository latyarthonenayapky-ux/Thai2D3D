@extends('layouts.app', ['title' => $draw ? '3D settlement' : '3D results'])

@section('content')
<style>
    .three-results{max-width:1440px;margin:24px auto;padding:0 20px}
    .three-results-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:18px}
    .three-results-heading h1{margin:0;font-size:26px}
    .three-results-heading p{margin:6px 0 0;color:var(--muted)}
    .three-results-actions{display:flex;flex-wrap:wrap;gap:8px}
    .three-results-summary{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:10px;margin:16px 0}
    .three-results-summary>div{padding:12px;border:1px solid var(--border);background:var(--surface)}
    .three-results-summary span{display:block;color:var(--muted);font-size:11px}
    .three-results-summary strong{display:block;margin-top:5px;font-size:18px;font-variant-numeric:tabular-nums}
    .three-results-table{width:100%;border-collapse:collapse}
    .three-results-table th,.three-results-table td{padding:8px 10px;border:1px solid var(--gridline);text-align:left}
    .three-results-table th{background:var(--surface-soft);font-size:11px;color:var(--muted)}
    @media(max-width:760px){.three-results{margin:14px auto;padding:0 10px}.three-results-heading{align-items:flex-start;flex-direction:column}.three-results-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.three-results-table th,.three-results-table td{padding:7px 6px;font-size:12px}}
</style>
<main class="three-results">
    <header class="three-results-heading">
        <div>
            <h1>{{ $draw ? '3D Draw settlement' : '3D results and settlements' }}</h1>
            <p>Only accepted stakes are included. Results are independently recorded for each 3D Draw.</p>
        </div>
        <nav class="three-results-actions" aria-label="3D navigation">
            <a class="button secondary" href="{{ route('admin.three-digit.index') }}">3D settings</a>
            <a class="button secondary" href="{{ route('admin.settings.index') }}">2D settings</a>
            @if($draw)<a class="button" href="{{ route('admin.three-digit.results') }}">All 3D results</a>@endif
        </nav>
    </header>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    @if($draw)
        <section class="card">
            <div class="between">
                <div><h2>{{ $draw->draw_date->format('Y-m-d') }} · 3D Draw</h2><p class="muted">Closed at {{ $draw->result_time }} · Asia/Yangon</p></div>
                <span class="pill">{{ $draw->result?->result ?? 'Result not entered' }}</span>
            </div>
            <form method="POST" action="{{ route('admin.three-digit.results.update', $draw) }}" class="row" style="align-items:end;margin-top:14px">
                @csrf @method('PATCH')
                <div><label for="three-result">Winning 3D result</label><input id="three-result" name="result" type="text" inputmode="numeric" pattern="[0-9]{3}" maxlength="3" value="{{ old('result', $draw->result?->result) }}" placeholder="000"></div>
                <button class="button" type="submit">Save result</button>
            </form>
            @if($draw->result?->changes?->isNotEmpty())
                <h3 style="margin-top:22px">Result change history</h3>
                <div class="table-wrap">
                    <table class="three-results-table">
                        <thead><tr><th>Changed at</th><th>Previous</th><th>New</th><th>Changed by</th></tr></thead>
                        <tbody>
                        @foreach($draw->result->changes as $change)
                            <tr><td>{{ $change->created_at->timezone('Asia/Yangon')->format('Y-m-d H:i:s') }}</td><td>{{ $change->old_value ?? '—' }}</td><td>{{ $change->new_value ?? '—' }}</td><td>{{ $change->changedBy?->name ?? 'Unknown' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        @if($draw->result?->result !== null && ! $settlement['payout_configured'])
            <div class="error" style="margin-top:14px">The result is saved, but the 3D payout multiplier is not configured. Set it in 3D Settings before relying on winnings.</div>
        @endif
        <section class="three-results-summary" aria-label="3D settlement totals">
            <div><span>Accepted stakes</span><strong>{{ number_format($settlement['totals']['total_stake'], 2) }}</strong></div>
            <div><span>Winning stake</span><strong>{{ number_format($settlement['totals']['winning_stake'], 2) }}</strong></div>
            <div><span>Winnings</span><strong>{{ number_format($settlement['totals']['winnings'], 2) }}</strong></div>
            <div><span>Commissions</span><strong>{{ number_format($settlement['totals']['commission'], 2) }}</strong></div>
            <div><span>Business net</span><strong>{{ number_format($settlement['totals']['business_net'], 2) }}</strong></div>
        </section>
        <section class="card">
            <h2>Agent settlement · 3D</h2>
            <div class="table-wrap">
                <table class="three-results-table">
                    <thead><tr><th>Agent</th><th>Draw handler</th><th>Accepted stake</th><th>Winning stake</th><th>Winnings</th><th>Commission</th><th>Business net</th></tr></thead>
                    <tbody>
                    @forelse($settlement['agents'] as $row)
                        <tr><td>{{ $row['agent']->agent_code }} · {{ $row['agent']->agent_name }}</td><td>{{ $row['handler']?->name ?? 'Unassigned' }}</td><td>{{ number_format($row['total_stake'], 2) }}</td><td>{{ number_format($row['winning_stake'], 2) }}</td><td>{{ number_format($row['winnings'], 2) }}</td><td>{{ number_format($row['commission'], 2) }}</td><td>{{ number_format($row['business_net'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="muted">No Agents or accepted sales are recorded for this Draw.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="card">
            <div class="table-wrap">
                <table class="three-results-table">
                    <thead><tr><th>Draw date</th><th>Official result</th><th>Settlement</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($draws as $pastDraw)
                        <tr><td>{{ $pastDraw->draw_date->format('Y-m-d') }}</td><td>{{ $pastDraw->result?->result ?? 'Not entered' }}</td><td>{{ $pastDraw->result?->result ? 'Calculated' : 'Awaiting result' }}</td><td><a class="button secondary" href="{{ route('admin.three-digit.results.show', $pastDraw) }}">Open settlement</a></td></tr>
                    @empty
                        <tr><td colspan="4" class="muted">No 3D Draws have closed yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($draws instanceof \Illuminate\Contracts\Pagination\Paginator || $draws instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div style="margin-top:16px">{{ $draws->links() }}</div>
            @endif
        </section>
    @endif
</main>
@endsection
