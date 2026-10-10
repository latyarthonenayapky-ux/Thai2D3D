@extends('layouts.app', ['title' => '3D settings'])

@section('content')
<style>
    .three-admin{max-width:1440px;margin:24px auto;padding:0 20px}
    .three-admin-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:18px}
    .three-admin-heading h1{margin:0;font-size:26px}
    .three-admin-heading p{margin:6px 0 0;color:var(--muted)}
    .three-admin-actions{display:flex;flex-wrap:wrap;gap:8px}
    .three-admin-section{margin-bottom:18px}
    .three-admin-section h2{margin:0 0 5px;font-size:18px}
    .three-admin-section>p{margin:0 0 14px;color:var(--muted);font-size:13px}
    .three-admin-table{width:100%;border-collapse:collapse}
    .three-admin-table th,.three-admin-table td{padding:8px 10px;border:1px solid var(--gridline);text-align:left;vertical-align:top}
    .three-admin-table th{background:var(--surface-soft);font-size:11px;color:var(--muted);white-space:nowrap}
    .three-admin-table input{min-width:100px}
    .three-admin-hot-list{display:flex;flex-wrap:wrap;gap:5px;margin:8px 0}
    .three-admin-hot{display:flex;align-items:center;gap:5px;padding:3px 6px;border:1px solid var(--gridline);font-variant-numeric:tabular-nums}
    .three-admin-hot form{display:inline}
    .three-admin-hot button{padding:2px 5px;font-size:10px}
    .three-admin-inline{display:flex;align-items:end;gap:6px}
    .three-admin-inline button{white-space:nowrap}
    .three-admin-input-note{display:block;color:var(--muted);font-size:11px;margin-top:4px}
    .three-admin-config{display:grid;grid-template-columns:minmax(240px,1fr) minmax(240px,1fr);gap:16px}
    @media(max-width:760px){.three-admin{margin:14px auto;padding:0 10px}.three-admin-heading{align-items:flex-start;flex-direction:column}.three-admin-config{grid-template-columns:1fr}.three-admin-table th,.three-admin-table td{padding:7px 6px;font-size:12px}.three-admin-table input{min-width:84px}}
</style>
<main class="three-admin">
    <header class="three-admin-heading">
        <div><h1>3D Settings</h1><p>Independent 3D Draws · the 1st and 16th · Asia/Yangon · sales open 00:00–15:30 on Draw day.</p></div>
        <nav class="three-admin-actions" aria-label="3D administration">
            <a class="button secondary" href="{{ route('admin.settings.index') }}">2D settings</a>
            <a class="button secondary" href="{{ route('admin.three-digit.results') }}">3D results and settlements</a>
            <a class="button secondary" href="{{ route('operator.sales.workspace') }}">2D sales</a>
            <a class="button" href="{{ route('three-digit.sales.workspace') }}">3D sales</a>
        </nav>
    </header>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="error" role="alert">
            <strong>Please review the following:</strong>
            <ul style="margin:8px 0 0;padding-left:20px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <section class="card three-admin-section">
        <h2>Winning payout multiplier · 3D</h2>
        <p>Saved once for this business and reused for every 3D Draw until changed here.</p>
        <form method="POST" action="{{ route('admin.settings.payouts') }}" class="three-admin-inline">
            @csrf
            @method('PUT')
            <div><label for="payout-3d">3D payout multiplier</label><input id="payout-3d" name="payout_3d_multiplier" type="number" min="0" step="0.01" value="{{ old('payout_3d_multiplier', auth()->user()->businessSettings?->payout_3d_multiplier) }}" required></div>
            <button class="button" type="submit">Save multiplier</button>
        </form>
    </section>
    <section class="card three-admin-section">
        <h2>3D commission rates</h2>
        <p>Set each Agent’s 3D commission separately from its 2D rate. The settlement uses the saved percentage.</p>
        <div class="table-wrap">
            <table class="three-admin-table">
                <thead><tr><th>Agent code</th><th>Agent name</th><th>Phone</th><th>3D commission (%)</th><th>Save</th></tr></thead>
                <tbody>
                @forelse($agents as $agent)
                    <tr>
                        <td>{{ $agent->agent_code }}</td><td>{{ $agent->agent_name }}</td><td>{{ $agent->phone ?: '—' }}</td>
                        <td>
                            <form id="three-agent-commission-{{ $agent->id }}" method="POST" action="{{ route('admin.settings.agents.three-digit-commission', $agent) }}">
                                @csrf @method('PATCH')
                                <input aria-label="3D commission for {{ $agent->agent_code }}" name="commission_3d_percent" type="number" min="0" max="100" step="0.01" value="{{ $agent->commission_3d_percent }}" required>
                            </form>
                        </td>
                        <td><button class="button secondary" type="submit" form="three-agent-commission-{{ $agent->id }}">Save</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No Agents created yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card three-admin-section">
        <h2>Draw number limits and Hot Numbers</h2>
        <p>Configure future Draws before Draw day. Hot Numbers must be three digits; limits count accepted 3D numbers across the business.</p>
        <div class="table-wrap">
            <table class="three-admin-table">
                <thead><tr><th>Draw date</th><th>Sales state</th><th>Number limit</th><th>Accepted numbers / amount</th><th>Hot Numbers · 000–999</th></tr></thead>
                <tbody>
                @forelse($draws as $draw)
                    @php $canEditDraw = $draw->draw_date->toDateString() > \Carbon\Carbon::today('Asia/Yangon')->toDateString(); @endphp
                    <tr>
                        <td><strong>{{ $draw->draw_date->format('Y-m-d') }}</strong><span class="three-admin-input-note">Draw at 15:30</span></td>
                        <td><span class="pill {{ $draw->status === 'open' ? '' : 'off' }}">{{ $draw->status === 'open' ? 'Open' : ($draw->draw_date->toDateString() > \Carbon\Carbon::today('Asia/Yangon')->toDateString() ? 'Scheduled' : 'Closed') }}</span></td>
                        <td>
                            @if($canEditDraw)
                                <form class="three-admin-inline" method="POST" action="{{ route('admin.three-digit.draws.settings', $draw) }}">
                                    @csrf @method('PUT')
                                    <input aria-label="3D Number Limit for {{ $draw->draw_date->format('Y-m-d') }}" name="number_limit" type="number" min="1" step="1" value="{{ $draw->number_limit }}" placeholder="Not set">
                                    <button class="button secondary" type="submit">Save</button>
                                </form>
                            @else
                                {{ $draw->number_limit ? number_format($draw->number_limit) : 'Not set' }}
                                <span class="three-admin-input-note">Locked after Draw day begins</span>
                            @endif
                        </td>
                        <td>{{ number_format($draw->accepted_count) }} / {{ number_format($draw->accepted_amount, 2) }}</td>
                        <td>
                            <div class="three-admin-hot-list">
                                @forelse($draw->hotNumbers->sortBy('number') as $hotNumber)
                                    <span class="three-admin-hot">{{ $hotNumber->number }}
                                        @if($canEditDraw)
                                            <form method="POST" action="{{ route('admin.three-digit.draws.hot-numbers.destroy', [$draw, $hotNumber]) }}">
                                                @csrf @method('DELETE')
                                                <button class="button danger" type="submit" aria-label="Remove {{ $hotNumber->number }}">×</button>
                                            </form>
                                        @endif
                                    </span>
                                @empty
                                    <span class="muted">None</span>
                                @endforelse
                            </div>
                            @if($canEditDraw)
                                <form method="POST" action="{{ route('admin.three-digit.draws.hot-numbers.store', $draw) }}">
                                    @csrf
                                    <label class="sr-only" for="three-hot-{{ $draw->id }}">3D Hot Numbers for {{ $draw->draw_date->format('Y-m-d') }}</label>
                                    <textarea id="three-hot-{{ $draw->id }}" name="numbers" rows="2" placeholder="123, 456, 789" required></textarea>
                                    <button class="button secondary" type="submit" style="margin-top:6px">Add Hot Numbers</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No 3D Draws are available. Saving a schedule sync will create the 1st and 16th of each month.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
