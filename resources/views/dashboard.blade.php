@extends('layouts.app', ['title' => __('app.dashboard.title')])

@section('content')
@php
    $me = auth()->user();
    $alertLabels = json_encode([
        'notice' => __('app.dashboard.review_notice'),
        'unavailable' => __('app.dashboard.review_unavailable'),
        'title' => __('app.dashboard.notify_title'),
        'body' => __('app.dashboard.notify_body'),
    ], JSON_UNESCAPED_UNICODE);
    $liveLabels = json_encode([
        'no_sales' => __('app.dashboard.live_no_sales'),
        'no_over' => __('app.dashboard.live_no_over'),
        'status_open' => __('app.common.status_open'),
        'status_closed' => __('app.common.status_closed'),
    ], JSON_UNESCAPED_UNICODE);
@endphp
<style>
    .live-round-list{display:grid;gap:18px}
    .live-round-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
    .live-round-totals{display:flex;gap:24px;flex-wrap:wrap}
    .live-round-totals strong{display:block;font-size:18px;margin-top:3px}
    .live-round-actions{display:flex;gap:8px;flex-wrap:wrap}
    .live-panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--border);margin-top:16px}
    .live-panel{background:var(--surface);padding:12px 16px;min-width:0}
    .live-panel h3{margin:0 0 8px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
    .live-table{width:100%;border-collapse:collapse;font-size:13px}
    .live-table th,.live-table td{padding:5px 6px;border-bottom:1px solid var(--gridline);text-align:right;font-variant-numeric:tabular-nums}
    .live-table th:first-child,.live-table td:first-child{text-align:left}
    .live-table thead th{color:var(--muted);font-size:10px;letter-spacing:.05em;text-transform:uppercase;border-bottom:1px solid var(--border)}
    .live-table .over-limit{color:var(--clock-red);font-weight:900}
    .live-table tfoot td{border-top:1px solid var(--border);border-bottom:0;font-weight:800}
    @media(max-width:900px){
        .live-panels{grid-template-columns:1fr}
        .live-round-totals{gap:18px}
    }
</style>
<main class="main">
    @if($me->isAdmin())
        <h1 class="page-title">{{ __('app.dashboard.title') }}</h1>
        <p class="subtitle">{{ __('app.dashboard.subtitle') }}</p>
        <script>
            (() => {
                const endpoint = @json(route('dashboard.live'));
                const labels = {!! $liveLabels !!};
                const formatCount = value => Number(value).toLocaleString();
                const formatAmount = value => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                const renderTop = (body, rows) => {
                    if (!body) return;
                    body.replaceChildren();
                    if (!rows.length) {
                        const cell = body.insertRow().insertCell();
                        cell.colSpan = 2;
                        cell.className = 'muted';
                        cell.textContent = labels.no_sales;
                        return;
                    }
                    rows.forEach(row => {
                        const tr = body.insertRow();
                        tr.insertCell().textContent = row.number;
                        tr.insertCell().textContent = formatAmount(row.amount);
                    });
                };

                const renderOver = (body, rows) => {
                    if (!body) return;
                    body.replaceChildren();
                    if (!rows.length) {
                        const cell = body.insertRow().insertCell();
                        cell.colSpan = 4;
                        cell.className = 'muted';
                        cell.textContent = labels.no_over;
                        return;
                    }
                    rows.forEach(row => {
                        const tr = body.insertRow();
                        tr.insertCell().textContent = row.number;
                        ['limit', 'sold', 'excess'].forEach(key => {
                            const cell = tr.insertCell();
                            cell.textContent = formatAmount(row[key]);
                            if (key !== 'limit') cell.className = 'over-limit';
                        });
                    });
                };

                const refreshLive = async () => {
                    try {
                        const response = await fetch(endpoint, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) return;
                        const data = await response.json();
                        const rounds = data.rounds || [];
                        const renderedRounds = [...document.querySelectorAll('[data-live-round]')].map(section => section.dataset.liveRound);
                        if (rounds.length !== renderedRounds.length || rounds.some(round => !renderedRounds.includes(String(round.id)))) {
                            window.location.reload();
                            return;
                        }
                        for (const round of rounds) {
                            const section = document.querySelector(`[data-live-round="${round.id}"]`);
                            const count = section?.querySelector('[data-live-round-count]');
                            const amount = section?.querySelector('[data-live-round-amount]');
                            if (count) count.textContent = formatCount(round.accepted_count);
                            if (amount) amount.textContent = formatAmount(round.accepted_amount);
                            renderTop(section?.querySelector('[data-live-top]'), round.top || []);
                            renderOver(section?.querySelector('[data-live-over]'), round.breaches || []);
                        }
                        const today = data.today || {rounds: []};
                        for (const row of today.rounds || []) {
                            const tr = document.querySelector(`[data-today-row="${row.id}"]`);
                            if (!tr) { window.location.reload(); return; }
                            tr.querySelector('[data-today-count]').textContent = formatCount(row.accepted_count);
                            tr.querySelector('[data-today-amount]').textContent = formatAmount(row.accepted_amount);
                            tr.querySelector('[data-today-result]').textContent = row.result_2d ?? '—';
                            tr.querySelector('[data-today-status]').textContent = labels['status_'+row.status] ?? row.status;
                        }
                        const dayCount = document.querySelector('[data-today-total-count]');
                        const dayAmount = document.querySelector('[data-today-total-amount]');
                        if (dayCount) dayCount.textContent = formatCount(today.accepted_count ?? 0);
                        if (dayAmount) dayAmount.textContent = formatAmount(today.accepted_amount ?? 0);
                    } catch (error) {
                        // Keep showing the last known totals; the next tick retries.
                    }
                };

                window.setInterval(refreshLive, 15000);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') refreshLive();
                });
            })();
        </script>

        @if(session('status'))
            <div class="notice" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="error" role="alert">
                <strong>{{ __('app.dashboard.action_failed') }}</strong>
                <ul style="margin:8px 0 0;padding-left:20px">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div id="review-notification" class="notice" role="status" aria-live="polite" hidden></div>
        @if($me->prefers('review_alerts', 'on'))
        <script>
            (() => {
                const endpoint = @json(route('admin.offline-reviews.pending'));
                const reviewUrl = @json(route('admin.offline-reviews.index'));
                const labels = {!! $alertLabels !!};
                const count = document.getElementById('pending-review-count');
                const link = document.getElementById('pending-review-link');
                const notice = document.getElementById('review-notification');
                let audioContext = null;
                let previousLatest = Number(@json($latestPendingReviewId ?? 0));
                const storageKey = 'thai2d3d-last-review-{{ $me->id }}';
                const storedLatest = Number(localStorage.getItem(storageKey) || previousLatest);

                const playTone = () => {
                    if (!audioContext) return;
                    const oscillator = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
                    oscillator.frequency.setValueAtTime(1174, audioContext.currentTime + 0.12);
                    gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.12, audioContext.currentTime + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.38);
                    oscillator.connect(gain);
                    gain.connect(audioContext.destination);
                    oscillator.start();
                    oscillator.stop(audioContext.currentTime + 0.4);
                };

                const notify = (pendingCount, latestId) => {
                    notice.hidden = false;
                    notice.textContent = labels.notice.replace(':count', pendingCount);
                    if (link) link.classList.add('danger');
                    if (audioContext) playTone();
                    if ('Notification' in window && Notification.permission === 'granted') {
                        const notification = new Notification(labels.title, {
                            body: labels.body.replace(':count', pendingCount),
                            tag: `thai2d3d-offline-review-${latestId}`,
                        });
                        notification.onclick = () => window.location.assign(reviewUrl);
                    }
                };

                if (storedLatest < previousLatest) {
                    notify(Number(@json($pendingReviewCount)), previousLatest);
                }
                localStorage.setItem(storageKey, String(previousLatest));

                // Browsers only allow audio after a user gesture, so unlock on the first click.
                document.addEventListener('click', async () => {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContextClass) return;
                    audioContext = new AudioContextClass();
                    await audioContext.resume();
                }, { once: true });

                const poll = async () => {
                    try {
                        const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (!response.ok) return;
                        const data = await response.json();
                        if (count) count.textContent = String(data.count);
                        if (link) link.classList.toggle('danger', data.count > 0);
                        if (data.latest && data.latest > previousLatest) notify(data.count, data.latest);
                        if (data.latest) {
                            previousLatest = data.latest;
                            localStorage.setItem(storageKey, String(previousLatest));
                        }
                    } catch (error) {
                        notice.hidden = false;
                        notice.textContent = labels.unavailable;
                    }
                };
                window.setInterval(() => {
                    if (document.visibilityState === 'visible') poll();
                }, 10000);
            })();
        </script>
        @endif

        @if($setupPending)
            <section class="card" style="margin-bottom:18px" aria-label="{{ __('app.dashboard.checklist_aria') }}">
                <h2>{{ __('app.dashboard.get_started') }}</h2>
                <ol style="margin:10px 0 0;padding-left:20px">
                    @foreach($setupChecklist as $item)
                        <li style="margin:6px 0">
                            @if($item['done'])
                                <s>{{ $item['label'] }}</s> <span class="pill off">{{ __('app.dashboard.done') }}</span>
                            @else
                                {{ $item['label'] }} · <a href="{{ $item['href'] }}">{{ $item['action'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
                <p class="muted" style="margin:12px 0 0">{!! __('app.dashboard.scheduler_cron') !!}</p>
            </section>
        @endif

        @forelse($adminRounds as $round)
            @php $figures = $round->figures; @endphp
            <section class="card live-round-list" style="margin-bottom:18px" data-live-round="{{ $round->id }}">
                <div class="live-round-heading">
                    <div>
                        <h2 style="margin:0">{{ $round->round_date->format('Y-m-d') }} · {{ __('app.clock.round') }} {{ $round->round_no }}</h2>
                        <p style="margin:6px 0 0">{{ ($round->start_time ?? __('app.common.auto')) }}–{{ $round->close_time }} · {{ __('app.dashboard.round_open') }}</p>
                    </div>
                    <div class="live-round-actions">
                        <a class="button" href="{{ route('operator.sales.workspace', ['round_id' => $round->id]) }}">{{ __('app.dashboard.open_sale_entry') }}</a>
                        <a class="button secondary" href="{{ route('admin.settings.index', ['round_id' => $round->id]) }}#rounds">{{ __('app.dashboard.manage_round') }}</a>
                    </div>
                </div>

                <div class="live-round-totals" aria-label="{{ __('app.dashboard.accepted_numbers') }}">
                    <div><span class="muted">{{ __('app.dashboard.accepted_numbers') }}</span><strong data-live-round-count>{{ number_format($figures['accepted_count']) }}</strong></div>
                    <div><span class="muted">{{ __('app.dashboard.amount_2d') }}</span><strong data-live-round-amount>{{ number_format($figures['accepted_amount'], 2) }}</strong></div>
                    <div><span class="muted">{{ __('app.dashboard.round_amount_limit') }}</span><strong @if($figures['amount_limit'] === null) class="muted" @endif data-live-round-limit>{{ $figures['amount_limit'] === null ? __('app.common.not_set') : number_format($figures['amount_limit'], 2) }}</strong></div>
                </div>

                <div class="live-panels">
                    <div class="live-panel">
                        <h3>{{ __('app.dashboard.top_sale_title') }}</h3>
                        <table class="live-table">
                            <thead><tr><th>{{ __('app.sale.number') }}</th><th>{{ __('app.sale.amount') }}</th></tr></thead>
                            <tbody data-live-top>
                                @forelse($figures['top'] as $row)
                                    <tr><td>{{ $row['number'] }}</td><td>{{ number_format($row['amount'], 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="2" class="muted">{{ __('app.dashboard.live_no_sales') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="live-panel">
                        <h3>{{ __('app.dashboard.over_limit_title') }}</h3>
                        <table class="live-table">
                            <thead><tr><th>{{ __('app.sale.number') }}</th><th>{{ __('app.sale.col_limit') }}</th><th>{{ __('app.sale.col_sold') }}</th><th>{{ __('app.sale.excess') }}</th></tr></thead>
                            <tbody data-live-over>
                                @forelse($figures['breaches'] as $row)
                                    <tr>
                                        <td class="over-limit">{{ $row['number'] }}</td>
                                        <td>{{ number_format($row['limit'], 2) }}</td>
                                        <td class="over-limit">{{ number_format($row['sold'], 2) }}</td>
                                        <td class="over-limit">{{ number_format($row['excess'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="muted">{{ __('app.dashboard.live_no_over') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @empty
            <section class="card">
                <h2>{{ __('app.dashboard.no_rounds') }}</h2>
                <p class="muted">{{ __('app.dashboard.no_rounds_help') }}</p>
                <p class="muted">{!! __('app.dashboard.no_rounds_scheduler') !!}</p>
                <a class="button" href="{{ route('admin.settings.index') }}#round-schedule">{{ __('app.dashboard.configure_schedule') }}</a>
            </section>
        @endforelse

        <section class="card live-round-list" data-live-today>
            <div class="live-round-heading">
                <h2 style="margin:0">{{ __('app.dashboard.today_title') }} · {{ today('Asia/Yangon')->format('Y-m-d') }}</h2>
                <div class="live-round-totals">
                    <div><span class="muted">{{ __('app.dashboard.accepted_numbers') }}</span><strong data-today-total-count>{{ number_format($todaySummary['accepted_count']) }}</strong></div>
                    <div><span class="muted">{{ __('app.dashboard.amount_2d') }}</span><strong data-today-total-amount>{{ number_format($todaySummary['accepted_amount'], 2) }}</strong></div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="live-table">
                    <thead><tr><th>{{ __('app.common.round') }}</th><th>{{ __('app.common.status') }}</th><th>{{ __('app.rounds.col_result_2d') }}</th><th>{{ __('app.dashboard.accepted_numbers') }}</th><th>{{ __('app.dashboard.amount_2d') }}</th></tr></thead>
                    <tbody>
                        @forelse($todaySummary['rounds'] as $row)
                            <tr data-today-row="{{ $row['id'] }}">
                                <td>{{ __('app.clock.round') }} {{ $row['round_no'] }} · {{ ($row['start_time'] ?? __('app.common.auto')) }}–{{ $row['close_time'] }}</td>
                                <td data-today-status>{{ __('app.common.status_'.$row['status']) }}</td>
                                <td data-today-result>{{ $row['result_2d'] ?? '—' }}</td>
                                <td data-today-count>{{ number_format($row['accepted_count']) }}</td>
                                <td data-today-amount>{{ number_format($row['accepted_amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">{{ __('app.dashboard.today_empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin:10px 0 0">{{ __('app.dashboard.today_help') }}</p>
        </section>
    @elseif($me->isOperator())
        <div class="eyebrow">{{ __('app.dashboard.operator_eyebrow') }}</div>
        <h1 class="page-title" style="margin-top:8px">{{ __('app.dashboard.welcome', ['name' => $me->name]) }}</h1>
        <p class="subtitle">{{ __('app.dashboard.operator_help') }}</p>

        @if(session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="error">
                <strong>{{ __('app.dashboard.action_failed') }}</strong>
                <ul style="margin:8px 0 0;padding-left:20px">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @forelse($openRounds as $round)
            @php
                $roundAssignments = $round->agentRoundSettings->keyBy('agent_id');
                $activeAgentIds = $round->sessions->pluck('agent_id')->all();
            @endphp
            <section class="card" style="margin-bottom:20px">
                <h2>{{ $round->round_date->format('Y-m-d') }} · {{ __('app.clock.round') }} {{ $round->round_no }}</h2>
                <p>{{ ($round->start_time ?? __('app.common.auto')) }}–{{ $round->close_time }}</p>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.dashboard.round_assignment') }}</th><th>{{ __('app.common.action') }}</th></tr></thead>
                        <tbody>
                        @forelse($agents as $agent)
                            @php $assignment = $roundAssignments->get($agent->id); @endphp
                            <tr>
                                <td>{{ $agent->agent_code }} · {{ $agent->agent_name }}</td>
                                @if($assignment?->operator_id == $me->id)
                                    <td>{{ in_array($agent->id, $activeAgentIds) ? __('app.dashboard.claimed_by_you_active') : __('app.dashboard.claimed_by_you') }}</td>
                                    <td>
                                        @if(! in_array($agent->id, $activeAgentIds))
                                            <form method="POST" action="{{ route('operator.agent-sessions.claim', [$round, $agent]) }}">
                                                @csrf
                                                <button class="button secondary" type="submit">{{ __('app.dashboard.resume_agent') }}</button>
                                            </form>
                                        @else
                                            <a class="button" href="{{ route('operator.sales.show', $round->sessions->firstWhere('agent_id', $agent->id)) }}">{{ __('app.dashboard.enter_sales') }}</a>
                                        @endif
                                    </td>
                                @elseif($assignment?->operator_id !== null)
                                    <td>{{ $assignment->operator?->isAdmin() ? __('app.dashboard.assigned_admin') : __('app.dashboard.assigned_other') }}</td>
                                    <td><span class="muted">{{ __('app.dashboard.unavailable') }}</span></td>
                                @else
                                    <td>{{ __('app.dashboard.available') }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('operator.agent-sessions.claim', [$round, $agent]) }}">
                                            @csrf
                                            <button class="button" type="submit">{{ __('app.dashboard.claim_agent') }}</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">{{ __('app.dashboard.no_agents') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="card">
                <h2>{{ __('app.dashboard.no_rounds') }}</h2>
                <p class="muted">{{ __('app.dashboard.agents_claim_unavailable') }}</p>
            </section>
        @endforelse

        <section class="card" style="margin-bottom:20px">
            <h2>{{ __('app.dashboard.recent_sessions') }}</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ __('app.common.date') }}</th><th>{{ __('app.clock.round') }}</th><th>{{ __('app.common.agent') }}</th><th>{{ __('app.common.status') }}</th><th>{{ __('app.dashboard.history') }}</th></tr></thead>
                    <tbody>
                    @forelse($recentSessions as $session)
                        <tr>
                            <td>{{ $session->round->round_date->format('Y-m-d') }}</td>
                            <td>{{ $session->round->round_no }}</td>
                            <td>{{ $session->agent->agent_code }} · {{ $session->agent->agent_name }}</td>
                            <td><span class="pill {{ $session->status === 'open' ? '' : 'off' }}">{{ __('app.common.status_'.$session->status) }}</span></td>
                            <td><a class="button secondary" href="{{ route('operator.sales.show', $session) }}">{{ __('app.dashboard.view_sales') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">{{ __('app.dashboard.no_sessions') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>{{ __('app.dashboard.account_security') }}</h2>
            <p>{{ __('app.dashboard.account_security_help') }}</p>
            <a href="{{ route('password.request') }}">{{ __('app.dashboard.reset_password') }}</a>
        </section>
    @else
    <div class="eyebrow">{{ __('app.dashboard.workspace') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.dashboard.welcome', ['name' => $me->name]) }}</h1>
    <p class="subtitle">{{ __('app.dashboard.signed_in_as', ['role' => __('app.roles.'.$me->role)]) }}</p>
    <div class="grid">
        @if($me->isAdmin() || $me->isOwner())
            <section class="card">
                <h2>{{ __('app.dashboard.user_access') }}</h2>
                <p>{{ $me->isOwner() ? __('app.dashboard.user_access_owner') : __('app.dashboard.user_access_admin') }}</p>
                <a class="button" href="{{ route('admin.users.index') }}">{{ __('app.dashboard.manage_users') }}</a>
                @if($me->isAdmin())
                    <a class="button secondary" href="{{ route('admin.settings.index') }}">{{ __('app.dashboard.application_settings') }}</a>
                    <a class="button secondary" href="{{ route('admin.settlements.index') }}">{{ __('app.dashboard.round_settlements') }}</a>
                    <a class="button secondary" href="{{ route('reports.summary') }}">{{ __('app.dashboard.period_summaries') }}</a>
                @endif
            </section>
        @else
            <section class="card">
                <h2>{{ __('app.dashboard.your_agents') }}</h2>
                <p>{{ __('app.dashboard.your_agents_help') }}</p>
                <p>{{ __('app.dashboard.your_agents_claim') }}</p>
            </section>
        @endif
        <section class="card">
            <h2>{{ __('app.dashboard.account_security') }}</h2>
            <p>{{ __('app.dashboard.account_security_help') }}</p>
            <a href="{{ route('password.request') }}">{{ __('app.dashboard.reset_password') }}</a>
        </section>
    </div>
    @endif
</main>
@endsection
