@extends('layouts.app', ['title' => 'Settings'])

@section('content')
<main class="main">
    <div class="eyebrow">Administration</div>
    <h1 class="page-title" style="margin-top:8px">Application settings</h1>
    <p class="subtitle">2D settings · three daily Rounds use Asia/Yangon time. Schedule changes affect unopened Rounds only.</p>
    <p class="row"><a class="button secondary" href="{{ route('admin.settlements.index') }}">2D results and settlements</a><a class="button secondary" href="{{ route('admin.three-digit.index') }}">3D settings</a><a class="button secondary" href="{{ route('admin.three-digit.results') }}">3D results and settlements</a></p>

    @if(session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error">
            <strong>Please review the following:</strong>
            <ul style="margin:8px 0 0;padding-left:20px">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card" id="round-schedule" style="margin-bottom:20px">
        <div class="between">
            <div>
                <h2>Daily Round schedule</h2>
                <p style="margin:0">Three fixed daily Rounds. Leave <strong>Start</strong> blank to open a Round automatically as soon as the previous Round closes; only the <strong>Close</strong> time is required. Configure blocked 2D values inside Sale Entry for each Round.</p>
            </div>
            <span class="pill">Asia/Yangon</span>
        </div>
        <form method="POST" action="{{ route('admin.settings.schedule') }}">
            @csrf
            @method('PUT')
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Round</th><th>Start</th><th>Close</th></tr></thead>
                    <tbody>
                    @foreach($schedules as $schedule)
                        <tr>
                            <td><strong>Round {{ $schedule->round_no }}</strong><input type="hidden" name="schedules[{{ $schedule->round_no }}][number_limit_3d]" value="{{ $schedule->number_limit_3d }}"></td>
                            <td><input aria-label="Round {{ $schedule->round_no }} start time" type="time" step="1" name="schedules[{{ $schedule->round_no }}][start_time]" value="{{ old("schedules.{$schedule->round_no}.start_time", $schedule->start_time) }}"></td>
                            <td><input aria-label="Round {{ $schedule->round_no }} close time" type="time" step="1" name="schedules[{{ $schedule->round_no }}][close_time]" value="{{ old("schedules.{$schedule->round_no}.close_time", $schedule->close_time) }}" required></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <button class="button" type="submit" style="margin-top:18px">Save schedule</button>
        </form>
    </section>

    <section class="card" id="payout-settings" style="margin-bottom:20px">
        <h2>Winning payout multipliers</h2>
        <p>Applied to the accepted stake on a winning 2D number. This multiplier is saved once for the business and reused until changed here.</p>
        <form method="POST" action="{{ route('admin.settings.payouts') }}" class="grid" style="align-items:end">
            @csrf
            @method('PUT')
            <div>
                <label for="payout-2d">2D payout multiplier</label>
                <input id="payout-2d" name="payout_2d_multiplier" type="number" min="0" step="0.01" value="{{ old('payout_2d_multiplier', $businessSettings->payout_2d_multiplier) }}" required>
            </div>
            <div><button class="button" type="submit">Save payout settings</button></div>
        </form>
    </section>

    <section class="card" id="rounds" style="margin-bottom:20px">
        <h2>Upcoming Rounds and Hot Numbers</h2>
        <p>2D Number Limit blocks listed values (00–99) per Round; it is not a sales-count cap. Hot Numbers are also managed per Round.</p>
        @if($rounds->isEmpty())
            <p class="muted">Daily Rounds will be generated after saving the schedule. Blocked 2D values are configured per Round using the Number Limit.</p>
        @else
            <form method="GET" action="{{ route('admin.settings.index') }}" class="row">
                <label for="round_id" style="margin:0">Select Round</label>
                <select id="round_id" name="round_id" style="max-width:380px" onchange="this.form.submit()">
                    @foreach($rounds as $round)
                        <option value="{{ $round->id }}" @selected($selectedRound?->id === $round->id)>
                            {{ $round->round_date->format('Y-m-d') }} · Round {{ $round->round_no }} · {{ ucfirst($round->status) }}
                        </option>
                    @endforeach
                </select>
            </form>
            <div class="table-wrap" style="margin-top:16px">
                <table>
                    <thead><tr><th>Date</th><th>Round</th><th>Hours</th><th>Blocked 2D values</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($rounds as $round)
                        <tr>
                            <td>{{ $round->round_date->format('Y-m-d') }}</td>
                            <td>{{ $round->round_no }}</td>
                            <td>{{ ($round->start_time ?? 'auto') }}–{{ $round->close_time }}</td>
                            <td>{{ implode(', ', $round->number_limits ?? []) ?: 'None' }}</td>
                            <td>
                                <span class="pill {{ $round->status === 'open' ? '' : 'off' }}">{{ ucfirst($round->status) }}</span>
                                @if($round->status === 'open' && $round->manual_reopen)
                                    <span class="muted">Manually reopened</span>
                                @endif
                            </td>
                            <td>
                                @if($round->status === 'closed')
                                    <div class="row">
                                        <a class="button secondary" href="{{ route('admin.rounds.settlement', $round) }}">Result / settlement</a>
                                        <form method="POST" action="{{ route('admin.settings.rounds.reopen', $round) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="button secondary" type="submit">Reopen</button>
                                        </form>
                                    </div>
                                @else
                                    <div class="row">
                                        <span class="muted">{{ $round->manual_reopen ? 'Reopened Round · close it here when finished' : 'Results available after close' }}</span>
                                        <form method="POST" action="{{ route('admin.settings.rounds.close', $round) }}" onsubmit="return confirm('Close this Round now and make its Agent sessions read-only?')">
                                            @csrf
                                            @method('PATCH')
                                            <button class="button danger" type="submit">{{ $round->manual_reopen ? 'Reclose reopened Round' : 'Close Round' }}</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($selectedRound)
                <form method="POST" action="{{ route('admin.settings.rounds.number-limits', $selectedRound) }}" style="max-width:680px;margin-top:22px">
                    @csrf
                    @method('PATCH')
                    <label for="blocked-numbers">Number Limit · blocked 2D values for {{ $selectedRound->round_date->format('Y-m-d') }} · Round {{ $selectedRound->round_no }}</label>
                    <p class="muted" style="margin:0 0 8px">Enter exact two-digit values from 00 to 99, separated by commas, spaces, or new lines. Only listed values are blocked; this does not cap how many numbers may be sold.</p>
                    <textarea id="blocked-numbers" name="numbers" placeholder="00, 17, 28&#10;39 46">{{ old('numbers', implode(', ', $selectedRound->number_limits ?? [])) }}</textarea>
                    <button class="button" type="submit" style="margin-top:12px">Save Number Limit</button>
                </form>
                <form method="POST" action="{{ route('admin.settings.hot-numbers.store', $selectedRound) }}" style="max-width:680px">
                    @csrf
                    <label for="hot-numbers">Hot Number list for {{ $selectedRound->round_date->format('Y-m-d') }} · Round {{ $selectedRound->round_no }}</label>
                    <textarea id="hot-numbers" name="numbers" placeholder="00, 17, 28&#10;39 46" required>{{ old('numbers') }}</textarea>
                    <button class="button" type="submit" style="margin-top:12px">Add Hot Numbers</button>
                </form>
                <div class="table-wrap" style="margin-top:16px">
                    <table>
                        <thead><tr><th>Number</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse($selectedRound->hotNumbers->sortBy('number') as $hotNumber)
                            <tr>
                                <td><strong>{{ $hotNumber->number }}</strong></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.settings.hot-numbers.destroy', [$selectedRound, $hotNumber]) }}" onsubmit="return confirm('Remove Hot Number {{ $hotNumber->number }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="muted">No Hot Numbers configured for this Round.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </section>

    <section class="card" id="agents" style="margin-bottom:20px">
        <h2>Agents and 2D Round assignments</h2>
        <p>Operators can handle multiple Agents in one Round. Each Agent can be claimed only once per Round; a new Round starts a new assignment.</p>
        <form method="POST" action="{{ route('admin.settings.agents.store') }}" class="grid" style="align-items:end">
            @csrf
            <div><label for="agent_code">Agent code</label><input id="agent_code" name="agent_code" value="{{ old('agent_code') }}" maxlength="255" required></div>
            <div><label for="agent_name">Agent name</label><input id="agent_name" name="agent_name" value="{{ old('agent_name') }}" maxlength="255" required></div>
            <div><label for="agent_phone">Phone (optional)</label><input id="agent_phone" name="phone" value="{{ old('phone') }}" maxlength="50"></div>
            <div><label for="agent-commission-2d">2D commission (%)</label><input id="agent-commission-2d" name="commission_2d_percent" type="number" min="0" max="100" step="0.01" value="{{ old('commission_2d_percent', '0.00') }}" required></div>
            <input type="hidden" name="commission_3d_percent" value="0">
            <div><button class="button" type="submit">Create Agent</button></div>
        </form>
        <div class="table-wrap" style="margin-top:16px">
            <table>
                <thead><tr><th>Agent code</th><th>Agent name</th><th>Phone</th><th>2D commission (%)</th><th>Status</th><th>Save</th></tr></thead>
                <tbody>
                @forelse($agents as $agent)
                    <tr>
                        <td>
                            <form id="agent-form-{{ $agent->id }}" method="POST" action="{{ route('admin.settings.agents.update', $agent) }}">
                            @csrf
                            @method('PATCH')
                            </form>
                            <input form="agent-form-{{ $agent->id }}" aria-label="Agent code" name="agent_code" value="{{ $agent->agent_code }}" required>
                            <input form="agent-form-{{ $agent->id }}" type="hidden" name="commission_3d_percent" value="{{ $agent->commission_3d_percent }}">
                        </td>
                        <td><input form="agent-form-{{ $agent->id }}" aria-label="Agent name" name="agent_name" value="{{ $agent->agent_name }}" required></td>
                        <td><input form="agent-form-{{ $agent->id }}" aria-label="Phone" name="phone" value="{{ $agent->phone }}"></td>
                        <td><input form="agent-form-{{ $agent->id }}" aria-label="2D commission percent" type="number" min="0" max="100" step="0.01" name="commission_2d_percent" value="{{ $agent->commission_2d_percent }}" required></td>
                        <td><select form="agent-form-{{ $agent->id }}" aria-label="Agent status" name="status"><option value="active" @selected($agent->status === 'active')>Active</option><option value="inactive" @selected($agent->status === 'inactive')>Inactive</option></select></td>
                        <td><button class="button secondary" type="submit" form="agent-form-{{ $agent->id }}">Save</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No Agents created.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($selectedRound)
            <h3 style="margin-top:24px">Amount Limits · {{ $selectedRound->round_date->format('Y-m-d') }} · Round {{ $selectedRound->round_no }}</h3>
            <p>Per-Agent thresholds for this Round. These do not reject sales; the Sale Entry ledger highlights numbers sold above the selected Agent's threshold.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Agent</th><th>Round handler</th><th>2D Amount Limit</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($agents as $agent)
                        <tr>
                            <td>{{ $agent->agent_code }} · {{ $agent->agent_name }}</td>
                            <td>{{ $agentRoundSettings->get($agent->id)?->operator?->name ?? 'Not claimed yet' }}</td>
                            <td>
                                <form id="agent-amount-limit-{{ $agent->id }}" method="POST" action="{{ route('admin.settings.rounds.agents.amount-limit', [$selectedRound, $agent]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input aria-label="Amount Limit for {{ $agent->agent_name }}" type="number" min="0" step="0.01" name="amount_limit" value="{{ old('amount_limit', $agentRoundSettings->get($agent->id)?->amount_limit) }}" placeholder="Not set">
                                </form>
                            </td>
                            <td><button class="button secondary" type="submit" form="agent-amount-limit-{{ $agent->id }}">Save limit</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Create an Agent before setting an Amount Limit.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card" id="number-rules">
        <h2>Configured number rules</h2>
        <p>W, N, and X are editable here. A/B/R/F/P/parity remain fixed; add other custom one-letter number-list codes below. Entered numbers are excluded only when explicitly placed inside brackets, such as A[Q]1000.</p>
        <div class="grid">
            @foreach($rules as $rule)
                <form method="POST" action="{{ route('admin.settings.rules.update', $rule) }}" class="card" style="padding:18px">
                    @csrf
                    @method('PATCH')
                    <h2>{{ $rule->code }} · {{ $rule->name }}</h2>
                    <p style="margin:4px 0 8px">{{ $rule->description }}</p>
                    <label for="rule-{{ $rule->code }}">Numbers (comma, space, or line separated)</label>
                    <textarea id="rule-{{ $rule->code }}" name="numbers">{{ old("rules.{$rule->code}", implode(', ', $rule->rule_config['numbers'] ?? [])) }}</textarea>
                    <label for="rule-status-{{ $rule->code }}">Status</label>
                    <select id="rule-status-{{ $rule->code }}" name="is_active">
                        <option value="1" @selected($rule->is_active)>Active</option>
                        <option value="0" @selected(! $rule->is_active)>Inactive</option>
                    </select>
                    <button class="button secondary" type="submit" style="margin-top:12px">Save {{ $rule->code }}</button>
                </form>
            @endforeach
        </div>
        <div style="height:1px;background:#edf1ee;margin:24px 0"></div>
        <h2>Custom parser number lists</h2>
        <p>Use a unique uppercase letter from C–Z, excluding reserved codes. Operators enter <strong>Q1000</strong>; bracket exclusions such as <strong>A[Q]1000</strong> are also supported.</p>
        <form method="POST" action="{{ route('admin.settings.rules.custom.store') }}" class="grid" style="align-items:end">
            @csrf
            <div><label for="custom-code">One-letter code</label><input id="custom-code" name="code" value="{{ old('code') }}" maxlength="1" pattern="[A-Z]" placeholder="Q" required></div>
            <div><label for="custom-name">List name</label><input id="custom-name" name="name" value="{{ old('name') }}" maxlength="100" required></div>
            <div style="grid-column:span 2"><label for="custom-numbers">Numbers (comma, space, or line separated)</label><textarea id="custom-numbers" name="numbers" required>{{ old('custom_numbers', old('numbers')) }}</textarea></div>
            <div><button class="button" type="submit">Add custom list</button></div>
        </form>
        @foreach($customNumberLists as $rule)
            <form method="POST" action="{{ route('admin.settings.rules.update', $rule) }}" class="card" style="padding:18px;margin-top:16px">
                @csrf
                @method('PATCH')
                <div class="between">
                    <h2>{{ $rule->code }} · {{ $rule->name }}</h2>
                    <button class="button danger" type="submit" form="delete-custom-rule-{{ $rule->id }}">Delete</button>
                </div>
                <label for="custom-rule-numbers-{{ $rule->id }}">Numbers (comma, space, or line separated)</label>
                <textarea id="custom-rule-numbers-{{ $rule->id }}" name="numbers">{{ old("rules.{$rule->code}", implode(', ', $rule->rule_config['numbers'] ?? [])) }}</textarea>
                <label for="custom-rule-status-{{ $rule->id }}">Status</label>
                <select id="custom-rule-status-{{ $rule->id }}" name="is_active">
                    <option value="1" @selected($rule->is_active)>Active</option>
                    <option value="0" @selected(! $rule->is_active)>Inactive</option>
                </select>
                <button class="button secondary" type="submit" style="margin-top:12px">Save {{ $rule->code }}</button>
            </form>
            <form id="delete-custom-rule-{{ $rule->id }}" method="POST" action="{{ route('admin.settings.rules.custom.destroy', $rule) }}" onsubmit="return confirm('Remove custom parser code {{ $rule->code }}?')">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </section>
</main>
@endsection
