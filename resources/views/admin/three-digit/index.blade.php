@extends('layouts.app', ['title' => __('app.threeAdmin.title')])

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
        <div><h1>{{ __('app.threeAdmin.heading') }}</h1><p>{{ __('app.threeAdmin.subtitle') }}</p></div>
        <nav class="three-admin-actions" aria-label="{{ __('app.threeAdmin.nav_aria') }}">
            <a class="button secondary" href="{{ route('admin.settings.index') }}">{{ __('app.threeAdmin.link_2d_settings') }}</a>
            <a class="button secondary" href="{{ route('admin.three-digit.results') }}">{{ __('app.reports.results_3d') }}</a>
            <a class="button secondary" href="{{ route('operator.sales.workspace') }}">{{ __('app.threeAdmin.link_2d_sales') }}</a>
            <a class="button" href="{{ route('three-digit.sales.workspace') }}">{{ __('app.threeAdmin.link_3d_sales') }}</a>
        </nav>
    </header>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="error" role="alert">
            <strong>{{ __('app.threeAdmin.errors_heading') }}</strong>
            <ul style="margin:8px 0 0;padding-left:20px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <section class="card three-admin-section">
        <h2>{{ __('app.threeAdmin.payout_title') }}</h2>
        <p>{{ __('app.threeAdmin.payout_help') }}</p>
        <form method="POST" action="{{ route('admin.settings.payouts') }}" class="three-admin-inline">
            @csrf
            @method('PUT')
            <div><label for="payout-3d">{{ __('app.threeAdmin.payout_label') }}</label><input id="payout-3d" name="payout_3d_multiplier" type="number" min="0" step="0.01" value="{{ old('payout_3d_multiplier', auth()->user()->businessSettings?->payout_3d_multiplier) }}" required></div>
            <button class="button" type="submit">{{ __('app.threeAdmin.payout_save') }}</button>
        </form>
    </section>
    <section class="card three-admin-section">
        <h2>{{ __('app.threeAdmin.commission_title') }}</h2>
        <p>{{ __('app.threeAdmin.commission_help') }}</p>
        <div class="table-wrap">
            <table class="three-admin-table">
                <thead><tr><th>{{ __('app.settings.col_agent_code') }}</th><th>{{ __('app.settings.col_agent_name') }}</th><th>{{ __('app.settings.col_phone') }}</th><th>{{ __('app.threeAdmin.col_commission_3d') }}</th><th>{{ __('app.common.save') }}</th></tr></thead>
                <tbody>
                @forelse($agents as $agent)
                    <tr>
                        <td>{{ $agent->agent_code }}</td><td>{{ $agent->agent_name }}</td><td>{{ $agent->phone ?: '—' }}</td>
                        <td>
                            <form id="three-agent-commission-{{ $agent->id }}" method="POST" action="{{ route('admin.settings.agents.three-digit-commission', $agent) }}">
                                @csrf @method('PATCH')
                                <input aria-label="{{ __('app.threeAdmin.commission_aria', ['code' => $agent->agent_code]) }}" name="commission_3d_percent" type="number" min="0" max="100" step="0.01" value="{{ $agent->commission_3d_percent }}" required>
                            </form>
                        </td>
                        <td><button class="button secondary" type="submit" form="three-agent-commission-{{ $agent->id }}">{{ __('app.common.save') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('app.threeAdmin.no_agents') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card three-admin-section">
        <h2>{{ __('app.threeAdmin.draw_limits_title') }}</h2>
        <p>{{ __('app.threeAdmin.draw_limits_help') }}</p>
        <div class="table-wrap">
            <table class="three-admin-table">
                <thead><tr><th>{{ __('app.reports.col_draw_date') }}</th><th>{{ __('app.threeAdmin.col_sales_state') }}</th><th>{{ __('app.threeAdmin.col_number_limit') }}</th><th>{{ __('app.threeAdmin.col_accepted_amount') }}</th><th>{{ __('app.threeAdmin.col_hot_3d') }}</th></tr></thead>
                <tbody>
                @forelse($draws as $draw)
                    @php $canEditDraw = $draw->draw_date->toDateString() > \Carbon\Carbon::today('Asia/Yangon')->toDateString(); @endphp
                    <tr>
                        <td><strong>{{ $draw->draw_date->format('Y-m-d') }}</strong><span class="three-admin-input-note">{{ __('app.threeAdmin.draw_at') }}</span></td>
                        <td><span class="pill {{ $draw->status === 'open' ? '' : 'off' }}">{{ $draw->status === 'open' ? __('app.common.status_open') : ($draw->draw_date->toDateString() > \Carbon\Carbon::today('Asia/Yangon')->toDateString() ? __('app.threeAdmin.status_scheduled') : __('app.common.status_closed')) }}</span></td>
                        <td>
                            @if($canEditDraw)
                                <form class="three-admin-inline" method="POST" action="{{ route('admin.three-digit.draws.settings', $draw) }}">
                                    @csrf @method('PUT')
                                    <input aria-label="{{ __('app.threeAdmin.limit_aria', ['date' => $draw->draw_date->format('Y-m-d')]) }}" name="number_limit" type="number" min="1" step="1" value="{{ $draw->number_limit }}" placeholder="{{ __('app.common.not_set') }}">
                                    <button class="button secondary" type="submit">{{ __('app.common.save') }}</button>
                                </form>
                            @else
                                {{ $draw->number_limit ? number_format($draw->number_limit) : __('app.common.not_set') }}
                                <span class="three-admin-input-note">{{ __('app.threeAdmin.locked_note') }}</span>
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
                                                <button class="button danger" type="submit" aria-label="{{ __('app.threeAdmin.remove_aria', ['number' => $hotNumber->number]) }}">×</button>
                                            </form>
                                        @endif
                                    </span>
                                @empty
                                    <span class="muted">{{ __('app.common.none') }}</span>
                                @endforelse
                            </div>
                            @if($canEditDraw)
                                <form method="POST" action="{{ route('admin.three-digit.draws.hot-numbers.store', $draw) }}">
                                    @csrf
                                    <label class="sr-only" for="three-hot-{{ $draw->id }}">{{ __('app.threeAdmin.hot_label', ['date' => $draw->draw_date->format('Y-m-d')]) }}</label>
                                    <textarea id="three-hot-{{ $draw->id }}" name="numbers" rows="2" placeholder="{{ __('app.threeAdmin.hot_placeholder') }}" required></textarea>
                                    <button class="button secondary" type="submit" style="margin-top:6px">{{ __('app.setup.add_hot') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('app.threeAdmin.no_draws') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
