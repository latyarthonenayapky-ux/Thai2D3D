@extends('layouts.app', ['title' => 'Sale Entry · 2D'])

@section('content')
<style>
    .sales-picker{max-width:1120px;margin:28px auto;padding:0 20px}
    .sales-picker-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;margin-bottom:16px}
    .sales-picker-heading h1{margin:0;font-size:24px}
    .sales-picker-heading p{margin:6px 0 0;color:var(--muted)}
    .sales-picker-round{display:flex;align-items:center;gap:10px;margin:0 0 16px}
    .sales-picker-round select{max-width:420px}
    .sales-picker-table{width:100%;border-collapse:collapse}
    .sales-picker-table th,.sales-picker-table td{padding:10px 12px;border:1px solid var(--gridline);text-align:left}
    .sales-picker-table th{background:var(--surface-soft);font-size:11px;text-transform:uppercase;color:var(--muted)}
    .sales-picker-agent{font-weight:800}
    .sales-picker-agent small{display:block;margin-top:3px;color:var(--muted);font-weight:400}
    .sales-picker-state{font-size:12px;color:var(--muted)}
    @media(max-width:680px){.sales-picker{margin:16px auto;padding:0 10px}.sales-picker-heading{align-items:flex-start;flex-direction:column}.sales-picker-round{align-items:stretch;flex-direction:column}.sales-picker-round select{max-width:none}.sales-picker-table th,.sales-picker-table td{padding:8px 6px;font-size:12px}}
</style>
<main class="sales-picker">
    <header class="sales-picker-heading">
        <div><h1>{{ request('view') === 'agent' ? 'Agent Statement · 2D' : 'Sale Entry · 2D' }}</h1><p>Select an open Round, then choose an Agent to begin.</p></div>
        <a href="{{ route('three-digit.sales.workspace') }}">3D Sale Entry</a>
    </header>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    @if($rounds->isEmpty())
        <section class="card"><strong>No 2D Round is open right now.</strong><p class="muted">Sale Entry appears when a scheduled Round opens.</p></section>
    @else
        <form class="sales-picker-round" method="GET" action="{{ route('operator.sales.workspace') }}">
            @if(request('view') === 'agent')<input type="hidden" name="view" value="agent">@endif
            <label for="sales-picker-round">Open Round</label>
            <select id="sales-picker-round" name="round_id" onchange="this.form.submit()">
                @foreach($rounds as $availableRound)
                    <option value="{{ $availableRound->id }}" @selected($round?->id === $availableRound->id)>
                        {{ $availableRound->round_date->format('Y-m-d') }} · Round {{ $availableRound->round_no }} · {{ ($availableRound->start_time ?? 'auto') }}–{{ $availableRound->close_time }}
                    </option>
                @endforeach
            </select>
        </form>
        <section class="card" style="padding:0;overflow:hidden">
            <div class="table-wrap">
                <table class="sales-picker-table">
                    <thead><tr><th>Agent</th><th>Round assignment</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($agents as $agent)
                        @php $agentSession = $sessions->get($agent->id); @endphp
                        <tr>
                            <td class="sales-picker-agent">{{ $agent->agent_code }} · {{ $agent->agent_name }}<small>{{ $agent->phone ?: 'No phone on file' }}</small></td>
                            <td class="sales-picker-state">
                                @if($agentSession && (int) $agentSession->operator_id === (int) auth()->id())
                                    Assigned to you for this Round
                                @elseif($agentSession)
                                    Claimed by {{ $agentSession->operator?->name ?? 'another user' }}
                                @else
                                    Unclaimed · first claim owns this Agent for this Round
                                @endif
                            </td>
                            <td>
                                @if($agentSession && (int) $agentSession->operator_id === (int) auth()->id())
                                    <a class="button" href="{{ route('operator.sales.show', [$agentSession, 'mode' => '2d', 'view' => request('view') === 'agent' ? 'agent' : null]) }}">{{ request('view') === 'agent' ? 'Open Agent Statement' : 'Open Sale Entry' }}</a>
                                @elseif(! $agentSession)
                                    <form method="POST" action="{{ route('operator.agent-sessions.claim', [$round, $agent]) }}">
                                        @csrf
                                        <button class="button secondary" type="submit">Claim and open</button>
                                    </form>
                                @else
                                    <span class="pill off">Unavailable</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">No active Agents are configured.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</main>
@endsection
