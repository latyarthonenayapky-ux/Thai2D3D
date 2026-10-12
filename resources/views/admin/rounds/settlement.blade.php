@extends('layouts.app', ['title' => __('app.settlement.title')])

@section('content')
<main class="main">
    <a href="{{ route('admin.settings.index', ['round_id' => $round->id]) }}">{{ __('app.settlement.back_settings') }}</a>
    <div class="eyebrow" style="margin-top:20px">{{ __('app.settlement.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.sale.round_line', ['date' => $round->round_date->format('Y-m-d'), 'no' => $round->round_no]) }}</h1>
    <p class="subtitle">{{ __('app.settlement.subtitle') }}</p>
    <p><a class="button secondary" href="{{ route('admin.three-digit.results') }}">{{ __('app.settlement.open_3d') }}</a></p>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error" role="alert">
            <strong>{{ __('app.settlement.errors_heading') }}</strong>
            <ul style="margin:8px 0 0;padding-left:20px">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card" style="margin-bottom:20px">
        <div class="between">
            <div>
                <h2>{{ __('app.settlement.results_title') }}</h2>
                <p>{{ __('app.settlement.results_help') }}</p>
            </div>
            <span class="pill {{ $round->status === 'closed' ? '' : 'off' }}">{{ __('app.common.status_'.$round->status) }}</span>
        </div>
        @if($round->status === 'closed')
            <form method="POST" action="{{ route('admin.rounds.result.update', $round) }}" class="grid" style="align-items:end">
                @csrf
                @method('PATCH')
                <div>
                    <label for="result-2d">{{ __('app.rounds.col_result_2d') }}</label>
                    <input id="result-2d" name="result_2d" inputmode="numeric" pattern="[0-9]{2}" maxlength="2" value="{{ old('result_2d', $round->result?->result_2d) }}" placeholder="00">
                </div>
                <div><button class="button" type="submit">{{ __('app.settlement.save_results') }}</button></div>
            </form>
        @else
            <p class="notice">{{ __('app.settlement.locked_notice') }}</p>
        @endif
        <p class="muted">{{ __('app.settlement.multiplier', ['value' => $round->admin->businessSettings?->payout_2d_multiplier ?? __('app.settlement.multiplier_missing')]) }}</p>
    </section>

    <section class="grid" style="margin-bottom:20px">
        <div class="card"><div class="eyebrow">{{ __('app.settlement.stat_accepted_stakes') }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['total_stake'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">{{ __('app.settlement.stat_winning_stake') }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['winning_stake'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">{{ __('app.settlement.stat_winnings') }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['winnings'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">{{ __('app.settlement.stat_commissions') }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['commission'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">{{ __('app.settlement.stat_business_net') }}</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['business_net'], 2) }}</h2></div>
    </section>
    @if($round->result?->result_2d !== null && ! $settlement['payout_2d_configured'])
        <div class="error">{{ __('app.settlement.payout_missing') }}</div>
    @endif

    <section class="card" style="margin-bottom:20px">
        <h2>{{ __('app.settlement.agent_settlement') }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.reports.col_round_handler') }}</th><th>{{ __('app.settlement.col_accepted_stake') }}</th><th>{{ __('app.reports.col_winning_stake') }}</th><th>{{ __('app.reports.col_winnings') }}</th><th>{{ __('app.reports.col_commission') }}</th><th>{{ __('app.reports.col_business_net') }}</th></tr></thead>
                <tbody>
                @forelse($settlement['agents'] as $row)
                    <tr>
                        <td>{{ $row['agent']->agent_code }} · {{ $row['agent']->agent_name }}</td>
                        <td>{{ $row['operator']?->name ?? __('app.settlement.unassigned') }}</td>
                        <td>{{ number_format($row['total_stake'], 2) }}</td>
                        <td>{{ number_format($row['winning_stake'], 2) }}</td>
                        <td>{{ number_format($row['winnings'], 2) }}</td>
                        <td>{{ number_format($row['commission'], 2) }}</td>
                        <td>{{ number_format($row['business_net'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('app.settlement.no_agents') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>{{ __('app.settlement.audit_title') }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('app.threeResults.col_changed_at') }}</th><th>{{ __('app.settlement.col_field') }}</th><th>{{ __('app.threeResults.col_previous') }}</th><th>{{ __('app.threeResults.col_new') }}</th><th>{{ __('app.threeResults.col_changed_by') }}</th></tr></thead>
                <tbody>
                @forelse(($round->result?->changes ?? collect())->where('field', 'result_2d') as $change)
                    <tr>
                        <td>{{ $change->created_at->timezone('Asia/Yangon')->format('Y-m-d H:i:s') }}</td>
                        <td>{{ strtoupper(str_replace('_', ' ', $change->field)) }}</td>
                        <td>{{ $change->old_value ?? '—' }}</td>
                        <td>{{ $change->new_value ?? '—' }}</td>
                        <td>{{ $change->changedBy->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('app.settlement.no_changes') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
