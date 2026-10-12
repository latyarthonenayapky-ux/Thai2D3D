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
@endphp
<style>
    .live-round-list{display:grid;gap:18px}
    .live-round-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
    .live-round-totals{display:flex;gap:24px;flex-wrap:wrap}
    .live-round-totals strong{display:block;font-size:18px;margin-top:3px}
    .live-agent-list{display:grid;gap:1px;margin:18px -24px -24px;background:#edf1ee;border-radius:0 0 16px 16px;overflow:hidden}
    .live-agent{display:grid;grid-template-columns:minmax(160px,1.3fr) minmax(100px,1fr) repeat(2,minmax(85px,.7fr)) minmax(125px,.9fr);gap:12px;align-items:center;background:#fff;padding:14px 24px}
    .live-agent-name{font-weight:750;color:#183a2a}
    .live-agent-sub{display:block;margin-top:4px;color:#718078;font-size:12px}
    .live-agent-value{font-weight:700;font-variant-numeric:tabular-nums}
    .live-table-labels{display:grid;grid-template-columns:minmax(160px,1.3fr) minmax(100px,1fr) repeat(2,minmax(85px,.7fr)) minmax(125px,.9fr);gap:12px;padding:0 24px 10px;color:#718078;font-size:11px;font-weight:750;letter-spacing:.06em;text-transform:uppercase}
    @media(max-width:900px){
        .live-agent-list{margin:16px -19px -19px}
        .live-table-labels{display:none}
        .live-agent{grid-template-columns:1fr auto;gap:8px 14px;padding:14px 19px}
        .live-agent-name{grid-column:1 / -1}
        .live-agent-limit{grid-column:1 / -1}
        .live-agent-operator{grid-column:1 / -1}
        .live-agent-count::before,.live-agent-amount::before{content:attr(data-inline-label);color:#718078;font-size:12px;font-weight:500}
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
                const formatCount = value => Number(value).toLocaleString();
                const formatAmount = value => Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
                            const count = document.querySelector(`[data-live-round-count="${round.id}"]`);
                            const amount = document.querySelector(`[data-live-round-amount="${round.id}"]`);
                            if (count) count.textContent = formatCount(round.accepted_count);
                            if (amount) amount.textContent = formatAmount(round.accepted_amount);
                            for (const agent of round.agents || []) {
                                const row = document.querySelector(`[data-live-agent-row="${round.id}-${agent.agent_id}"]`);
                                if (!row) { window.location.reload(); return; }
                                row.querySelector('[data-live-agent-count]').textContent = formatCount(agent.accepted_count);
                                row.querySelector('[data-live-agent-amount]').textContent = formatAmount(agent.accepted_amount);
                                const pill = row.querySelector('[data-live-limit-pill]');
                                if (pill) pill.style.display = (agent.amount_limit !== null && agent.accepted_amount >= agent.amount_limit) ? '' : 'none';
                            }
                        }
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
            <section class="card live-round-list" style="margin-bottom:18px" data-live-round="{{ $round->id }}">
                <div class="live-round-heading">
                    <div>
                        <h2 style="margin:0">{{ $round->round_date->format('Y-m-d') }} · {{ __('app.clock.round') }} {{ $round->round_no }}</h2>
                        <p style="margin:6px 0 0">{{ ($round->start_time ?? __('app.common.auto')) }}–{{ $round->close_time }} · {{ __('app.dashboard.round_open') }}</p>
                    </div>
                    <a class="button secondary" href="{{ route('admin.settings.index', ['round_id' => $round->id]) }}#rounds">{{ __('app.dashboard.manage_round') }}</a>
                </div>

                <div class="live-round-totals" aria-label="{{ __('app.dashboard.accepted_numbers') }}">
                    <div><span class="muted">{{ __('app.dashboard.accepted_numbers') }}</span><strong data-live-round-count="{{ $round->id }}">{{ number_format($round->accepted_count) }}</strong></div>
                    <div><span class="muted">{{ __('app.dashboard.amount_2d') }}</span><strong data-live-round-amount="{{ $round->id }}">{{ number_format($round->accepted_amount, 2) }}</strong></div>
                </div>

                <div class="live-table-labels" aria-hidden="true">
                    <span>{{ __('app.common.agent') }}</span><span>{{ __('app.settings.col_handler') }}</span><span>{{ __('app.dashboard.numbers_2d') }}</span><span>{{ __('app.dashboard.amount_2d_col') }}</span><span>{{ __('app.dashboard.limit_monitor') }}</span>
                </div>
                <div class="live-agent-list">
                    @forelse($round->agent_sales as $agent)
                        @php
                            $amountLimitReached = $agent->amount_limit !== null && $agent->accepted_amount >= $agent->amount_limit;
                        @endphp
                        <article class="live-agent" data-live-agent-row="{{ $round->id }}-{{ $agent->agent_id }}">
                            <div class="live-agent-name">
                                {{ $agent->agent_code }} · {{ $agent->agent_name }}
                                @if($agent->assigned_user_id === $me->id && $agent->agent_session_id)
                                    <a class="button secondary" href="{{ route('operator.sales.show', $agent->agent_session_id) }}">{{ __('app.dashboard.enter_sales') }}</a>
                                @elseif($agent->assigned_user_id === null || $agent->assigned_user_id === $me->id)
                                    <form method="POST" action="{{ route('operator.agent-sessions.claim', [$round, $agent->agent_id]) }}" style="display:inline">
                                        @csrf
                                        <button class="button secondary" type="submit">{{ $agent->assigned_user_id === null ? __('app.dashboard.claim_agent') : __('app.dashboard.resume_agent') }}</button>
                                    </form>
                                @endif
                                <span class="pill off" data-live-limit-pill @if(!$amountLimitReached) style="display:none" @endif>{{ __('app.dashboard.limit_reached') }}</span>
                            </div>
                            <div class="live-agent-operator">
                                @if($agent->assigned_user_id === null)
                                    {{ __('app.dashboard.not_claimed') }}
                                @elseif($agent->assigned_user_id === $me->id)
                                    {{ __('app.dashboard.you_admin') }}
                                @else
                                    {{ $agent->handler_name }} ({{ __('app.roles.'.$agent->handler_role) }})
                                @endif
                            </div>
                            <div class="live-agent-count live-agent-value" data-live-agent-count data-inline-label="{{ __('app.dashboard.inline_numbers') }}">{{ number_format($agent->accepted_count) }}</div>
                            <div class="live-agent-amount live-agent-value" data-live-agent-amount data-inline-label="{{ __('app.dashboard.inline_amount') }}">{{ number_format($agent->accepted_amount, 2) }}</div>
                            <div class="live-agent-limit">
                                @if($agent->amount_limit === null)
                                    <span class="muted">{{ __('app.common.not_set') }}</span>
                                @else
                                    {{ number_format($agent->amount_limit, 2) }}
                                    <span class="live-agent-sub">{{ __('app.dashboard.monitoring_only') }}</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="muted" style="padding:16px 24px;margin:0">{{ __('app.dashboard.create_agents') }}</p>
                    @endforelse
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
