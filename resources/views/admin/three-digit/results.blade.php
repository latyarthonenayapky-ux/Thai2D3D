@extends('layouts.app', ['title' => $draw ? __('app.threeResults.title_settlement') : __('app.threeResults.title_list')])

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
            <h1>{{ $draw ? __('app.threeResults.heading_settlement') : __('app.reports.results_3d') }}</h1>
            <p>{{ __('app.threeResults.subtitle') }}</p>
        </div>
        <nav class="three-results-actions" aria-label="{{ __('app.threeResults.nav_aria') }}">
            <a class="button secondary" href="{{ route('admin.three-digit.index') }}">{{ __('app.threeAdmin.title') }}</a>
            <a class="button secondary" href="{{ route('admin.settings.index') }}">{{ __('app.threeAdmin.link_2d_settings') }}</a>
            @if($draw)<a class="button" href="{{ route('admin.three-digit.results') }}">{{ __('app.threeResults.all_results') }}</a>@endif
        </nav>
    </header>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    @if($draw)
        <section class="card">
            <div class="between">
                <div><h2>{{ __('app.threeResults.draw_heading', ['date' => $draw->draw_date->format('Y-m-d')]) }}</h2><p class="muted">{{ __('app.threeResults.closed_at', ['time' => $draw->result_time]) }}</p></div>
                <span class="pill">{{ $draw->result?->result ?? __('app.threeResults.result_not_entered') }}</span>
            </div>
            <form method="POST" action="{{ route('admin.three-digit.results.update', $draw) }}" class="row" style="align-items:end;margin-top:14px">
                @csrf @method('PATCH')
                <div><label for="three-result">{{ __('app.threeResults.result_label') }}</label><input id="three-result" name="result" type="text" inputmode="numeric" pattern="[0-9]{3}" maxlength="3" value="{{ old('result', $draw->result?->result) }}" placeholder="000"></div>
                <button class="button" type="submit">{{ __('app.threeResults.save_result') }}</button>
            </form>
            @if($draw->result?->changes?->isNotEmpty())
                <h3 style="margin-top:22px">{{ __('app.threeResults.history_title') }}</h3>
                <div class="table-wrap">
                    <table class="three-results-table">
                        <thead><tr><th>{{ __('app.threeResults.col_changed_at') }}</th><th>{{ __('app.threeResults.col_previous') }}</th><th>{{ __('app.threeResults.col_new') }}</th><th>{{ __('app.threeResults.col_changed_by') }}</th></tr></thead>
                        <tbody>
                        @foreach($draw->result->changes as $change)
                            <tr><td>{{ $change->created_at->timezone('Asia/Yangon')->format('Y-m-d H:i:s') }}</td><td>{{ $change->old_value ?? '—' }}</td><td>{{ $change->new_value ?? '—' }}</td><td>{{ $change->changedBy?->name ?? __('app.threeResults.unknown') }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        @if($draw->result?->result !== null && ! $settlement['payout_configured'])
            <div class="error" style="margin-top:14px">{{ __('app.threeResults.payout_missing') }}</div>
        @endif
        <section class="three-results-summary" aria-label="{{ __('app.threeResults.totals_aria') }}">
            <div><span>{{ __('app.threeResults.stat_accepted_stakes') }}</span><strong>{{ number_format($settlement['totals']['total_stake'], 2) }}</strong></div>
            <div><span>{{ __('app.threeResults.stat_winning_stake') }}</span><strong>{{ number_format($settlement['totals']['winning_stake'], 2) }}</strong></div>
            <div><span>{{ __('app.threeResults.stat_winnings') }}</span><strong>{{ number_format($settlement['totals']['winnings'], 2) }}</strong></div>
            <div><span>{{ __('app.threeResults.stat_commissions') }}</span><strong>{{ number_format($settlement['totals']['commission'], 2) }}</strong></div>
            <div><span>{{ __('app.threeResults.stat_business_net') }}</span><strong>{{ number_format($settlement['totals']['business_net'], 2) }}</strong></div>
        </section>
        <section class="card">
            <h2>{{ __('app.threeResults.agent_settlement') }}</h2>
            <div class="table-wrap">
                <table class="three-results-table">
                    <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.reports.col_draw_handler') }}</th><th>{{ __('app.threeResults.col_accepted_stake') }}</th><th>{{ __('app.reports.col_winning_stake') }}</th><th>{{ __('app.reports.col_winnings') }}</th><th>{{ __('app.reports.col_commission') }}</th><th>{{ __('app.reports.col_business_net') }}</th></tr></thead>
                    <tbody>
                    @forelse($settlement['agents'] as $row)
                        <tr><td>{{ $row['agent']->agent_code }} · {{ $row['agent']->agent_name }}</td><td>{{ $row['handler']?->name ?? __('app.threeResults.unassigned') }}</td><td>{{ number_format($row['total_stake'], 2) }}</td><td>{{ number_format($row['winning_stake'], 2) }}</td><td>{{ number_format($row['winnings'], 2) }}</td><td>{{ number_format($row['commission'], 2) }}</td><td>{{ number_format($row['business_net'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="muted">{{ __('app.threeResults.no_agents_draw') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="card">
            <div class="table-wrap">
                <table class="three-results-table">
                    <thead><tr><th>{{ __('app.reports.col_draw_date') }}</th><th>{{ __('app.threeResults.col_official_result') }}</th><th>{{ __('app.rounds.col_settlement') }}</th><th>{{ __('app.common.action') }}</th></tr></thead>
                    <tbody>
                    @forelse($draws as $pastDraw)
                        <tr><td>{{ $pastDraw->draw_date->format('Y-m-d') }}</td><td>{{ $pastDraw->result?->result ?? __('app.rounds.not_entered') }}</td><td>{{ $pastDraw->result?->result ? __('app.threeResults.calculated') : __('app.threeResults.awaiting_result') }}</td><td><a class="button secondary" href="{{ route('admin.three-digit.results.show', $pastDraw) }}">{{ __('app.threeResults.open_settlement') }}</a></td></tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('app.threeResults.no_draws_closed') }}</td></tr>
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
