@extends('layouts.app', ['title' => strtoupper($saleMode).' '.__('app.sale.entry').' · '.($agentSession?->agent->agent_code ?? __('app.sale.agent'))])

@section('content')
<!-- THESIS: Spreadsheet-first sale entry keeps draft input separate from server-recorded wagers.
OWN-WORLD: Green ledger rules, compact cells, quiet paper surfaces, and a high-contrast live clock.
STORY: Choose an Agent, stage inputs with Enter, inspect the recent queue, then commit with Save (F1).
FIRST VIEWPORT: Agent and input controls at left, the 00–99 accepted ledger centered, round totals at right.
FORM: Operator ledger, grounded in the supplied worksheet reference; 2D cells preserve the familiar number grid. -->
<style>
    .sale-workspace{max-width:1600px;margin:10px auto;padding:0 20px}
    .sale-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px}
    .sale-heading h1{margin:0;font-size:20px;letter-spacing:-.03em}
    .sale-meta{display:flex;gap:14px;align-items:center;color:var(--muted);font-size:13px}
    .sale-grid{display:grid;grid-template-columns:minmax(430px,2.5fr) minmax(200px,.62fr);gap:12px;align-items:start}
    .sale-panel{min-width:0;background:var(--surface);border:1px solid var(--border)}
    .sale-panel-title{display:flex;justify-content:space-between;gap:8px;align-items:center;padding:7px 12px;background:var(--surface-soft);border-bottom:1px solid var(--border);font-size:13px;font-weight:800}
    .sale-agent-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;min-width:0}
    .sale-agent-row .button{min-height:36px;padding:7px 12px}
    .sale-entry-form{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;min-width:0}
    .sale-entry-form .button{min-height:36px;padding:7px 12px}
    .draft-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center;padding:8px 10px;border-bottom:1px dashed var(--gridline);font-size:13px}
    .draft-row small{display:block;color:var(--muted);margin-top:3px}
    .draft-row .button{padding:4px 8px;font-size:11px}
    .draft-empty{padding:12px;color:var(--muted);font-size:12px}
    .pill.voided{background:var(--surface-soft);color:var(--muted)}
    .sale-void{display:inline-block}
    .sale-void>summary{cursor:pointer;color:var(--accent);font-size:12px;font-weight:700;list-style:none}
    .sale-void>summary::-webkit-details-marker{display:none}
    .sale-void form{display:flex;gap:6px;align-items:center;margin-top:6px}
    .sale-void input{width:150px;min-height:30px;padding:5px 7px;font-size:12px}
    .sale-history .button{min-height:30px;padding:5px 9px;font-size:12px}
    .sale-void-meta{margin-top:4px;font-size:11px}
    .ledger-wrap{max-height:min(67vh,650px);overflow:auto}
    .ledger-wrap-full{max-height:none;overflow:visible}
    .ledger-grid{border-collapse:collapse;table-layout:fixed;width:100%;font-size:12px}
    .ledger-grid th,.ledger-grid td{padding:5px 6px;border:1px solid var(--gridline);white-space:nowrap}
    .ledger-grid th{background:var(--surface-soft);color:var(--muted);text-align:center}
    .ledger-grid .ledger-number{width:9%;text-align:center;font-weight:700}
    .ledger-grid .ledger-amount{width:16%;text-align:right;font-variant-numeric:tabular-nums}
    .ledger-grid .has-sales{background:var(--accent-soft);color:var(--accent-text);font-weight:800}
    /* 00–99 shows in four bands of 25 compact rows, no inner scroll. */
    .ledger-wrap-full .ledger-grid{font-size:13px}
    .ledger-wrap-full .ledger-grid th,.ledger-wrap-full .ledger-grid td{padding:1px 6px;line-height:1.35}
    .ledger-wrap-full .ledger-grid .ledger-num-col{width:7%}
    .ledger-wrap-full .ledger-grid .ledger-amt-col{width:18%}
    .sold-rank-table{border-collapse:collapse;width:100%;font-size:12px}
    .sold-rank-table th,.sold-rank-table td{padding:7px 9px;border:1px solid var(--gridline);font-variant-numeric:tabular-nums}
    .sold-rank-table td:last-child{text-align:right}
    .sale-agent-row form,.sale-agent-row>div{min-width:0}
    .sale-agent-row select{width:100%;min-width:0}
    .rank-wrap{max-height:240px;overflow:auto;margin-top:6px;scrollbar-gutter:stable}
    .sale-rank{table-layout:fixed;width:100%}
    .sale-rank th:first-child,.sale-rank td:first-child{width:34%}
    .sale-rank th:last-child,.sale-rank td:last-child{width:33%}
    .sale-stat{padding:12px;border-bottom:1px solid var(--gridline)}
    .sale-stat:last-child{border-bottom:0}
    .sale-stat-label{display:block;color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
    .sale-stat-value{display:block;margin-top:5px;font-size:21px;font-weight:800;font-variant-numeric:tabular-nums}
    /* Phone / Inter rows: one row per channel, every figure opens its sheet. */
    .sale-sources{width:100%;border-collapse:collapse;margin:0 0 12px;font-size:12px}
    .sale-sources th,.sale-sources td{padding:0;border:1px solid var(--border);background:var(--surface)}
    .sale-sources thead th{background:var(--surface-soft);color:var(--muted);font-size:10px;letter-spacing:.05em;text-transform:uppercase;padding:4px 8px;text-align:right}
    .sale-sources thead th:first-child{text-align:left}
    .sale-sources tbody th{text-align:left;padding:5px 8px;font-size:11px;font-weight:800}
    .sale-sources .source-cell{display:block;width:100%;border:0;background:transparent;color:inherit;font:inherit;font-weight:800;font-variant-numeric:tabular-nums;text-align:right;padding:5px 8px;cursor:pointer}
    .sale-sources .source-cell:hover,.sale-sources .source-cell[aria-expanded=true]{background:var(--accent-soft);color:var(--accent-text)}
    .sale-sources .source-cell.over-limit{color:var(--clock-red)}
    .sale-sources .source-total th{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:5px 8px;border-top:2px solid var(--border)}
    .sale-sources .source-total .button{min-height:28px;padding:4px 9px;font-size:11px;white-space:nowrap}
    .sale-sources .total-cell{display:block;text-align:right;padding:5px 8px;font-weight:800;font-variant-numeric:tabular-nums}
    .sale-sheet{background:var(--surface);border:1px solid var(--border);border-top:0;margin:-12px 0 12px}
    .sale-sheet table{width:100%;border-collapse:collapse;font-size:12px}
    .sale-sheet th,.sale-sheet td{padding:5px 8px;border-bottom:1px solid var(--gridline);text-align:right;font-variant-numeric:tabular-nums}
    .sale-sheet th:first-child,.sale-sheet td:first-child{text-align:left}
    .sale-sheet tfoot td{border-top:1px solid var(--border);border-bottom:0;font-weight:800}
    .sale-sheet-body{max-height:260px;overflow:auto}
    /* Sale input bar: agent, a half-width input, save and the staged queue on one line. */
    .sale-bar{margin-bottom:12px}
    .sale-bar-row{display:grid;grid-template-columns:minmax(180px,.7fr) minmax(200px,.7fr) auto minmax(200px,1fr);gap:10px;align-items:end;padding:10px 12px}
    .sale-bar-row label{margin:0 0 4px;font-size:11px}
    .sale-bar-row select,.sale-bar-row input{width:100%;min-height:36px;padding:6px 8px;border-radius:4px;font-size:13px}
    .sale-bar-row .button{min-height:36px;padding:7px 12px;white-space:nowrap}
    .sale-bar-entry{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end}
    .sale-bar-queue{min-width:0;border:1px solid var(--border);background:var(--surface-soft)}
    .sale-bar-queue-head{display:flex;justify-content:space-between;gap:8px;padding:4px 8px;font-size:10px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--gridline)}
    .draft-strip{max-height:104px;overflow:auto;display:grid;gap:1px;background:var(--gridline)}
    .draft-strip .draft-row{background:var(--surface);border-bottom:0;padding:4px 8px;font-size:12px}
    .draft-strip .draft-row small{margin-top:1px;font-size:10px}
    .draft-strip .draft-empty{padding:8px;background:var(--surface)}
    .sale-bar-note{padding:0 12px 8px;color:var(--muted);font-size:11px;display:flex;gap:12px;flex-wrap:wrap}
    .sale-bar-note kbd{font:inherit;font-weight:800;color:var(--text)}
    .sale-save{min-height:36px}
    .sale-history{margin-top:14px}
    .sale-history>summary{cursor:pointer;list-style:none}
    .sale-history>summary::-webkit-details-marker{display:none}
    .sale-history[open]>summary{border-bottom:1px solid var(--border)}
    .sale-history table{font-size:12px}
    .sale-history th,.sale-history td{padding:8px}
    .sale-search{width:130px;min-height:30px;padding:5px 8px;border-radius:4px}
    .sale-alert{margin:0;padding:9px 12px;border-bottom:1px solid var(--border);font-size:12px}
    .amount-limit-alert{background:var(--danger-bg);color:var(--danger-text);border-left:4px solid var(--clock-red);font-size:13px;font-weight:800}
    .over-limit{color:var(--clock-red);font-weight:900}
    .sale-number-chips{display:flex;gap:4px;flex-wrap:wrap;max-width:470px}
    .sale-number-chip{padding:2px 5px;border:1px solid var(--gridline);font-variant-numeric:tabular-nums}
    .sale-number-chip.rejected{color:var(--danger-text);background:var(--danger-bg)}
    .sale-number-chip.excluded{color:var(--muted);background:var(--surface-soft);text-decoration:line-through}
    .sale-number-chip.voided{color:var(--muted);background:var(--surface-soft);text-decoration:line-through}
    @media(max-width:1060px){.sale-grid{grid-template-columns:1fr}.sale-stats{grid-column:auto}}
    @media(max-width:700px){.sale-workspace{margin:12px auto;padding:0 10px}.sale-heading{align-items:flex-start;flex-direction:column}.sale-meta{flex-wrap:wrap;gap:6px 12px}.sale-bar-row{grid-template-columns:1fr}.sale-sources .source-total th{flex-wrap:wrap}.sale-grid{grid-template-columns:1fr}.sale-ledger{grid-row:2}.sale-stats{grid-column:auto}.ledger-wrap{max-height:420px}.ledger-grid{font-size:11px}.ledger-grid th,.ledger-grid td{padding:4px 3px}.ledger-wrap-full{max-height:none}.ledger-wrap-full .ledger-grid{font-size:11px}.ledger-wrap-full .ledger-grid th,.ledger-wrap-full .ledger-grid td{padding:1px 2px}.sale-history{margin-top:10px}}
</style>
<main class="sale-workspace" data-sale-workspace
      data-sale-mode="{{ $saleMode }}"
      data-sale-action="{{ $agentSession ? route('operator.sales.store', $agentSession) : '' }}"
      data-batch-action="{{ $agentSession ? route('operator.sales.store-batch', $agentSession) : '' }}"
      data-csrf="{{ csrf_token() }}"
      data-storage-key="thai2d3d-sale-drafts-{{ auth()->id() }}-{{ $agentSession?->id ?? 'round-'.$round->id.'-unassigned' }}-{{ $saleMode }}">
    <div class="sale-heading">
        <div>
            <h1>{{ $agentStatement ? __('app.sale.statement') : __('app.sale.entry') }} · {{ strtoupper($saleMode) }}</h1>
            <div class="sale-meta">
                <span>{{ __('app.sale.round_line', ['date' => $round->round_date->format('Y-m-d'), 'no' => $round->round_no]) }}</span>
                <span>{{ __('app.common.status_'.$round->status) }}</span>
                @if($roundWide)<span>{{ __('app.sale.all_agents_round', ['mode' => $round->round_no.'D']) }}</span>@endif
                @if($agentSession && auth()->user()->isAdmin() && $roundWide)
                    <a href="{{ route('operator.sales.show', [$agentSession, 'mode' => $saleMode, 'view' => 'agent']) }}">{{ __('app.sale.statement') }}</a>
                @elseif($agentSession && auth()->user()->isAdmin() && $agentStatement)
                    <a href="{{ route('operator.sales.show', [$agentSession, 'mode' => $saleMode]) }}">{{ __('app.sale.live_all') }}</a>
                @endif
                @if($saleMode === '2d')
                    <a href="{{ route('operator.sales.setup', ['round_id' => $round->id]) }}">{{ __('app.sale.link_round_setup') }}</a>
                @endif
                @if($saleMode === '3d')
                    <a href="{{ route('operator.sales.workspace') }}">2D {{ __('app.sale.entry') }}</a>
                @endif
                @if(auth()->user()->isOperator() && $canEnterSales)
                    <a href="{{ route('operator.sales.offline', $agentSession) }}">{{ __('app.sale.link_offline') }}</a>
                @endif
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="notice sale-alert" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error sale-alert" role="alert">{{ $errors->first() }}</div>
    @endif
    @if($agentSession !== null && ! $canEnterSales)
        <div class="error sale-alert" role="status">
            {{ $roundPastCloseTime ?? false
                ? __('app.sale.closed_by_clock', ['time' => $round->close_time])
                : __('app.sale.closed_session') }}
        </div>
    @endif
    @if($agentSession === null)
        <div class="notice sale-alert" role="status">{{ __('app.sale.choose_agent_first') }}</div>
    @endif
    @if($errors->has('agent'))
        <div class="error sale-alert" role="alert">{{ $errors->first('agent') }}</div>
    @endif
    <div class="sale-alert notice" data-sale-status role="status" aria-live="polite" hidden></div>
    @php
        $limitBannerRows = collect($amountLimitBreaches ?? [])->map(fn (array $breach): string => __('app.sale.over_amount_limit_row', [
            'tier' => $breach['label'],
            'limit' => number_format($breach['limit'], 2),
            'used' => number_format($breach['used'], 2),
            'over' => number_format($breach['over'], 2),
        ]))->all();
    @endphp
    <div class="sale-alert amount-limit-alert" role="alert" data-limit-banner @if($limitBannerRows === [])hidden @endif>
        <strong>{{ __('app.sale.over_amount_limit_title') }}</strong>
        <span data-limit-banner-body>{{ implode('  ·  ', $limitBannerRows) }}</span>
    </div>

    <table class="sale-sources" aria-label="{{ __('app.sale.sources_aria') }}">
        <thead>
            <tr>
                <th>{{ __('app.sale.sources_head') }}</th>
                <th>{{ __('app.sale.accepted_numbers') }}</th>
                <th>{{ __('app.sale.rejected_numbers') }}</th>
                <th>{{ __('app.sale.accepted_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($channelBreakdown as $channel => $channelRow)
                <tr>
                    <th scope="row">{{ __('app.sale.source_'.$channel) }}</th>
                    @foreach([
                        ['figure' => 'numbers', 'rows' => 'numbers', 'value' => $channelRow['accepted_numbers'], 'title' => __('app.sale.sheet_numbers')],
                        ['figure' => 'rejected', 'rows' => 'rejected', 'value' => $channelRow['rejected_numbers'], 'title' => __('app.sale.sheet_rejected')],
                        ['figure' => 'amount', 'rows' => 'numbers', 'value' => $channelRow['accepted_amount'], 'title' => __('app.sale.accepted_amount')],
                    ] as $cell)
                        <td>
                            <button class="source-cell" type="button" data-source="{{ $channel }}" data-figure="{{ $cell['figure'] }}" data-sheet="{{ $cell['rows'] }}" aria-expanded="false"
                                    title="{{ __('app.sale.source_open_aria', ['sheet' => $cell['title'], 'source' => __('app.sale.source_'.$channel)]) }}">
                                {{ $cell['figure'] === 'amount' ? number_format((float) $cell['value'], 2) : number_format((int) $cell['value']) }}
                            </button>
                        </td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="source-total">
                <th scope="row">
                    {{ __('app.sale.sources_total') }}
                    @if($saleMode === '2d')
                        <button class="button secondary" type="button" data-copy-over-limit
                                title="{{ __('app.sale.copy_limit_title') }}">{{ __('app.sale.copy_limit') }}</button>
                    @endif
                </th>
                <td><span class="total-cell" data-accepted-count>{{ number_format($saleMode === '2d' ? $acceptedCount : $threeDigitAcceptedCount) }}</span></td>
                <td><span class="total-cell" data-rejected-count>{{ number_format($saleMode === '2d' ? $rejectedCount : $threeDigitRejectedCount) }}</span></td>
                <td><span class="total-cell {{ ($amountLimitBreaches ?? []) !== [] ? 'over-limit' : '' }}" data-accepted-amount>{{ number_format($saleMode === '2d' ? $acceptedAmount : $threeDigitAcceptedAmount, 2) }}</span></td>
            </tr>
        </tbody>
    </table>
    <div class="sale-sheet" data-source-sheet hidden></div>

    <section class="sale-panel sale-bar" aria-label="{{ __('app.sale.input_panel') }}">
        <div class="sale-panel-title">
            <span>{{ __('app.sale.input_panel') }} · {{ strtoupper($saleMode) }}</span>
            <span>{{ __('app.sale.stage_help') }}</span>
        </div>
        <div class="sale-bar-row">
            @if($saleMode === '2d')
                @php $agentState = $agentSession === null ? '' : ($agentOptions->firstWhere('agent.id', $agentSession->agent_id)['state'] ?? ''); @endphp
                <form class="sale-agent-row" method="POST" action="{{ route('operator.sales.select') }}">
                    @csrf
                    <input type="hidden" name="round_id" value="{{ $round->id }}">
                    @if($agentStatement)<input type="hidden" name="view" value="agent">@endif
                    <div>
                        <label for="sale-agent">{{ __('app.sale.agent_choose') }}</label>
                        <select id="sale-agent" name="agent_id" onchange="this.form.requestSubmit()">
                            @if($agentSession === null)
                                <option value="" selected disabled>{{ __('app.sale.agent_placeholder') }}</option>
                            @endif
                            @foreach($agentOptions as $option)
                                <option value="{{ $option['agent']->id }}" @selected($option['session']?->id === $agentSession?->id) @disabled(! $option['selectable'])>
                                    {{ $option['agent']->agent_code }} · {{ $option['agent']->agent_name }}@if(! $option['selectable']) — {{ $option['state'] }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button class="button secondary" type="submit">{{ __('app.sale.select') }}</button>
                </form>
            @else
                <div class="sale-agent-row">
                    <div>
                        <label for="sale-agent">{{ __('app.sale.agent') }}</label>
                        <select id="sale-agent">
                            @foreach($availableSessions as $availableSession)
                                <option value="{{ $availableSession->id }}" data-url="{{ route('operator.sales.show', [$availableSession, 'mode' => $saleMode, 'view' => $agentStatement ? 'agent' : null]) }}" @selected($availableSession->id === $agentSession->id)>
                                    {{ $availableSession->agent->agent_code }} · {{ $availableSession->agent->agent_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button class="button secondary" type="button" data-focus-agent title="{{ __('app.sale.focus_agent_title') }}">F2</button>
                </div>
            @endif
            <form class="sale-entry-form" data-draft-form>
                <div>
                    <label for="sale-input">{{ __('app.sale.mode_input', ['mode' => strtoupper($saleMode)]) }}</label>
                    <input id="sale-input" type="text" maxlength="255" autocomplete="off" autocapitalize="characters" spellcheck="false" @disabled(! $canEnterSales) autofocus>
                </div>
                <button class="button secondary" type="submit" @disabled(! $canEnterSales)>{{ __('app.sale.add') }}</button>
            </form>
            <button class="button sale-save" type="button" data-save-sales @disabled(! $canEnterSales)>{{ __('app.sale.save_button') }}</button>
            <div class="sale-bar-queue">
                <div class="sale-bar-queue-head">
                    <span>{{ __('app.sale.recent_sales') }}</span>
                    <span><span data-draft-count>0</span></span>
                </div>
                <div class="draft-strip" data-draft-list>
                    <div class="draft-empty">{{ __('app.sale.draft_empty') }}</div>
                </div>
            </div>
        </div>
        <div class="sale-bar-note">
            <span><kbd>Enter</kbd> {{ __('app.sale.shortcut_add') }}</span>
            <span><kbd>F3</kbd> {{ __('app.sale.shortcut_input') }}</span>
            <span><kbd>F1</kbd> {{ __('app.sale.shortcut_save') }}</span>
            @if($saleMode === '2d')
                <span>{{ $agentState ?: ($selectableCount === 0 ? __('app.sale.all_agents_in_use') : __('app.sale.no_agent_chosen')) }}</span>
                <span>{{ __('app.sale.hint_2d') }}</span>
            @endif
        </div>
    </section>

    <div class="sale-grid">
        <section class="sale-panel sale-ledger">
            <div class="sale-panel-title">
                <span>
                    @if($saleMode === '2d' && $roundWide)
                        {{ __('app.sale.live_all') }}
                    @elseif($agentStatement)
                        {{ __('app.sale.statement_for', ['code' => $agentSession->agent->agent_code]) }}
                    @else
                        {{ __('app.sale.live_for', ['code' => $agentSession->agent->agent_code]) }}
                    @endif
                </span>
                <label style="display:flex;align-items:center;gap:6px;margin:0;font-size:10px">
                    {{ __('app.sale.high_to_low') }}
                    <input class="sale-search" type="search" placeholder="{{ __('app.sale.search_placeholder') }}" aria-label="{{ __('app.sale.search_aria') }}" data-sold-search>
                </label>
            </div>
            @if($saleMode === '2d')
                <div class="ledger-wrap ledger-wrap-full">
                    <table class="ledger-grid">
                        <colgroup>@for($band = 0; $band < 4; $band++)<col class="ledger-num-col"><col class="ledger-amt-col">@endfor</colgroup>
                        <thead><tr><th colspan="2">00–24</th><th colspan="2">25–49</th><th colspan="2">50–74</th><th colspan="2">75–99</th></tr></thead>
                        <tbody data-sold-grid>
                        @for($row = 0; $row < 25; $row++)
                            <tr>
                                @foreach([0, 25, 50, 75] as $offset)
                                    @php $number = str_pad((string) ($row + $offset), 2, '0', STR_PAD_LEFT); @endphp
                                    <td class="ledger-number" data-number="{{ $number }}">{{ $number }}</td>
                                    <td class="ledger-amount" data-amount-for="{{ $number }}">—</td>
                                @endforeach
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                </div>
            @else
                <div class="ledger-wrap">
                    <table class="sold-rank-table">
                        <thead><tr><th>{{ __('app.sale.number_3d') }}</th><th>{{ __('app.sale.accepted_amount') }}</th></tr></thead>
                        <tbody data-sold-rank></tbody>
                    </table>
                </div>
            @endif
        </section>

        <aside class="sale-panel sale-stats" aria-label="{{ __('app.sale.stats_aria') }}">
            @if($saleMode === '3d')
                <div class="sale-stat">
                    <span class="sale-stat-label">{{ __('app.sale.limit_3d') }}</span>
                    <span class="sale-stat-value" style="font-size:15px">{{ $round->number_limit_3d ? number_format($round->number_limit_3d) : __('app.sale.not_set') }}</span>
                </div>
            @endif
            @if($saleMode === '2d')
                <div class="sale-stat">
                    <span class="sale-stat-label">{{ __('app.sale.above_limit', ['who' => __('app.sale.all_agents')]) }}</span>
                    <p class="muted" style="margin:5px 0 0">{{ __('app.sale.above_limit_round_desc') }}</p>
                    <div class="rank-wrap">
                        <table class="sold-rank-table sale-rank">
                            <thead><tr><th>{{ __('app.sale.number') }}</th><th>{{ __('app.sale.col_limit') }}</th><th>{{ __('app.sale.col_sold') }}</th><th>{{ __('app.sale.excess') }}</th></tr></thead>
                            <tbody data-over-limit></tbody>
                        </table>
                    </div>
                </div>
            @endif
            <div class="sale-stat">
                <span class="sale-stat-label">{{ __('app.sale.top_sales') }}</span>
                <div class="rank-wrap">
                    <table class="sold-rank-table sale-rank">
                        <thead><tr><th>{{ __('app.sale.number') }}</th><th>{{ __('app.sale.amount') }}</th></tr></thead>
                        <tbody data-top-sales></tbody>
                    </table>
                </div>
            </div>
        </aside>
    </div>

    @if($saleMode === '2d' && ! $canEnterSales && $round->result?->result_2d !== null && $ownSettlement)
        <section class="sale-panel sale-history" aria-label="{{ __('app.sale.settlement_aria') }}">
            <div class="sale-panel-title"><span>{{ __('app.sale.settlement_title') }}</span><span>{{ $round->result->result_2d }}</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ __('app.sale.accepted_stake') }}</th><th>{{ __('app.sale.winning_stake') }}</th><th>{{ __('app.sale.winnings') }}</th><th>{{ __('app.sale.commission') }}</th><th>{{ __('app.sale.business_net') }}</th></tr></thead>
                    <tbody><tr>
                        <td>{{ number_format($ownSettlement['total_stake'], 2) }}</td>
                        <td>{{ number_format($ownSettlement['winning_stake'], 2) }}</td>
                        <td>{{ number_format($ownSettlement['winnings'], 2) }}</td>
                        <td>{{ number_format($ownSettlement['commission'], 2) }}</td>
                        <td>{{ number_format($ownSettlement['business_net'], 2) }}</td>
                    </tr></tbody>
                </table>
            </div>
        </section>
    @endif

    @if($agentSession !== null)
    <details class="sale-panel sale-history">
        <summary class="sale-panel-title">
            <span>{{ __('app.sale.saved_sales', ['mode' => strtoupper($saleMode)]) }}</span>
            <span>{{ __('app.sale.latest_50') }}</span>
        </summary>
        <div class="table-wrap">
            @php
                $actor = auth()->user();
                $mayVoid = $actor->isAdmin()
                    || ((int) $agentSession->operator_id === (int) $actor->id && $round->status === 'open');
                $voidRoute = $saleMode === '2d' ? 'operator.sales.void' : 'operator.three-digit-sales.void';
                $restoreRoute = $saleMode === '2d' ? 'operator.sales.restore' : 'operator.three-digit-sales.restore';
            @endphp
            <table>
                <thead><tr><th>{{ __('app.sale.time') }}</th><th>{{ __('app.sale.input') }}</th><th>{{ __('app.sale.status') }}</th><th>{{ __('app.sale.accepted_amount') }}</th><th>{{ __('app.sale.numbers') }}</th><th>{{ __('app.sale.correct') }}</th></tr></thead>
                <tbody>
                @php $history = $saleMode === '2d' ? $saleInputs : $threeDigitSaleInputs; @endphp
                @forelse($history as $saleInput)
                    <tr>
                        <td>{{ $saleInput->created_at->timezone('Asia/Yangon')->format('H:i:s') }}</td>
                        <td><strong>{{ $saleInput->original_input }}</strong></td>
                        <td>
                            <span class="pill {{ $saleInput->status === 'accepted' ? '' : ($saleInput->status === 'voided' ? 'voided' : 'off') }}">{{ __('app.sale.status_'.$saleInput->status) }}</span>
                            @if($saleInput->status === 'voided')
                                <div class="muted sale-void-meta">{{ $saleInput->voidedBy?->name ?? __('app.sale.admin') }} · {{ $saleInput->voided_at?->timezone('Asia/Yangon')->format('M j, H:i') }} · {{ $saleInput->void_reason }}</div>
                            @endif
                        </td>
                        <td>{{ number_format((float) ($saleMode === '2d' ? $saleInput->details->where('status', 'accepted')->where('is_excluded', false)->sum('amount') : $saleInput->details->where('status', 'accepted')->sum('amount')), 2) }}</td>
                        <td><div class="sale-number-chips">
                            @foreach($saleInput->details as $detail)
                                @php
                                    $voided = $detail->status === 'voided';
                                    $excluded = isset($detail->is_excluded) && $detail->is_excluded;
                                    $reasonKey = 'app.sale.reason_'.strtolower((string) ($detail->reject_reason ?? $detail->status));
                                @endphp
                                <span class="sale-number-chip {{ $voided ? 'voided' : ($excluded ? 'excluded' : ($detail->status === 'accepted' ? '' : 'rejected')) }}">{{ $detail->number }}@if($voided) · {{ __('app.sale.status_voided') }}@elseif($excluded) · {{ __('app.sale.status_excluded') }}@elseif($detail->status !== 'accepted') · {{ Illuminate\Support\Facades\Lang::has($reasonKey) ? __($reasonKey) : str_replace('_', ' ', (string) ($detail->reject_reason ?? $detail->status)) }}@endif</span>
                            @endforeach
                        </div></td>
                        <td>
                            @if($saleInput->status === 'voided')
                                @if($actor->isAdmin() || ((int) $saleInput->voided_by === (int) $actor->id && $mayVoid))
                                    <form method="POST" action="{{ route($restoreRoute, [$agentSession, $saleInput]) }}">
                                        @csrf
                                        <button class="button secondary" type="submit">{{ __('app.sale.restore') }}</button>
                                    </form>
                                @endif
                            @elseif($mayVoid)
                                <details class="sale-void">
                                    <summary>{{ __('app.sale.void') }}</summary>
                                    <form method="POST" action="{{ route($voidRoute, [$agentSession, $saleInput]) }}">
                                        @csrf
                                        <label class="sr-only" for="void-reason-{{ $saleInput->id }}">{{ __('app.sale.reason') }}</label>
                                        <input id="void-reason-{{ $saleInput->id }}" name="reason" required minlength="3" maxlength="255" placeholder="{{ __('app.sale.reason') }}">
                                        <button class="button danger" type="submit">{{ __('app.sale.confirm') }}</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ __('app.sale.no_saved_sales') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </details>
    @endif
</main>
@if($saleMode === '2d')
    <script src="{{ asset('js/sale-entry-keyboard.js') }}"></script>
@endif
<script>
    (() => {
        const workspace = document.querySelector('[data-sale-workspace]');
        const form = document.querySelector('[data-draft-form]');
        const input = document.getElementById('sale-input');
        const agent = document.getElementById('sale-agent');
        const draftList = document.querySelector('[data-draft-list]');
        const draftCount = document.querySelector('[data-draft-count]');
        const status = document.querySelector('[data-sale-status]');
        const limitBanner = document.querySelector('[data-limit-banner]');
        const limitBannerBody = document.querySelector('[data-limit-banner-body]');
        const money = value => Number(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const t = @json(__('app.sale.js'));
        const fmt = (text, vars = {}) => text.replace(/:(\w+)/g, (token, key) =>
            Object.prototype.hasOwnProperty.call(vars, key) ? String(vars[key]) : token);
        let limitBreaches = @json($amountLimitBreaches ?? []);
        const renderLimitBanner = () => {
            if (!limitBreaches.length) return;
            if (limitBanner) {
                limitBannerBody.textContent = limitBreaches.map(row => fmt(t.over_limit_row, {
                    tier: row.label,
                    limit: money(row.limit),
                    used: money(row.used),
                    over: money(row.over),
                })).join('  ·  ');
                limitBanner.hidden = false;
            }
            document.querySelector('[data-accepted-amount]')?.classList.add('over-limit');
        };
        renderLimitBanner();
        const saveButton = document.querySelector('[data-save-sales]');
        const storageKey = workspace.dataset.storageKey;
        const mode = workspace.dataset.saleMode;
        const amountCap = @json($numberAmountLimit);
        @php
            $liveRow = [
                'date' => $round->round_date->format('Y-m-d'),
                'round_no' => (int) $round->round_no,
                'agent' => $agentSession?->agent === null
                    ? '—'
                    : trim($agentSession->agent->agent_code.' · '.(string) $agentSession->agent->agent_name),
            ];
        @endphp
        const liveRow = {!! json_encode($liveRow, JSON_UNESCAPED_UNICODE) !!};
        const channels = @json($channelBreakdown);
        const draftId = () => window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const typingNormalization = @json(auth()->user()->prefers('sale_typing_normalization', 'on'));
        if (mode === '2d' && typingNormalization && window.Thai2D3DSaleEntryKeyboard) {
            input.addEventListener('keydown', event => {
                if (event.code !== 'NumpadDecimal' || event.repeat) return;
                event.preventDefault();
                const normalized = window.Thai2D3DSaleEntryKeyboard.normalizeKeyInput(event.key, event.code);
                const start = input.selectionStart ?? input.value.length;
                const end = input.selectionEnd ?? start;
                input.setRangeText(normalized, start, end, 'end');
                input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: normalized }));
            });
            input.addEventListener('beforeinput', event => {
                if (event.inputType !== 'insertText' || event.isComposing || typeof event.data !== 'string') return;
                const normalized = window.Thai2D3DSaleEntryKeyboard.normalizeTypedText(event.data);
                if (normalized === event.data) return;
                event.preventDefault();
                const start = input.selectionStart ?? input.value.length;
                const end = input.selectionEnd ?? start;
                input.setRangeText(normalized, start, end, 'end');
                input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: normalized }));
            });
        }
        let drafts = [];
        let sold = new Map(@json($soldItems).map(item => [item.number, Number(item.amount)]));
        let overLimitRows = @json($overLimitRows);
        let acceptedCount = Number(document.querySelector('[data-accepted-count]').textContent.replaceAll(',', ''));
        let acceptedAmount = Number(document.querySelector('[data-accepted-amount]').textContent.replaceAll(',', ''));
        let rejectedCount = Number(document.querySelector('[data-rejected-count]').textContent.replaceAll(',', ''));

        const showStatus = (message, isError = false) => {
            status.hidden = false;
            status.classList.toggle('error', isError);
            status.classList.toggle('notice', !isError);
            status.textContent = message;
        };
        const persist = () => localStorage.setItem(storageKey, JSON.stringify(drafts));
        const renderDrafts = () => {
            draftCount.textContent = String(drafts.length);
            draftList.replaceChildren();
            if (!drafts.length) {
                const empty = document.createElement('div');
                empty.className = 'draft-empty';
                empty.textContent = t.draft_empty;
                draftList.append(empty);
                return;
            }
            drafts.forEach(draft => {
                const row = document.createElement('div');
                row.className = 'draft-row';
                const label = document.createElement('span');
                const strong = document.createElement('strong');
                strong.textContent = draft.input;
                const detail = document.createElement('small');
                detail.textContent = `${new Date(draft.createdAt).toLocaleTimeString()}${draft.error ? ` · ${draft.error}` : ` · ${t.not_saved}`}`;
                label.append(strong, detail);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button secondary';
                remove.textContent = t.remove;
                remove.setAttribute('aria-label', fmt(t.remove_aria, {input: draft.input}));
                remove.addEventListener('click', () => {
                    drafts = drafts.filter(item => item.id !== draft.id);
                    persist();
                    renderDrafts();
                });
                row.append(label, remove);
                draftList.append(row);
            });
        };
        const renderSold = () => {
            if (mode === '2d') {
                document.querySelectorAll('[data-amount-for]').forEach(cell => {
                    const amount = sold.get(cell.dataset.amountFor) || 0;
                    cell.textContent = amount ? amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
                    cell.classList.toggle('has-sales', amount > 0);
                    cell.classList.toggle('over-limit', amountCap !== null && amount > Number(amountCap));
                    document.querySelector(`[data-number="${cell.dataset.amountFor}"]`).classList.toggle('has-sales', amount > 0);
                });
                return;
            }
            const body = document.querySelector('[data-sold-rank]');
            const query = document.querySelector('[data-sold-search]').value.trim();
            const rows = [...sold.entries()].filter(([number]) => number.includes(query)).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
            body.replaceChildren();
            if (!rows.length) {
                const row = body.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 2;
                cell.className = 'muted';
                cell.textContent = t.no_matching;
                return;
            }
            rows.forEach(([number, amount]) => {
                const row = body.insertRow();
                row.insertCell().textContent = number;
                row.insertCell().textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        };
        const renderTopSales = () => {
            const body = document.querySelector('[data-top-sales]');
            if (!body) return;
            const rows = [...sold.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).slice(0, 10);
            body.replaceChildren();
            if (!rows.length) {
                const row = body.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 2;
                cell.className = 'muted';
                cell.textContent = t.no_accepted;
                return;
            }
            rows.forEach(([number, amount]) => {
                const row = body.insertRow();
                row.insertCell().textContent = number;
                row.insertCell().textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        };
        const overLimitEntries = () => [...overLimitRows]
            .sort((a, b) => b.excess - a.excess || a.number.localeCompare(b.number));
        const renderOverLimit = () => {
            const body = document.querySelector('[data-over-limit]');
            if (!body) return;
            const rows = overLimitEntries();
            body.replaceChildren();
            if (!rows.length) {
                const row = body.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 4;
                cell.className = 'muted';
                cell.textContent = t.no_over_limit_round;
                return;
            }
            rows.forEach(row => {
                const tr = body.insertRow();
                tr.insertCell().textContent = row.number;
                tr.insertCell().textContent = money(row.limit);
                tr.insertCell().textContent = money(row.sold);
                tr.insertCell().textContent = money(row.excess);
            });
        };
        const sourceSheet = document.querySelector('[data-source-sheet]');
        const sheetHeadings = {
            numbers: [t.sheet_date, t.sheet_round, t.sheet_agent, t.sheet_number, t.sheet_count, t.sheet_amount],
            rejected: [t.sheet_date, t.sheet_round, t.sheet_agent, t.sheet_number, t.sheet_count, t.sheet_amount, t.sheet_reason],
        };
        const sheetCells = {
            numbers: row => [row.date, row.round_no, row.agent, row.number, row.count, money(row.amount)],
            rejected: row => [row.date, row.round_no, row.agent, row.number, row.count, money(row.amount), row.reason],
        };
        // Columns the sheet total row spans before it reaches the count.
        const sheetFooterSpans = {numbers: 4, rejected: 4};
        let openSheet = null;
        const markSourceCells = () => {
            document.querySelectorAll('.source-cell').forEach(cell => {
                const active = openSheet !== null
                    && cell.dataset.source === openSheet.source
                    && cell.dataset.figure === openSheet.figure;
                cell.setAttribute('aria-expanded', active ? 'true' : 'false');
            });
        };
        const renderSourceSheet = () => {
            sourceSheet.replaceChildren();
            if (openSheet === null) {
                sourceSheet.hidden = true;
                return;
            }
            const headings = sheetHeadings[openSheet.rows];
            const rows = channels[openSheet.source][openSheet.rows];
            const title = document.createElement('div');
            title.className = 'sale-panel-title';
            const label = document.createElement('span');
            label.textContent = openSheet.title;
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'button secondary';
            close.textContent = t.sheet_close;
            close.addEventListener('click', () => toggleSourceSheet(openSheet.element));
            title.append(label, close);
            const table = document.createElement('table');
            const head = document.createElement('thead');
            const headRow = head.insertRow();
            headings.forEach(text => {
                headRow.insertCell().textContent = text;
            });
            const body = document.createElement('tbody');
            if (!rows.length) {
                const empty = body.insertRow();
                const cell = empty.insertCell();
                cell.colSpan = headings.length;
                cell.className = 'muted';
                cell.textContent = t.sheet_empty;
            }
            rows.forEach(row => {
                const tr = body.insertRow();
                sheetCells[openSheet.rows](row).forEach(value => {
                    tr.insertCell().textContent = value;
                });
            });
            table.append(head, body);
            if (rows.length) {
                // One Agent per row means the office needs the channel's own total under it.
                const foot = document.createElement('tfoot');
                const footRow = foot.insertRow();
                const footLabel = footRow.insertCell();
                footLabel.colSpan = sheetFooterSpans[openSheet.rows];
                footLabel.textContent = t.sheet_total;
                footRow.insertCell().textContent = rows.reduce((sum, row) => sum + Number(row.count), 0).toLocaleString();
                footRow.insertCell().textContent = money(rows.reduce((sum, row) => sum + Number(row.amount), 0));
                if (openSheet.rows === 'rejected') footRow.insertCell().textContent = '';
                table.append(foot);
            }
            const scroll = document.createElement('div');
            scroll.className = 'sale-sheet-body';
            scroll.append(table);
            sourceSheet.append(title, scroll);
            sourceSheet.hidden = false;
        };
        const toggleSourceSheet = (cell) => {
            openSheet = openSheet !== null && openSheet.cell === cell
                ? null
                : {cell, source: cell.dataset.source, figure: cell.dataset.figure, rows: cell.dataset.sheet, title: cell.title};
            markSourceCells();
            renderSourceSheet();
        };
        document.querySelectorAll('.source-cell').forEach(cell => {
            cell.addEventListener('click', () => toggleSourceSheet(cell));
        });
        const round2 = value => Math.round(Number(value) * 100) / 100;
        const mergeChannelNumber = (rows, number, amount, reason) => {
            const row = rows.find(entry => entry.number === number
                && entry.date === liveRow.date
                && entry.round_no === liveRow.round_no
                && entry.agent === liveRow.agent
                && (reason === undefined || entry.reason === reason));
            if (row) {
                row.count += 1;
                row.amount = round2(row.amount + amount);
            } else {
                rows.push({
                    ...liveRow,
                    number,
                    count: 1,
                    amount: round2(amount),
                    source: 'web',
                    ...(reason === undefined ? {} : {reason}),
                });
            }
            rows.sort((a, b) => String(a.agent).localeCompare(String(b.agent))
                || String(b.date).localeCompare(String(a.date))
                || b.amount - a.amount
                || String(a.number).localeCompare(String(b.number)));
        };
        const writeSourceCells = () => {
            document.querySelectorAll('.source-cell').forEach(cell => {
                const row = channels[cell.dataset.source];
                const figure = cell.dataset.figure === 'amount'
                    ? money(row.accepted_amount)
                    : String(row[cell.dataset.figure === 'numbers' ? 'accepted_numbers' : 'rejected_numbers']).toLocaleString();
                cell.textContent = figure;
            });
        };
        const addAcceptedDetails = (details, inputText) => {
            const web = channels.web;
            let lineTotal = 0;
            for (const detail of details) {
                if (detail.is_excluded) continue;
                const amount = Number(detail.amount);
                if (detail.status !== 'accepted') {
                    rejectedCount++;
                    web.rejected_numbers += 1;
                    mergeChannelNumber(
                        web.rejected,
                        String(detail.number),
                        amount,
                        detail.reject_reason ? detail.reject_reason.replaceAll('_', ' ') : t.status_rejected
                    );
                    continue;
                }
                sold.set(detail.number, (sold.get(detail.number) || 0) + amount);
                acceptedCount++;
                acceptedAmount += amount;
                lineTotal += amount;
                web.accepted_numbers += 1;
                web.accepted_amount = round2(web.accepted_amount + amount);
                mergeChannelNumber(web.numbers, String(detail.number), amount);
            }
            if (inputText) {
                web.inputs.unshift({
                    time: new Date().toLocaleTimeString(),
                    ...liveRow,
                    input: inputText,
                    count: details.length,
                    total: round2(lineTotal),
                });
                web.inputs.splice(50);
            }
            document.querySelector('[data-accepted-count]').textContent = acceptedCount.toLocaleString();
            document.querySelector('[data-accepted-amount]').textContent = acceptedAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.querySelector('[data-rejected-count]').textContent = rejectedCount.toLocaleString();
            renderSold();
            renderTopSales();
            renderOverLimit();
            writeSourceCells();
            if (openSheet !== null && openSheet.source === 'web') renderSourceSheet();
        };
        try {
            const storedDrafts = localStorage.getItem(storageKey);
            if (storedDrafts) {
                const parsed = JSON.parse(storedDrafts);
                if (!Array.isArray(parsed) || parsed.some(draft => typeof draft.input !== 'string' || typeof draft.id !== 'string')) {
                    throw new Error(t.drafts_invalid);
                }
                drafts = parsed;
            }
        } catch (error) {
            showStatus(`${error.message} ${t.drafts_invalid_hint}`, true);
        }
        renderDrafts();
        renderSold();
        renderTopSales();
        renderOverLimit();

        const stageInputs = values => {
            const staged = [];
            for (const raw of values) {
                const value = raw.trim();
                if (!value) continue;
                staged.push({ id: draftId(), input: value, createdAt: new Date().toISOString() });
            }
            if (!staged.length) return false;
            drafts.push(...staged);
            try {
                persist();
                renderDrafts();
                showStatus(staged.length === 1
                    ? fmt(t.staged_one, {input: staged[0].input})
                    : fmt(t.staged_many, {count: staged.length}));
                input.focus();
                return true;
            } catch (error) {
                drafts = drafts.filter(draft => !staged.includes(draft));
                showStatus(fmt(t.storage_failed, {error: error.message}), true);
                return false;
            }
        };
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (!input.value.trim()) return;
            if (stageInputs([input.value])) input.value = '';
        });
        input.addEventListener('paste', event => {
            const text = event.clipboardData?.getData('text');
            if (!text || !/[\r\n]/.test(text)) return;
            event.preventDefault();
            if (stageInputs(text.split(/[\r\n]+/))) input.value = '';
        });
        saveButton.addEventListener('click', async () => {
            if (!drafts.length) {
                showStatus(t.empty_queue);
                input.focus();
                return;
            }
            saveButton.disabled = true;
            let saved = 0;
            let failed = 0;
            let fatal = false;
            const pending = [...drafts];
            const batchSize = 50;
            for (let index = 0; index < pending.length; index += batchSize) {
                const chunk = pending.slice(index, index + batchSize);
                try {
                    const response = await fetch(workspace.dataset.batchAction, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': workspace.dataset.csrf,
                        },
                        body: JSON.stringify({
                            sale_type: mode,
                            inputs: chunk.map(draft => ({ input: draft.input, client_uuid: draft.id })),
                        }),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = result.message || fmt(t.server_status, {status: response.status});
                        for (const draft of chunk) {
                            draft.error = message;
                            failed++;
                        }
                        persist();
                        renderDrafts();
                        if ([401, 419, 500, 502, 503, 504].includes(response.status)) break;
                        continue;
                    }
                    for (const draft of chunk) {
                        const item = (result.results || []).find(entry => entry.client_uuid === draft.id);
                        if (!item) {
                            draft.error = t.no_response;
                            failed++;
                        } else if (item.status === 'saved') {
                            addAcceptedDetails(item.details || [], draft.input);
                            drafts = drafts.filter(row => row.id !== draft.id);
                            saved++;
                        } else {
                            draft.error = item.message || t.server_rejected;
                            failed++;
                        }
                    }
                    if (Array.isArray(result.amount_limit_breaches)) limitBreaches = result.amount_limit_breaches;
                    if (Array.isArray(result.over_limit_rows)) overLimitRows = result.over_limit_rows;
                    renderLimitBanner();
                    persist();
                    renderDrafts();
                    renderOverLimit();
                } catch (error) {
                    for (const draft of chunk) {
                        draft.error = t.connection_failed;
                        failed++;
                    }
                    try { persist(); } catch (storageError) {
                        showStatus(fmt(t.connection_storage_failed, {error: storageError.message}), true);
                        fatal = true;
                        break;
                    }
                    renderDrafts();
                    break;
                }
            }
            saveButton.disabled = false;
            if (!fatal) {
                showStatus(fmt(saved === 1 ? t.saved_single : t.saved_plural, {
                    count: saved,
                    extra: failed ? fmt(t.saved_failed, {count: failed}) : '',
                }), failed > 0);
            }
        });
        const focusAgent = document.querySelector('[data-focus-agent]');
        if (agent && focusAgent) focusAgent.addEventListener('click', () => agent.focus());
        if (agent) {
            agent.addEventListener('change', () => {
                const url = agent.selectedOptions[0]?.dataset?.url;
                if (url) window.location.assign(url);
            });
        }
        const plainAmount = value => value.toFixed(2).replace(/\.?0+$/, '');
        const copyButton = document.querySelector('[data-copy-over-limit]');
        copyButton?.addEventListener('click', async () => {
            const rows = overLimitEntries();
            if (!rows.length) {
                showStatus(t.copy_empty, true);
                return;
            }
            const text = rows.map(row => `${row.number}=${plainAmount(row.excess)}`).join('\n');
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const holder = document.createElement('textarea');
                    holder.value = text;
                    holder.setAttribute('readonly', '');
                    holder.style.cssText = 'position:fixed;top:-1000px';
                    document.body.append(holder);
                    holder.select();
                    const copied = document.execCommand('copy');
                    holder.remove();
                    if (!copied) throw new Error(t.copy_refused);
                }
                showStatus(fmt(t.copied, {count: rows.length}));
            } catch (error) {
                showStatus(fmt(t.copy_failed, {error: error.message}), true);
            }
        });
        document.querySelector('[data-sold-search]').addEventListener('input', renderSold);
        window.addEventListener('keydown', event => {
            if (event.key === 'F1') {
                event.preventDefault();
                saveButton.click();
            } else if (event.key === 'F2') {
                event.preventDefault();
                (agent ?? focusAgent).focus();
            } else if (event.key === 'F3') {
                event.preventDefault();
                input.focus();
            } else if (event.key === 'F8') {
                event.preventDefault();
                const search = document.querySelector('[data-sold-search]');
                search.focus();
            }
        });
    })();
</script>
@endsection
