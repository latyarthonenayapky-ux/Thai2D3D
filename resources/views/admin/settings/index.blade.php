@extends('layouts.app', ['title' => __('app.settings.title')])

@section('content')
@php
    $me = auth()->user();
    $idleOptions = [0, 15, 30, 60, 120, 240, 480];
    $permissionLabels = json_encode([
        'granted' => __('app.preferences.permission_granted'),
        'denied' => __('app.preferences.permission_denied'),
        'prompt' => __('app.preferences.permission_prompt'),
        'unsupported' => __('app.preferences.permission_unsupported'),
    ], JSON_UNESCAPED_UNICODE);
    $permissionPrefix = trim(__('app.preferences.notifications_status', ['state' => '']));
@endphp
<main class="main">
    <div class="eyebrow">{{ __('app.settings.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.settings.title') }}</h1>
    <p class="subtitle">{{ __('app.settings.subtitle') }}</p>
    <p class="row">
        <a class="button secondary" href="{{ route('admin.settlements.index') }}">{{ __('app.settings.results_link') }}</a>
        <a class="button secondary" href="{{ route('admin.three-digit.index') }}">{{ __('app.settings.three_digit_link') }}</a>
        <a class="button secondary" href="{{ route('admin.three-digit.results') }}">{{ __('app.settings.three_digit_results_link') }}</a>
    </p>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error" role="alert">
            <strong>{{ __('app.settings.review_problem') }}</strong>
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
                <h2>{{ __('app.settings.schedule_title') }}</h2>
                <p style="margin:0">{!! __('app.settings.schedule_help') !!}</p>
            </div>
            <span class="pill">{{ __('app.settings.yangon') }}</span>
        </div>
        <form method="POST" action="{{ route('admin.settings.schedule') }}">
            @csrf
            @method('PUT')
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ __('app.settings.col_round') }}</th><th>{{ __('app.settings.col_start') }}</th><th>{{ __('app.settings.col_close') }}</th></tr></thead>
                    <tbody>
                    @foreach($schedules as $schedule)
                        <tr>
                            <td><strong>{{ __('app.common.round') }} {{ $schedule->round_no }}</strong><input type="hidden" name="schedules[{{ $schedule->round_no }}][number_limit_3d]" value="{{ $schedule->number_limit_3d }}"></td>
                            <td><input aria-label="{{ __('app.common.round') }} {{ $schedule->round_no }} {{ __('app.settings.col_start') }}" type="time" step="1" name="schedules[{{ $schedule->round_no }}][start_time]" value="{{ old("schedules.{$schedule->round_no}.start_time", $schedule->start_time) }}"></td>
                            <td><input aria-label="{{ __('app.common.round') }} {{ $schedule->round_no }} {{ __('app.settings.col_close') }}" type="time" step="1" name="schedules[{{ $schedule->round_no }}][close_time]" value="{{ old("schedules.{$schedule->round_no}.close_time", $schedule->close_time) }}" required></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <button class="button" type="submit" style="margin-top:18px">{{ __('app.settings.save_schedule') }}</button>
        </form>
    </section>

    <section class="card" id="payout-settings" style="margin-bottom:20px">
        <h2>{{ __('app.settings.payout_title') }}</h2>
        <p>{{ __('app.settings.payout_help') }}</p>
        <form method="POST" action="{{ route('admin.settings.payouts') }}" class="grid" style="align-items:end">
            @csrf
            @method('PUT')
            <div>
                <label for="payout-2d">{{ __('app.settings.payout_2d') }}</label>
                <input id="payout-2d" name="payout_2d_multiplier" type="number" min="0" step="0.01" value="{{ old('payout_2d_multiplier', $businessSettings->payout_2d_multiplier) }}" required>
            </div>
            <div><button class="button" type="submit">{{ __('app.settings.save_payout') }}</button></div>
        </form>
    </section>

    <section class="card" id="rounds" style="margin-bottom:20px">
        <h2>{{ __('app.settings.rounds_title') }}</h2>
        <p>{{ __('app.settings.rounds_help') }}</p>
        @if($rounds->isEmpty())
            <p class="muted">{{ __('app.settings.rounds_empty') }}</p>
            <p class="muted">{!! __('app.settings.rounds_scheduler') !!}</p>
        @else
            <form method="GET" action="{{ route('admin.settings.index') }}" class="row">
                <label for="round_id" style="margin:0">{{ __('app.settings.select_round') }}</label>
                <select id="round_id" name="round_id" style="max-width:380px" onchange="this.form.submit()">
                    @foreach($rounds as $round)
                        <option value="{{ $round->id }}" @selected($selectedRound?->id === $round->id)>
                            {{ $round->round_date->format('Y-m-d') }} · {{ __('app.common.round') }} {{ $round->round_no }} · {{ ucfirst($round->status) }}
                        </option>
                    @endforeach
                </select>
            </form>
            <div class="table-wrap" style="margin-top:16px">
                <table>
                    <thead><tr><th>{{ __('app.common.date') }}</th><th>{{ __('app.common.round') }}</th><th>{{ __('app.settings.col_hours') }}</th><th>{{ __('app.settings.col_blocked') }}</th><th>{{ __('app.common.status') }}</th><th>{{ __('app.common.action') }}</th></tr></thead>
                    <tbody>
                    @foreach($rounds as $round)
                        <tr>
                            <td>{{ $round->round_date->format('Y-m-d') }}</td>
                            <td>{{ $round->round_no }}</td>
                            <td>{{ ($round->start_time ?? __('app.common.auto')) }}–{{ $round->close_time }}</td>
                            <td>{{ implode(', ', $round->number_limits ?? []) ?: __('app.common.none') }}</td>
                            <td>
                                <span class="pill {{ $round->status === 'open' ? '' : 'off' }}">{{ ucfirst($round->status) }}</span>
                                @if($round->status === 'open' && $round->manual_reopen)
                                    <span class="muted">{{ __('app.settings.manually_reopened') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($round->status === 'closed')
                                    <div class="row">
                                        <a class="button secondary" href="{{ route('admin.rounds.settlement', $round) }}">{{ __('app.settings.result_settlement') }}</a>
                                        <form method="POST" action="{{ route('admin.settings.rounds.reopen', $round) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="button secondary" type="submit">{{ __('app.settings.reopen') }}</button>
                                        </form>
                                    </div>
                                @else
                                    <div class="row">
                                        <span class="muted">{{ $round->manual_reopen ? __('app.settings.reopened_hint') : __('app.settings.results_after_close') }}</span>
                                        <form method="POST" action="{{ route('admin.settings.rounds.close', $round) }}" onsubmit="return confirm('{{ __('app.settings.confirm_close') }}')">
                                            @csrf
                                            @method('PATCH')
                                            <button class="button danger" type="submit">{{ $round->manual_reopen ? __('app.settings.reclose_round') : __('app.settings.close_round') }}</button>
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
                @php $roundLabel = $selectedRound->round_date->format('Y-m-d').' · '.__('app.common.round').' '.$selectedRound->round_no; @endphp
                <form method="POST" action="{{ route('admin.settings.rounds.number-limits', $selectedRound) }}" style="max-width:680px;margin-top:22px">
                    @csrf
                    @method('PATCH')
                    <label for="blocked-numbers">{{ __('app.settings.number_limit_label', ['round' => $roundLabel]) }}</label>
                    <p class="muted" style="margin:0 0 8px">{{ __('app.settings.number_limit_help') }}</p>
                    <textarea id="blocked-numbers" name="numbers" placeholder="00, 17, 28&#10;39 46">{{ old('numbers', implode(', ', $selectedRound->number_limits ?? [])) }}</textarea>
                    <button class="button" type="submit" style="margin-top:12px">{{ __('app.settings.save_number_limit') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.settings.hot-numbers.store', $selectedRound) }}" style="max-width:680px">
                    @csrf
                    <label for="hot-numbers">{{ __('app.settings.hot_number_label', ['round' => $roundLabel]) }}</label>
                    <textarea id="hot-numbers" name="numbers" placeholder="00, 17, 28&#10;39 46" required>{{ old('numbers') }}</textarea>
                    <button class="button" type="submit" style="margin-top:12px">{{ __('app.settings.add_hot_numbers') }}</button>
                </form>
                <div class="table-wrap" style="margin-top:16px">
                    <table>
                        <thead><tr><th>{{ __('app.settings.col_number') }}</th><th>{{ __('app.common.action') }}</th></tr></thead>
                        <tbody>
                        @forelse($selectedRound->hotNumbers->sortBy('number') as $hotNumber)
                            <tr>
                                <td><strong>{{ $hotNumber->number }}</strong></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.settings.hot-numbers.destroy', [$selectedRound, $hotNumber]) }}" onsubmit="return confirm('{{ __('app.settings.confirm_remove_hot', ['number' => $hotNumber->number]) }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button danger" type="submit">{{ __('app.settings.remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="muted">{{ __('app.settings.no_hot_numbers') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </section>

    <section class="card" id="agents" style="margin-bottom:20px">
        <h2>{{ __('app.settings.agents_title') }}</h2>
        <p>{{ __('app.settings.agents_help') }}</p>
        <form method="POST" action="{{ route('admin.settings.agents.store') }}" class="grid" style="align-items:end">
            @csrf
            <div><label for="agent_code">{{ __('app.settings.col_agent_code') }}</label><input id="agent_code" name="agent_code" value="{{ old('agent_code') }}" maxlength="255" required></div>
            <div><label for="agent_name">{{ __('app.settings.col_agent_name') }}</label><input id="agent_name" name="agent_name" value="{{ old('agent_name') }}" maxlength="255" required></div>
            <div><label for="agent_phone">{{ __('app.settings.agent_phone_label') }}</label><input id="agent_phone" name="phone" value="{{ old('phone') }}" maxlength="50"></div>
            <div><label for="agent-commission-2d">{{ __('app.settings.col_commission') }}</label><input id="agent-commission-2d" name="commission_2d_percent" type="number" min="0" max="100" step="0.01" value="{{ old('commission_2d_percent', '0.00') }}" required></div>
            <input type="hidden" name="commission_3d_percent" value="0">
            <div><button class="button" type="submit">{{ __('app.settings.create_agent') }}</button></div>
        </form>
        <div class="table-wrap" style="margin-top:16px">
            <table>
                <thead><tr><th>{{ __('app.settings.col_agent_code') }}</th><th>{{ __('app.settings.col_agent_name') }}</th><th>{{ __('app.settings.col_phone') }}</th><th>{{ __('app.settings.col_commission') }}</th><th>{{ __('app.common.status') }}</th><th>{{ __('app.common.save') }}</th></tr></thead>
                <tbody>
                @forelse($agents as $agent)
                    <tr>
                        <td>
                            <form id="agent-form-{{ $agent->id }}" method="POST" action="{{ route('admin.settings.agents.update', $agent) }}">
                            @csrf
                            @method('PATCH')
                            </form>
                            <input form="agent-form-{{ $agent->id }}" aria-label="{{ __('app.settings.col_agent_code') }}" name="agent_code" value="{{ $agent->agent_code }}" required>
                            <input form="agent-form-{{ $agent->id }}" type="hidden" name="commission_3d_percent" value="{{ $agent->commission_3d_percent }}">
                        </td>
                        <td><input form="agent-form-{{ $agent->id }}" aria-label="{{ __('app.settings.col_agent_name') }}" name="agent_name" value="{{ $agent->agent_name }}" required></td>
                        <td><input form="agent-form-{{ $agent->id }}" aria-label="{{ __('app.settings.col_phone') }}" name="phone" value="{{ $agent->phone }}"></td>
                        <td><input form="agent-form-{{ $agent->id }}" aria-label="{{ __('app.settings.col_commission') }}" type="number" min="0" max="100" step="0.01" name="commission_2d_percent" value="{{ $agent->commission_2d_percent }}" required></td>
                        <td><select form="agent-form-{{ $agent->id }}" aria-label="{{ __('app.common.status') }}" name="status"><option value="active" @selected($agent->status === 'active')>{{ __('app.settings.active') }}</option><option value="inactive" @selected($agent->status === 'inactive')>{{ __('app.settings.inactive') }}</option></select></td>
                        <td><button class="button secondary" type="submit" form="agent-form-{{ $agent->id }}">{{ __('app.common.save') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ __('app.settings.no_agents_created') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <h3 style="margin-top:24px">{{ __('app.settings.mobile_access_title') }}</h3>
        <p>{{ __('app.settings.mobile_access_help') }}</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.settings.col_mobile_login_code') }}</th><th>{{ __('app.settings.col_mobile_password') }}</th><th>{{ __('app.settings.mobile_devices') }}</th><th>{{ __('app.common.action') }}</th></tr></thead>
                <tbody>
                @forelse($agents as $agent)
                    <tr>
                        <td>
                            {{ $agent->agent_code }} · {{ $agent->agent_name }}
                            <form id="agent-mobile-{{ $agent->id }}" method="POST" action="{{ route('admin.settings.agents.mobile-access', $agent) }}">
                            @csrf
                            @method('PATCH')
                            </form>
                        </td>
                        <td><input form="agent-mobile-{{ $agent->id }}" aria-label="{{ __('app.settings.col_mobile_login_code') }} · {{ $agent->agent_name }}" name="mobile_login_code" value="{{ old('mobile_login_code', $agent->mobile_login_code) }}" maxlength="32" pattern="[A-Za-z0-9._\-]{4,32}" required></td>
                        <td><input form="agent-mobile-{{ $agent->id }}" aria-label="{{ __('app.settings.col_mobile_password') }} · {{ $agent->agent_name }}" type="password" name="mobile_password" minlength="6" maxlength="64" autocomplete="new-password" placeholder="{{ $agent->mobile_password ? __('app.settings.mobile_password_keep') : __('app.settings.col_mobile_password') }}"></td>
                        <td>@if($agent->mobile_login_code){{ $agent->active_devices_count }}@else<span class="muted">{{ __('app.settings.mobile_not_enabled') }}</span>@endif</td>
                        <td>
                            <button class="button secondary" type="submit" form="agent-mobile-{{ $agent->id }}">{{ __('app.settings.save_mobile_access') }}</button>
                            @if($agent->mobile_login_code)
                                <form method="POST" action="{{ route('admin.settings.agents.mobile-access.devices', $agent) }}" style="display:inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button secondary" type="submit">{{ __('app.settings.mobile_sign_out_devices') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.settings.agents.mobile-access.disable', $agent) }}" style="display:inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button danger" type="submit">{{ __('app.settings.mobile_disable') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('app.settings.no_agents_created') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($selectedRound)
            <h3 style="margin-top:24px">{{ __('app.settings.amount_limits_title') }} · {{ $selectedRound->round_date->format('Y-m-d') }} · {{ __('app.common.round') }} {{ $selectedRound->round_no }}</h3>
            <p>{{ __('app.settings.amount_limits_help') }}</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ __('app.common.agent') }}</th><th>{{ __('app.settings.col_handler') }}</th><th>{{ __('app.settings.amount_limit_2d') }}</th><th>{{ __('app.common.action') }}</th></tr></thead>
                    <tbody>
                    @forelse($agents as $agent)
                        <tr>
                            <td>{{ $agent->agent_code }} · {{ $agent->agent_name }}</td>
                            <td>{{ $agentRoundSettings->get($agent->id)?->operator?->name ?? __('app.settings.not_claimed_yet') }}</td>
                            <td>
                                <form id="agent-amount-limit-{{ $agent->id }}" method="POST" action="{{ route('admin.settings.rounds.agents.amount-limit', [$selectedRound, $agent]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input aria-label="{{ __('app.settings.amount_limit_2d') }} · {{ $agent->agent_name }}" type="number" min="0" step="0.01" name="amount_limit" value="{{ old('amount_limit', $agentRoundSettings->get($agent->id)?->amount_limit) }}" placeholder="{{ __('app.common.not_set') }}">
                                </form>
                            </td>
                            <td><button class="button secondary" type="submit" form="agent-amount-limit-{{ $agent->id }}">{{ __('app.settings.save_limit') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('app.settings.create_agent_first') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card" id="number-rules" style="margin-bottom:20px">
        <h2>{{ __('app.settings.rules_title') }}</h2>
        <p>{{ __('app.settings.rules_help') }}</p>
        <div class="grid">
            @foreach($rules as $rule)
                <form method="POST" action="{{ route('admin.settings.rules.update', $rule) }}" class="card" style="padding:18px">
                    @csrf
                    @method('PATCH')
                    <h2>{{ $rule->code }} · {{ $rule->name }}</h2>
                    <p style="margin:4px 0 8px">{{ $rule->description }}</p>
                    <label for="rule-{{ $rule->code }}">{{ __('app.settings.numbers_label') }}</label>
                    <textarea id="rule-{{ $rule->code }}" name="numbers">{{ old("rules.{$rule->code}", implode(', ', $rule->rule_config['numbers'] ?? [])) }}</textarea>
                    <label for="rule-status-{{ $rule->code }}">{{ __('app.common.status') }}</label>
                    <select id="rule-status-{{ $rule->code }}" name="is_active">
                        <option value="1" @selected($rule->is_active)>{{ __('app.settings.active') }}</option>
                        <option value="0" @selected(! $rule->is_active)>{{ __('app.settings.inactive') }}</option>
                    </select>
                    <button class="button secondary" type="submit" style="margin-top:12px">{{ __('app.settings.save_rule', ['code' => $rule->code]) }}</button>
                </form>
            @endforeach
        </div>
        <div style="height:1px;background:#edf1ee;margin:24px 0"></div>
        <h2>{{ __('app.settings.custom_lists_title') }}</h2>
        <p>{!! __('app.settings.custom_lists_help') !!}</p>
        <form method="POST" action="{{ route('admin.settings.rules.custom.store') }}" class="grid" style="align-items:end">
            @csrf
            <div><label for="custom-code">{{ __('app.settings.custom_code_label') }}</label><input id="custom-code" name="code" value="{{ old('code') }}" maxlength="1" pattern="[A-Z]" placeholder="Q" required></div>
            <div><label for="custom-name">{{ __('app.settings.custom_name_label') }}</label><input id="custom-name" name="name" value="{{ old('name') }}" maxlength="100" required></div>
            <div style="grid-column:span 2"><label for="custom-numbers">{{ __('app.settings.numbers_label') }}</label><textarea id="custom-numbers" name="numbers" required>{{ old('custom_numbers', old('numbers')) }}</textarea></div>
            <div><button class="button" type="submit">{{ __('app.settings.add_custom_list') }}</button></div>
        </form>
        @foreach($customNumberLists as $rule)
            <form method="POST" action="{{ route('admin.settings.rules.update', $rule) }}" class="card" style="padding:18px;margin-top:16px">
                @csrf
                @method('PATCH')
                <div class="between">
                    <h2>{{ $rule->code }} · {{ $rule->name }}</h2>
                    <button class="button danger" type="submit" form="delete-custom-rule-{{ $rule->id }}">{{ __('app.settings.delete') }}</button>
                </div>
                <label for="custom-rule-numbers-{{ $rule->id }}">{{ __('app.settings.numbers_label') }}</label>
                <textarea id="custom-rule-numbers-{{ $rule->id }}" name="numbers">{{ old("rules.{$rule->code}", implode(', ', $rule->rule_config['numbers'] ?? [])) }}</textarea>
                <label for="custom-rule-status-{{ $rule->id }}">{{ __('app.common.status') }}</label>
                <select id="custom-rule-status-{{ $rule->id }}" name="is_active">
                    <option value="1" @selected($rule->is_active)>{{ __('app.settings.active') }}</option>
                    <option value="0" @selected(! $rule->is_active)>{{ __('app.settings.inactive') }}</option>
                </select>
                <button class="button secondary" type="submit" style="margin-top:12px">{{ __('app.settings.save_rule', ['code' => $rule->code]) }}</button>
            </form>
            <form id="delete-custom-rule-{{ $rule->id }}" method="POST" action="{{ route('admin.settings.rules.custom.destroy', $rule) }}" onsubmit="return confirm('{{ __('app.settings.confirm_delete_rule', ['code' => $rule->code]) }}')">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </section>

    <section class="card" id="language" style="margin-bottom:20px">
        <h2>{{ __('app.preferences.language_title') }}</h2>
        <p>{{ __('app.preferences.language_help') }}</p>
        <p class="muted">{{ __('app.preferences.language_current', ['name' => \App\Models\UserPreference::supportedLocales()[$me->locale()] ?? $me->locale()]) }}</p>
        <div class="row">
            @foreach(\App\Models\UserPreference::supportedLocales() as $code => $label)
                <form method="POST" action="{{ route('preferences.locale') }}" class="inline-form">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <button class="button {{ $me->locale() === $code ? '' : 'secondary' }}" type="submit">{{ $label }}@if($me->locale() === $code)&nbsp;&#10003;@endif</button>
                </form>
            @endforeach
        </div>
    </section>

    <section class="card" id="display-preferences" style="margin-bottom:20px">
        <h2>{{ __('app.preferences.display_title') }}</h2>
        <p>{{ __('app.preferences.display_help') }}</p>
        <form method="POST" action="{{ route('preferences.display') }}" class="grid" style="align-items:end">
            @csrf
            @method('PATCH')
            <div>
                <label for="pref-clock-format">{{ __('app.display.clock_format') }}</label>
                <select id="pref-clock-format" name="clock_format">
                    <option value="24" @selected($me->clockFormat() === '24')>{{ __('app.display.hour_24') }}</option>
                    <option value="12" @selected($me->clockFormat() === '12')>{{ __('app.display.hour_12') }}</option>
                </select>
            </div>
            <div>
                <label for="pref-currency-label">{{ __('app.display.currency_label') }}</label>
                <input id="pref-currency-label" name="currency_label" type="text" maxlength="10" value="{{ old('currency_label', $me->currencyLabel()) }}" required>
                <p class="muted" style="margin:6px 0 0">{{ __('app.preferences.currency_hint') }}</p>
            </div>
            <div><button class="button" type="submit">{{ __('app.preferences.save_preferences') }}</button></div>
        </form>
    </section>

    <section class="card" id="sale-entry-preferences" style="margin-bottom:20px">
        <h2>{{ __('app.preferences.sale_title') }}</h2>
        <p>{!! __('app.preferences.sale_help') !!}</p>
        <form method="POST" action="{{ route('preferences.sale-entry') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="sale_typing_normalization" value="off">
            <label style="display:flex;align-items:center;gap:10px;font-weight:500">
                <input class="checkbox" type="checkbox" name="sale_typing_normalization" value="on" @checked($me->prefers('sale_typing_normalization', 'on'))>
                <span>{{ __('app.preferences.sale_normalization') }}</span>
            </label>
            <button class="button" type="submit" style="margin-top:14px">{{ __('app.preferences.save_preferences') }}</button>
        </form>
    </section>

    <section class="card" id="notifications" style="margin-bottom:20px">
        <h2>{{ __('app.preferences.notifications_title') }}</h2>
        <p>{{ __('app.preferences.notifications_help') }}</p>
        <form method="POST" action="{{ route('preferences.notifications') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="review_alerts" value="off">
            <label style="display:flex;align-items:center;gap:10px;font-weight:500">
                <input class="checkbox" type="checkbox" name="review_alerts" value="on" @checked($me->prefers('review_alerts', 'on'))>
                <span>{{ __('app.preferences.notifications_enable') }}</span>
            </label>
            <button class="button" type="submit" style="margin-top:14px">{{ __('app.preferences.save_preferences') }}</button>
        </form>
        <p class="muted" id="notification-permission" data-labels="{{ $permissionLabels }}"></p>
    </section>

    <section class="card" id="security">
        <h2>{{ __('app.preferences.security_title') }}</h2>
        <p>{{ __('app.preferences.security_help') }}</p>
        <form method="POST" action="{{ route('preferences.security') }}" class="grid" style="align-items:end">
            @csrf
            @method('PATCH')
            <div>
                <label for="pref-idle-minutes">{{ __('app.preferences.idle_minutes') }}</label>
                <select id="pref-idle-minutes" name="idle_minutes">
                    @foreach($idleOptions as $minutes)
                        <option value="{{ $minutes }}" @selected((int) ($me->preference('idle_minutes', '0')) === $minutes)>
                            {{ $minutes === 0 ? __('app.preferences.idle_off') : __('app.preferences.idle_'.$minutes) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div><button class="button" type="submit">{{ __('app.preferences.save_preferences') }}</button></div>
        </form>
    </section>
</main>
<script>
    (() => {
        const target = document.getElementById('notification-permission');
        if (!target) return;
        const labels = JSON.parse(target.dataset.labels || '{}');
        const state = 'Notification' in window ? Notification.permission : 'unsupported';
        target.textContent = @json($permissionPrefix) + ' ' + (labels[state] ?? labels.unsupported);
    })();
</script>
@endsection
