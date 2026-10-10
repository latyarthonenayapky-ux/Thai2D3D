@extends('layouts.app', ['title' => 'Round settlement'])

@section('content')
<main class="main">
    <a href="{{ route('admin.settings.index', ['round_id' => $round->id]) }}">← 2D settings</a>
    <div class="eyebrow" style="margin-top:20px">Round result and settlement</div>
    <h1 class="page-title" style="margin-top:8px">{{ $round->round_date->format('Y-m-d') }} · Round {{ $round->round_no }}</h1>
    <p class="subtitle">Daily 2D result and settlement · only accepted, non-excluded 2D wagers are included.</p>
    <p><a class="button secondary" href="{{ route('admin.three-digit.results') }}">Open separate 3D Draw settlements</a></p>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error" role="alert">
            <strong>Please review the result:</strong>
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
                <h2>Official Round results</h2>
                <p>Enter the two-digit result after this daily Round closes.</p>
            </div>
            <span class="pill {{ $round->status === 'closed' ? '' : 'off' }}">{{ ucfirst($round->status) }}</span>
        </div>
        @if($round->status === 'closed')
            <form method="POST" action="{{ route('admin.rounds.result.update', $round) }}" class="grid" style="align-items:end">
                @csrf
                @method('PATCH')
                <div>
                    <label for="result-2d">2D result</label>
                    <input id="result-2d" name="result_2d" inputmode="numeric" pattern="[0-9]{2}" maxlength="2" value="{{ old('result_2d', $round->result?->result_2d) }}" placeholder="00">
                </div>
                <div><button class="button" type="submit">Save results</button></div>
            </form>
        @else
            <p class="notice">Results can be recorded after this Round is closed.</p>
        @endif
        <p class="muted">2D payout multiplier: {{ $round->admin->businessSettings?->payout_2d_multiplier ?? 'Not configured' }}</p>
    </section>

    <section class="grid" style="margin-bottom:20px">
        <div class="card"><div class="eyebrow">Accepted 2D stakes</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['total_stake'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">Winning stake</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['winning_stake'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">Winnings</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['winnings'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">Agent commissions</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['commission'], 2) }}</h2></div>
        <div class="card"><div class="eyebrow">Business net</div><h2 style="font-size:24px;margin-top:8px">{{ number_format($settlement['totals']['business_net'], 2) }}</h2></div>
    </section>
    @if($round->result?->result_2d !== null && ! $settlement['payout_2d_configured'])
        <div class="error">The 2D result is saved, but the payout multiplier is not configured. Winnings are not calculated until the multiplier is set in Settings.</div>
    @endif

    <section class="card" style="margin-bottom:20px">
        <h2>Agent settlement · 2D</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Agent</th><th>Round handler</th><th>Accepted stake</th><th>Winning stake</th><th>Winnings</th><th>Commission</th><th>Business net</th></tr></thead>
                <tbody>
                @forelse($settlement['agents'] as $row)
                    <tr>
                        <td>{{ $row['agent']->agent_code }} · {{ $row['agent']->agent_name }}</td>
                        <td>{{ $row['operator']?->name ?? 'Unassigned' }}</td>
                        <td>{{ number_format($row['total_stake'], 2) }}</td>
                        <td>{{ number_format($row['winning_stake'], 2) }}</td>
                        <td>{{ number_format($row['winnings'], 2) }}</td>
                        <td>{{ number_format($row['commission'], 2) }}</td>
                        <td>{{ number_format($row['business_net'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No Agent assignments or accepted sales for this Round.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Result change audit</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Changed at</th><th>Field</th><th>Previous</th><th>New</th><th>Changed by</th></tr></thead>
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
                    <tr><td colspan="5" class="muted">No changes recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
