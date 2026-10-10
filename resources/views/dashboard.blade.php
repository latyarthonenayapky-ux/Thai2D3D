@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
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
        .live-agent-count::before{content:"Numbers ";color:#718078;font-size:12px;font-weight:500}
        .live-agent-amount::before{content:"Amount ";color:#718078;font-size:12px;font-weight:500}
        .live-round-totals{gap:18px}
    }
</style>
<main class="main">
    @if(auth()->user()->isAdmin())
        <h1 class="page-title">Live sales dashboard</h1>
        <p class="subtitle">Open daily Rounds · accepted 2D sales across your business · refreshes every 15 seconds while this page is open</p>
        <script>
            let liveRefreshTimer = window.setTimeout(() => window.location.reload(), 15000);
            document.addEventListener('visibilitychange', () => {
                window.clearTimeout(liveRefreshTimer);
                if (document.visibilityState === 'visible') {
                    liveRefreshTimer = window.setTimeout(() => window.location.reload(), 15000);
                }
            });
        </script>

        @if(session('status'))
            <div class="notice" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="error" role="alert">
                <strong>Action could not be completed:</strong>
                <ul style="margin:8px 0 0;padding-left:20px">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row" style="margin-bottom:20px">
            <a class="button" href="{{ route('admin.settings.index') }}#round-schedule">Round schedule</a>
            <a class="button secondary" href="{{ route('admin.settings.index') }}#agents">Agents and limits</a>
            <a class="button secondary" href="{{ route('admin.settings.index') }}#rounds">Hot Numbers</a>
            <a class="button secondary" href="{{ route('admin.settlements.index') }}">Results and settlements</a>
            <a class="button secondary" href="{{ route('admin.three-digit.index') }}">3D Draw settings</a>
            <a class="button secondary" href="{{ route('three-digit.sales.workspace') }}">3D Sale Entry</a>
            <a class="button secondary" href="{{ route('reports.summary') }}">Period summaries</a>
            <a class="button {{ $pendingReviewCount > 0 ? 'danger' : 'secondary' }}" id="pending-review-link" href="{{ route('admin.offline-reviews.index') }}" aria-live="polite">Pending Review · <span id="pending-review-count">{{ $pendingReviewCount }}</span></a>
            <button class="button secondary" id="enable-review-notifications" type="button">Enable phone alerts</button>
        </div>
        <div id="review-notification" class="notice" role="status" aria-live="polite" hidden></div>
        <script>
            (() => {
                const endpoint = @json(route('admin.offline-reviews.pending'));
                const reviewUrl = @json(route('admin.offline-reviews.index'));
                const count = document.getElementById('pending-review-count');
                const link = document.getElementById('pending-review-link');
                const notice = document.getElementById('review-notification');
                const enable = document.getElementById('enable-review-notifications');
                let audioContext = null;
                let previousLatest = Number(@json($latestPendingReviewId ?? 0));
                const storageKey = 'thai2d3d-last-review-{{ auth()->id() }}';
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
                    notice.textContent = `${pendingCount} offline sale${pendingCount === 1 ? '' : 's'} need your review. Open Pending Review to decide.`;
                    link.classList.add('danger');
                    if (audioContext) playTone();
                    if ('Notification' in window && Notification.permission === 'granted') {
                        const notification = new Notification('Offline sales need review', {
                            body: `${pendingCount} offline sale${pendingCount === 1 ? '' : 's'} waiting for your decision.`,
                            tag: `thai2d3d-offline-review-${latestId}`,
                        });
                        notification.onclick = () => window.location.assign(reviewUrl);
                    }
                };

                if (storedLatest < previousLatest) {
                    notify(Number(@json($pendingReviewCount)), previousLatest);
                }
                localStorage.setItem(storageKey, String(previousLatest));

                enable.addEventListener('click', async () => {
                    let permission = 'unsupported';
                    if ('Notification' in window) {
                        permission = await Notification.requestPermission();
                    }
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (AudioContextClass) {
                        audioContext = new AudioContextClass();
                        await audioContext.resume();
                    }
                    enable.textContent = permission === 'granted'
                        ? 'Phone alerts enabled'
                        : 'In-app alerts enabled';
                    enable.disabled = true;
                });

                const poll = async () => {
                    try {
                        const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (!response.ok) return;
                        const data = await response.json();
                        count.textContent = String(data.count);
                        link.classList.toggle('danger', data.count > 0);
                        if (data.latest && data.latest > previousLatest) notify(data.count, data.latest);
                        if (data.latest) {
                            previousLatest = data.latest;
                            localStorage.setItem(storageKey, String(previousLatest));
                        }
                    } catch (error) {
                        notice.hidden = false;
                        notice.textContent = 'Review alert status is temporarily unavailable. Pending sales remain saved.';
                    }
                };
                window.setInterval(() => {
                    if (document.visibilityState === 'visible') poll();
                }, 10000);

                let liveRefreshTimer = window.setTimeout(() => window.location.reload(), 30000);
                document.addEventListener('visibilitychange', () => {
                    window.clearTimeout(liveRefreshTimer);
                    if (document.visibilityState === 'visible') {
                        liveRefreshTimer = window.setTimeout(() => window.location.reload(), 30000);
                    }
                });
            })();
        </script>

        @forelse($adminRounds as $round)
            <section class="card live-round-list" style="margin-bottom:18px">
                <div class="live-round-heading">
                    <div>
                        <h2 style="margin:0">{{ $round->round_date->format('Y-m-d') }} · Round {{ $round->round_no }}</h2>
                        <p style="margin:6px 0 0">{{ ($round->start_time ?? 'auto') }}–{{ $round->close_time }} · Open</p>
                    </div>
                    <a class="button secondary" href="{{ route('admin.settings.index', ['round_id' => $round->id]) }}#rounds">Manage this Round</a>
                </div>

                <div class="live-round-totals" aria-label="Round accepted sales totals">
                    <div><span class="muted">Accepted numbers</span><strong>{{ number_format($round->accepted_count) }}</strong></div>
                    <div><span class="muted">2D accepted amount</span><strong>{{ number_format($round->accepted_amount, 2) }}</strong></div>
                </div>

                <div class="live-table-labels" aria-hidden="true">
                    <span>Agent</span><span>Round handler</span><span>2D numbers</span><span>2D amount</span><span>2D limit · monitor</span>
                </div>
                <div class="live-agent-list">
                    @forelse($round->agent_sales as $agent)
                        @php
                            $amountLimitReached = $agent->amount_limit !== null && $agent->accepted_amount >= $agent->amount_limit;
                        @endphp
                        <article class="live-agent">
                            <div class="live-agent-name">
                                {{ $agent->agent_code }} · {{ $agent->agent_name }}
                                @if($agent->assigned_user_id === auth()->id() && $agent->agent_session_id)
                                    <a class="button secondary" href="{{ route('operator.sales.show', $agent->agent_session_id) }}">Enter sales</a>
                                @elseif($agent->assigned_user_id === null || $agent->assigned_user_id === auth()->id())
                                    <form method="POST" action="{{ route('operator.agent-sessions.claim', [$round, $agent->agent_id]) }}" style="display:inline">
                                        @csrf
                                        <button class="button secondary" type="submit">{{ $agent->assigned_user_id === null ? 'Claim Agent' : 'Resume Agent' }}</button>
                                    </form>
                                @endif
                                @if($amountLimitReached)
                                    <span class="pill off">Limit reached</span>
                                @endif
                            </div>
                            <div class="live-agent-operator">
                                @if($agent->assigned_user_id === null)
                                    Not claimed
                                @elseif($agent->assigned_user_id === auth()->id())
                                    You (Admin)
                                @else
                                    {{ $agent->handler_name }} ({{ ucfirst($agent->handler_role) }})
                                @endif
                            </div>
                            <div class="live-agent-count live-agent-value">{{ number_format($agent->accepted_count) }}</div>
                            <div class="live-agent-amount live-agent-value">{{ number_format($agent->accepted_amount, 2) }}</div>
                            <div class="live-agent-limit">
                                @if($agent->amount_limit === null)
                                    <span class="muted">Not set</span>
                                @else
                                    {{ number_format($agent->amount_limit, 2) }}
                                    <span class="live-agent-sub">Monitoring only; sales remain accepted</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="muted" style="padding:16px 24px;margin:0">Create or activate Agents in Settings to start receiving sales.</p>
                    @endforelse
                </div>
            </section>
        @empty
            <section class="card">
                <h2>No open Rounds</h2>
                <p class="muted">Set the daily schedule in Settings. Configure a Round’s blocked 2D values from Sale Entry; this is separate from the 3D number-count limit.</p>
                <a class="button" href="{{ route('admin.settings.index') }}#round-schedule">Configure Round schedule</a>
            </section>
        @endforelse
    @elseif(auth()->user()->isOperator())
        <div class="eyebrow">Operator workspace</div>
        <h1 class="page-title" style="margin-top:8px">Welcome, {{ auth()->user()->name }}</h1>
        <p class="subtitle">Each Agent has one handler per Round. You may claim multiple Agents in a Round; ownership is tracked separately for every Round.</p>

        @if(session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="error">
                <strong>Action could not be completed:</strong>
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
                <h2>{{ $round->round_date->format('Y-m-d') }} · Round {{ $round->round_no }}</h2>
                <p>{{ ($round->start_time ?? 'auto') }}–{{ $round->close_time }}</p>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Agent</th><th>Round assignment</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse($agents as $agent)
                            @php $assignment = $roundAssignments->get($agent->id); @endphp
                            <tr>
                                <td>{{ $agent->agent_code }} · {{ $agent->agent_name }}</td>
                                @if($assignment?->operator_id == auth()->id())
                                    <td>{{ in_array($agent->id, $activeAgentIds) ? 'Claimed by you · Active' : 'Claimed by you' }}</td>
                                    <td>
                                        @if(! in_array($agent->id, $activeAgentIds))
                                            <form method="POST" action="{{ route('operator.agent-sessions.claim', [$round, $agent]) }}">
                                                @csrf
                                                <button class="button secondary" type="submit">Resume Agent</button>
                                            </form>
                                        @else
                                            <a class="button" href="{{ route('operator.sales.show', $round->sessions->firstWhere('agent_id', $agent->id)) }}">Enter sales</a>
                                        @endif
                                    </td>
                                @elseif($assignment?->operator_id !== null)
                                    <td>{{ $assignment->operator?->isAdmin() ? 'Assigned to Admin' : 'Assigned to another Operator' }}</td>
                                    <td><span class="muted">Unavailable</span></td>
                                @else
                                    <td>Available</td>
                                    <td>
                                        <form method="POST" action="{{ route('operator.agent-sessions.claim', [$round, $agent]) }}">
                                            @csrf
                                            <button class="button" type="submit">Claim Agent</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">No active Agents are configured for this business.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="card">
                <h2>No open Rounds</h2>
                <p class="muted">Agent claiming is available when your Admin opens a Round.</p>
            </section>
        @endforelse

        <section class="card" style="margin-bottom:20px">
            <h2>Your recent Agent/Round sessions</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Round</th><th>Agent</th><th>Status</th><th>History</th></tr></thead>
                    <tbody>
                    @forelse($recentSessions as $session)
                        <tr>
                            <td>{{ $session->round->round_date->format('Y-m-d') }}</td>
                            <td>{{ $session->round->round_no }}</td>
                            <td>{{ $session->agent->agent_code }} · {{ $session->agent->agent_name }}</td>
                            <td><span class="pill {{ $session->status === 'open' ? '' : 'off' }}">{{ ucfirst($session->status) }}</span></td>
                            <td><a class="button secondary" href="{{ route('operator.sales.show', $session) }}">View sales</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No Agent sessions yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Account security</h2>
            <p>Use password recovery if you need to replace your password. Never share your sign-in link or password.</p>
            <a href="{{ route('password.request') }}">Reset password</a>
        </section>
    @else
    <div class="eyebrow">Workspace</div>
    <h1 class="page-title" style="margin-top:8px">Welcome, {{ auth()->user()->name }}</h1>
    <p class="subtitle">You’re signed in as {{ auth()->user()->role }}.</p>
    <div class="grid">
        @if(auth()->user()->isAdmin() || auth()->user()->isOwner())
            <section class="card">
                <h2>User access</h2>
                <p>
                    @if(auth()->user()->isOwner())
                        Create administrators and operators, issue password setup links, and manage access.
                    @else
                        Create operator accounts, issue password setup links, and manage operator access.
                    @endif
                </p>
                <a class="button" href="{{ route('admin.users.index') }}">Manage users</a>
                @if(auth()->user()->isAdmin())
                    <a class="button secondary" href="{{ route('admin.settings.index') }}">Application settings</a>
                    <a class="button secondary" href="{{ route('admin.settlements.index') }}">Round settlements</a>
                    <a class="button secondary" href="{{ route('reports.summary') }}">Period summaries</a>
                @endif
            </section>
        @else
            <section class="card">
                <h2>Your agents</h2>
                <p>Agent ownership is acquired separately for each Round when you open an Agent. You can handle multiple Agents in the same Round.</p>
                <p>Claim an Agent for an open Round to start entering sales.</p>
            </section>
        @endif
        <section class="card">
            <h2>Account security</h2>
            <p>Use password recovery if you need to replace your password. Never share your sign-in link or password.</p>
            <a href="{{ route('password.request') }}">Reset password</a>
        </section>
    </div>
    @endif
</main>
@endsection
