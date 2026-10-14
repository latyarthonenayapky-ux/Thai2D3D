@extends('layouts.app', ['title' => __('app.setup.page_title')])

@section('content')
<style>
    .setup-wrap{max-width:1250px;margin:16px auto;padding:0 20px}
    .setup-card{background:var(--surface);border:1px solid var(--border);margin-bottom:12px}
    .setup-title{display:flex;justify-content:space-between;gap:8px;align-items:center;padding:10px 12px;background:var(--surface-soft);border-bottom:1px solid var(--border);font-size:13px;font-weight:800}
    .setup-body{padding:12px}
    .setup-round{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end;max-width:660px}
    .setup-round select{width:100%;min-height:40px;padding:8px 10px;border-radius:4px}
    .setup-round .button{min-height:40px;padding:8px 12px}
    .setup-meta{display:flex;gap:14px;flex-wrap:wrap;margin-top:10px;color:var(--muted);font-size:13px}
    .setup-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--border)}
    .limit-block{background:var(--surface);padding:12px}
    .limit-block h3{margin:0 0 4px;font-size:13px}
    .limit-block p{margin:0;font-size:11px;color:var(--muted)}
    .limit-chips{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}
    .limit-chip{padding:3px 6px;border:1px solid var(--gridline);font-variant-numeric:tabular-nums;font-size:12px;font-weight:700}
    .limit-chip.hot{display:inline-flex;align-items:center;gap:5px;background:var(--accent-soft);color:var(--accent-text)}
    .limit-chip form{display:inline}
    .limit-chip button{border:0;background:transparent;color:var(--danger-text);font:inherit;font-weight:800;cursor:pointer;padding:0}
    .limit-block label{display:block;margin:10px 0 5px;font-size:12px}
    .limit-block textarea{min-height:58px;width:100%;padding:7px 9px;border-radius:4px;font-size:12px}
    .limit-block input[type=text]{width:100%;min-height:36px;padding:7px 9px;border-radius:4px}
    .limit-block .button{margin-top:7px;padding:7px 10px;font-size:12px}
    .limit-list{width:100%;border-collapse:collapse;margin-top:8px;font-size:12px}
    .limit-list th,.limit-list td{padding:6px;border:1px solid var(--gridline);text-align:left;vertical-align:middle}
    .limit-list th{color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.05em}
    .limit-list input{width:104px;min-height:32px;padding:5px 7px;border-radius:4px;font-variant-numeric:tabular-nums}
    .limit-list .button{min-height:32px;padding:5px 8px;font-size:11px}
    .setup-next{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
    .cap-grid{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:5px}
    .cap-cell{display:grid;gap:3px;margin:0;font-size:11px}
    .cap-cell span{text-align:center;color:var(--muted);font-weight:800;font-variant-numeric:tabular-nums}
    .cap-cell input{width:100%;min-height:30px;padding:4px 5px;border-radius:4px;font-size:12px;font-variant-numeric:tabular-nums;text-align:right}
    .cap-grid .button{margin-top:9px;padding:7px 12px;font-size:12px}
    .cap-card .setup-body{padding:12px}
    .setup-next .button{min-height:44px;padding:11px 20px;font-weight:800}
    .setup-note{margin:0;font-size:12px;color:var(--muted)}
    .sale-alert{margin:0 0 12px;padding:9px 12px;border:1px solid var(--border);font-size:12px}
    @media(max-width:900px){.setup-wrap{margin:12px auto;padding:0 10px}.setup-grid{grid-template-columns:1fr}.cap-grid{grid-template-columns:repeat(5,minmax(0,1fr))}}
</style>
<main class="setup-wrap">
    <h1 class="page-title">{{ __('app.setup.heading') }}</h1>
    <p class="setup-note">{{ __('app.setup.intro') }}</p>

    @if(session('status'))
        <div class="notice sale-alert" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error sale-alert" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="setup-card">
        <div class="setup-title"><span>{{ __('app.common.round') }}</span><span>{{ __('app.setup.open_rounds', ['count' => $rounds->count()]) }}</span></div>
        <div class="setup-body">
            <form class="setup-round" method="GET" action="{{ route('operator.sales.setup') }}">
                <div>
                    <label for="setup-round">{{ __('app.setup.choose_round') }}</label>
                    <select id="setup-round" name="round_id" onchange="this.form.requestSubmit()">
                        @foreach($rounds as $availableRound)
                            <option value="{{ $availableRound->id }}" @selected($availableRound->id === $round->id)>
                                {{ __('app.setup.round_option', [
                                    'date' => $availableRound->round_date->format('m-d'),
                                    'no' => $availableRound->round_no,
                                    'start' => $availableRound->start_time ?? __('app.common.auto'),
                                    'close' => $availableRound->close_time,
                                ]) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button class="button secondary" type="submit">{{ __('app.setup.go') }}</button>
            </form>
            <div class="setup-meta">
                <span>{{ __('app.sale.round_line', ['date' => $round->round_date->format('Y-m-d'), 'no' => $round->round_no]) }}</span>
                <span>{{ __('app.common.status_'.$round->status) }}</span>
                <span>{{ __('app.setup.agents_active', ['count' => $agentOptions->count()]) }}</span>
            </div>
        </div>
    </section>

    <section class="setup-card">
        <div class="setup-title"><span>{{ __('app.setup.limits_title') }}</span><span>{{ __('app.setup.limits_hint') }}</span></div>
        <div class="setup-grid">
            <div class="limit-block">
                <h3>{{ __('app.setup.number_limit_title') }}</h3>
                <p>{{ __('app.setup.number_limit_desc') }}</p>
                <div class="limit-chips">
                    @forelse($numberLimits as $blockedNumber)
                        <span class="limit-chip">{{ str_pad((string) $blockedNumber, 2, '0', STR_PAD_LEFT) }}</span>
                    @empty
                        <span class="muted">{{ __('app.setup.nothing_blocked') }}</span>
                    @endforelse
                </div>
                @if(auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('admin.settings.rounds.number-limits', $round) }}">
                        @csrf
                        @method('PATCH')
                        <label for="setup-number-limits">{{ __('app.setup.edit_blocked') }}</label>
                        <textarea id="setup-number-limits" name="numbers" placeholder="00, 17, 28">{{ implode(', ', $numberLimits) }}</textarea>
                        <button class="button secondary" type="submit">{{ __('app.setup.save_number_limit') }}</button>
                    </form>
                @endif
            </div>

            <div class="limit-block">
                <h3>{{ __('app.setup.hot_title') }}</h3>
                <p>{{ __('app.setup.hot_desc') }}</p>
                <div class="limit-chips">
                    @forelse($hotNumbers as $hotNumber)
                        <span class="limit-chip hot">
                            {{ $hotNumber->number }}
                            @if(auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.settings.hot-numbers.destroy', [$round, $hotNumber]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" aria-label="{{ __('app.setup.remove_hot_aria', ['number' => $hotNumber->number]) }}">×</button>
                                </form>
                            @endif
                        </span>
                    @empty
                        <span class="muted">{{ __('app.setup.nothing_hot') }}</span>
                    @endforelse
                </div>
                @if(auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('admin.settings.hot-numbers.store', $round) }}">
                        @csrf
                        <label for="setup-hot-numbers">{{ __('app.setup.add_hot') }}</label>
                        <input id="setup-hot-numbers" type="text" name="numbers" placeholder="{{ __('app.setup.add_hot_placeholder') }}" required>
                        <button class="button secondary" type="submit">{{ __('app.setup.add_hot') }}</button>
                    </form>
                @endif
            </div>

            @if(! auth()->user()->isAdmin())
            <div class="limit-block">
                <h3>{{ __('app.setup.amount_limit_title') }}</h3>
                <p>{{ __('app.setup.amount_limit_desc') }}</p>
                <div class="limit-chips">
                    @forelse($numberAmountLimits as $cappedNumber => $cap)
                        <span class="limit-chip">{{ $cappedNumber }} · {{ number_format((float) $cap, 2) }}</span>
                    @empty
                        <span class="muted">{{ __('app.setup.nothing_capped') }}</span>
                    @endforelse
                </div>
                <p class="setup-note">{{ __('app.setup.caps_readonly_hint') }}</p>
            </div>
            @endif
        </div>
    </section>

    @if(auth()->user()->isAdmin())
        <section class="setup-card cap-card">
            <div class="setup-title"><span>{{ __('app.setup.caps_title') }}</span><span>{{ __('app.setup.caps_hint') }}</span></div>
            <div class="setup-body">
                <form method="POST" action="{{ route('admin.settings.rounds.number-amount-limits', $round) }}">
                    @csrf
                    @method('PATCH')
                    <div class="cap-grid">
                        @for($capNumber = 0; $capNumber < 100; $capNumber++)
                            @php $capKey = str_pad((string) $capNumber, 2, '0', STR_PAD_LEFT); @endphp
                            <label class="cap-cell">
                                <span>{{ $capKey }}</span>
                                <input type="number" name="limits[{{ $capKey }}]" min="0" step="0.01"
                                       value="{{ $numberAmountLimits[$capKey] ?? '' }}" placeholder="—">
                            </label>
                        @endfor
                    </div>
                    <button class="button secondary" type="submit">{{ __('app.setup.caps_save') }}</button>
                </form>
            </div>
        </section>
    @endif

    <section class="setup-card">
        <div class="setup-title"><span>{{ __('app.setup.continue_title') }}</span><span>{{ __('app.setup.agents_ready', ['count' => $selectableCount]) }}</span></div>
        <div class="setup-body setup-next">
            <p class="setup-note">
                @if($activeSession)
                    {{ __('app.setup.you_already_have') }} <strong>{{ $activeSession->agent->agent_code }}</strong> {{ __('app.setup.open_in_round') }}
                @elseif($selectableCount === 0)
                    {{ __('app.setup.all_agents_held') }}
                @else
                    {{ __('app.setup.choose_agent_next') }}
                @endif
            </p>
            <form method="GET" action="{{ route('operator.sales.entry') }}">
                <input type="hidden" name="round_id" value="{{ $round->id }}">
                <button class="button" type="submit" @disabled(! $activeSession && $selectableCount === 0)>{{ __('app.setup.continue_button') }}</button>
            </form>
        </div>
    </section>
</main>
@endsection
