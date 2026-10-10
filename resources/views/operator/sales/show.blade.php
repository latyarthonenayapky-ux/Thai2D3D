@extends('layouts.app', ['title' => $saleMode.' Sale Entry · '.$agentSession->agent->agent_code])

@section('content')
<!-- THESIS: Spreadsheet-first sale entry keeps draft input separate from server-recorded wagers.
OWN-WORLD: Green ledger rules, compact cells, quiet paper surfaces, and a high-contrast live clock.
STORY: Choose an Agent, stage inputs with Enter, inspect the recent queue, then commit with Save (F1).
FIRST VIEWPORT: Agent and input controls at left, the 00–99 accepted ledger centered, round totals at right.
FORM: Operator ledger, grounded in the supplied worksheet reference; 2D cells preserve the familiar number grid. -->
<style>
    .sale-workspace{max-width:1600px;margin:16px auto;padding:0 20px}
    .sale-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px}
    .sale-heading h1{margin:0;font-size:23px;letter-spacing:-.03em}
    .sale-meta{display:flex;gap:14px;align-items:center;color:var(--muted);font-size:13px}
    .sale-grid{display:grid;grid-template-columns:minmax(250px,.88fr) minmax(440px,1.72fr) minmax(235px,.72fr);gap:12px;align-items:start}
    .sale-panel{min-width:0;background:var(--surface);border:1px solid var(--border)}
    .sale-panel-title{display:flex;justify-content:space-between;gap:8px;align-items:center;padding:10px 12px;background:var(--surface-soft);border-bottom:1px solid var(--border);font-size:13px;font-weight:800}
    .sale-controls{padding:12px}
    .sale-controls label{margin:0 0 5px;font-size:12px}
    .sale-controls select,.sale-controls input{min-height:40px;padding:8px 10px;border-radius:4px}
    .sale-agent-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end}
    .sale-agent-row .button{min-height:40px;padding:8px 12px}
    .sale-entry-form{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;margin-top:14px}
    .sale-entry-form .button{min-height:40px;padding:8px 12px}
    .sale-shortcuts{display:flex;gap:10px;margin-top:8px;color:var(--muted);font-size:11px}
    .sale-shortcuts kbd{font:inherit;font-weight:800;color:var(--text)}
    .draft-list{max-height:280px;overflow:auto}
    .draft-row{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center;padding:8px 10px;border-bottom:1px dashed var(--gridline);font-size:13px}
    .draft-row small{display:block;color:var(--muted);margin-top:3px}
    .draft-row .button{padding:4px 8px;font-size:11px}
    .draft-empty{padding:12px;color:var(--muted);font-size:12px}
    .ledger-wrap{max-height:min(67vh,650px);overflow:auto}
    .ledger-grid{border-collapse:collapse;table-layout:fixed;width:100%;font-size:12px}
    .ledger-grid th,.ledger-grid td{padding:5px 6px;border:1px solid var(--gridline);white-space:nowrap}
    .ledger-grid th{background:var(--surface-soft);color:var(--muted);text-align:center}
    .ledger-grid .ledger-number{width:9%;text-align:center;font-weight:700}
    .ledger-grid .ledger-amount{width:16%;text-align:right;font-variant-numeric:tabular-nums}
    .ledger-grid .has-sales{background:var(--accent-soft);color:var(--accent-text);font-weight:800}
    .sold-rank-table{border-collapse:collapse;width:100%;font-size:12px}
    .sold-rank-table th,.sold-rank-table td{padding:7px 9px;border:1px solid var(--gridline);font-variant-numeric:tabular-nums}
    .sold-rank-table td:last-child{text-align:right}
    .sale-stat{padding:12px;border-bottom:1px solid var(--gridline)}
    .sale-stat:last-child{border-bottom:0}
    .sale-stat-label{display:block;color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
    .sale-stat-value{display:block;margin-top:5px;font-size:21px;font-weight:800;font-variant-numeric:tabular-nums}
    .sale-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;background:var(--border);border:1px solid var(--border);margin-bottom:12px}
    .sale-summary .sale-stat{background:var(--surface);padding:9px 12px;border:0}
    .sale-summary .sale-stat-value{font-size:17px}
    .sale-admin-controls{padding:12px;border-top:1px solid var(--border);display:grid;gap:14px}
    .sale-admin-controls h3{margin:0 0 5px;font-size:13px}
    .sale-admin-controls p{margin:0 0 6px}
    .sale-admin-controls label{margin:0 0 5px}
    .sale-admin-controls textarea{min-height:62px;padding:7px 9px;border-radius:4px;font-size:12px}
    .sale-admin-controls input{min-height:36px;padding:7px 9px;border-radius:4px}
    .sale-admin-controls .button{padding:7px 10px;font-size:12px}
    .hot-number-list{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}
    .hot-number-item{display:inline-flex;align-items:center;gap:5px;border:1px solid var(--gridline);padding:3px 5px;font-size:11px}
    .hot-number-item form{display:inline}
    .hot-number-item button{border:0;background:transparent;color:var(--danger-text);font:inherit;font-weight:800;cursor:pointer}
    .sale-save{margin:12px;width:calc(100% - 24px);min-height:44px}
    .sale-history{margin-top:14px}
    .sale-history table{font-size:12px}
    .sale-history th,.sale-history td{padding:8px}
    .sale-search{width:130px;min-height:30px;padding:5px 8px;border-radius:4px}
    .sale-alert{margin:0;padding:9px 12px;border-bottom:1px solid var(--border);font-size:12px}
    .sale-number-chips{display:flex;gap:4px;flex-wrap:wrap;max-width:470px}
    .sale-number-chip{padding:2px 5px;border:1px solid var(--gridline);font-variant-numeric:tabular-nums}
    .sale-number-chip.rejected{color:var(--danger-text);background:var(--danger-bg)}
    .sale-number-chip.excluded{color:var(--muted);background:var(--surface-soft);text-decoration:line-through}
    @media(max-width:1060px){.sale-grid{grid-template-columns:minmax(230px,.85fr) minmax(360px,1.4fr)}.sale-stats{grid-column:1/-1}}
    @media(max-width:700px){.sale-workspace{margin:12px auto;padding:0 10px}.sale-heading{align-items:flex-start;flex-direction:column}.sale-meta{flex-wrap:wrap;gap:6px 12px}.sale-summary{grid-template-columns:1fr}.sale-summary .sale-stat{padding:8px 10px}.sale-summary .sale-stat-value{font-size:16px}.sale-grid{grid-template-columns:1fr}.sale-ledger{grid-row:2}.sale-stats{grid-column:auto}.ledger-wrap{max-height:420px}.ledger-grid{font-size:11px}.ledger-grid th,.ledger-grid td{padding:4px 3px}.sale-history{margin-top:10px}}
</style>
<main class="sale-workspace" data-sale-workspace
      data-sale-mode="{{ $saleMode }}"
      data-sale-action="{{ route('operator.sales.store', $agentSession) }}"
      data-csrf="{{ csrf_token() }}"
      data-storage-key="thai2d3d-sale-drafts-{{ auth()->id() }}-{{ $agentSession->id }}-{{ $saleMode }}">
    <div class="sale-heading">
        <div>
            <h1>{{ $agentStatement ? 'Agent Statement' : 'Sale Entry' }} · {{ strtoupper($saleMode) }}</h1>
            <div class="sale-meta">
                <span>{{ $agentSession->round->round_date->format('Y-m-d') }} · Round {{ $agentSession->round->round_no }}</span>
                <span>{{ ucfirst($agentSession->round->status) }}</span>
                @if($roundWide)<span>All Agents · {{ $agentSession->round->round_no }}D round</span>@endif
                @if(auth()->user()->isAdmin() && $roundWide)
                    <a href="{{ route('operator.sales.show', [$agentSession, 'mode' => $saleMode, 'view' => 'agent']) }}">Agent Statement</a>
                @elseif(auth()->user()->isAdmin() && $agentStatement)
                    <a href="{{ route('operator.sales.show', [$agentSession, 'mode' => $saleMode]) }}">Live of items sold · all Agents</a>
                @endif
                <a href="{{ route('operator.sales.workspace') }}">Choose another Agent</a>
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="notice sale-alert" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error sale-alert" role="alert">{{ $errors->first() }}</div>
    @endif
    @if(! $canEnterSales)
        <div class="error sale-alert" role="status">This Round or Agent session is closed. The ledger is read-only.</div>
    @endif
    <div class="sale-alert notice" data-sale-status role="status" aria-live="polite" hidden></div>

    <section class="sale-summary" aria-label="Round sales totals">
        <div class="sale-stat">
            <span class="sale-stat-label">Accepted numbers</span>
            <span class="sale-stat-value" data-accepted-count>{{ number_format($saleMode === '2d' ? $acceptedCount : $threeDigitAcceptedCount) }}</span>
        </div>
        <div class="sale-stat">
            <span class="sale-stat-label">Accepted amount</span>
            <span class="sale-stat-value" data-accepted-amount>{{ number_format($saleMode === '2d' ? $acceptedAmount : $threeDigitAcceptedAmount, 2) }}</span>
        </div>
        <div class="sale-stat">
            <span class="sale-stat-label">Rejected numbers</span>
            <span class="sale-stat-value" data-rejected-count>{{ number_format($saleMode === '2d' ? $rejectedCount : $threeDigitRejectedCount) }}</span>
        </div>
    </section>

    <div class="sale-grid">
        <section class="sale-panel">
            <div class="sale-panel-title"><span>Sale input</span><span>{{ strtoupper($saleMode) }}</span></div>
            <div class="sale-controls">
                <div class="sale-agent-row">
                    <div>
                        <label for="sale-agent">Agent</label>
                        <select id="sale-agent">
                            @if(auth()->user()->isAdmin() && $saleMode === '2d')
                                @foreach($agentChoices as $choice)
                                    <option
                                        value="{{ $choice['url'] ?? '' }}"
                                        data-claim-url="{{ $choice['claim_url'] ?? '' }}"
                                        @selected($choice['selected'])
                                        @disabled(! $choice['selectable'])
                                    >
                                        {{ $choice['agent']->agent_code }} · {{ $choice['agent']->agent_name }} — {{ $choice['status'] }}
                                    </option>
                                @endforeach
                            @else
                                @foreach($availableSessions as $availableSession)
                                    <option value="{{ route('operator.sales.show', [$availableSession, 'mode' => $saleMode, 'view' => $agentStatement ? 'agent' : null]) }}" @selected($availableSession->id === $agentSession->id)>
                                        {{ $availableSession->agent->agent_code }} · {{ $availableSession->agent->agent_name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <button class="button secondary" type="button" data-focus-agent title="Select Agent (F2)">F2</button>
                </div>
                @if(auth()->user()->isAdmin() && $saleMode === '2d')
                    <form method="POST" action="" data-agent-claim-form hidden>
                        @csrf
                        @if($agentStatement)<input type="hidden" name="view" value="agent">@endif
                    </form>
                @endif
                <form class="sale-entry-form" data-draft-form>
                    <div>
                        <label for="sale-input">{{ strtoupper($saleMode) }} input</label>
                        <input id="sale-input" type="text" maxlength="255" autocomplete="off" autocapitalize="characters" spellcheck="false" @disabled(! $canEnterSales) autofocus>
                    </div>
                    <button class="button secondary" type="submit" @disabled(! $canEnterSales)>Add</button>
                </form>
                <div class="sale-shortcuts"><span><kbd>Enter</kbd> Add to Recent Sales</span><span><kbd>F3</kbd> Input</span><span><kbd>F1</kbd> Save all</span></div>
                @if($saleMode === '2d' && config('app.desktop_mode'))
                    <div class="muted">2D codes auto-uppercase; typing * enters R. Pasted legacy * lists remain unchanged.</div>
                @endif
            </div>
            <div class="sale-panel-title"><span>Recent Sales</span><span data-draft-count>0</span></div>
            <div class="draft-list" data-draft-list>
                <div class="draft-empty">Enter a sale input to stage it here. Nothing is sent to the server until Save (F1).</div>
            </div>
            <button class="button sale-save" type="button" data-save-sales @disabled(! $canEnterSales)>Save · F1</button>
        </section>

        <section class="sale-panel sale-ledger">
            <div class="sale-panel-title">
                <span>
                    @if($saleMode === '2d' && $roundWide)
                        Live of items sold · all Agents
                    @elseif($agentStatement)
                        Agent Statement · {{ $agentSession->agent->agent_code }}
                    @else
                        Live of items sold · {{ $agentSession->agent->agent_code }}
                    @endif
                </span>
                <label style="display:flex;align-items:center;gap:6px;margin:0;font-size:10px">
                    Highest to lowest amount
                    <input class="sale-search" type="search" placeholder="Search · F8" aria-label="Search sold items" data-sold-search>
                </label>
            </div>
            @if($saleMode === '2d')
                <div class="ledger-wrap">
                    <table class="ledger-grid">
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
                        <thead><tr><th>3D number</th><th>Accepted amount</th></tr></thead>
                        <tbody data-sold-rank></tbody>
                    </table>
                </div>
            @endif
        </section>

        <aside class="sale-panel sale-stats" aria-label="Accepted and rejected sales totals">
            @if($saleMode === '3d')
                <div class="sale-stat">
                    <span class="sale-stat-label">3D number count limit</span>
                    <span class="sale-stat-value" style="font-size:15px">{{ $agentSession->round->number_limit_3d ? number_format($agentSession->round->number_limit_3d) : 'Not set' }}</span>
                </div>
            @endif
            <div class="sale-stat">
                <span class="sale-stat-label">Top sales · high → low</span>
                <table class="sold-rank-table" style="margin-top:6px">
                    <thead><tr><th>No.</th><th>Amount</th></tr></thead>
                    <tbody data-top-sales></tbody>
                </table>
            </div>
            @if($saleMode === '2d')
                <div class="sale-stat">
                    <span class="sale-stat-label">Above Amount Limit · {{ $agentSession->agent->agent_code }}</span>
                    <p class="muted" style="margin:5px 0 0">This Agent's per-number accepted amount above {{ $amountLimit === null ? 'no limit set' : number_format((float) $amountLimit, 2) }}; sorted by excess.</p>
                    <table class="sold-rank-table" style="margin-top:6px">
                        <thead><tr><th>No.</th><th>Excess</th></tr></thead>
                        <tbody data-over-limit>
                        @php
                            $overLimitRows = collect($agentSoldItems)
                                ->filter(fn (array $item): bool => $amountLimit !== null && $item['amount'] > (float) $amountLimit)
                                ->map(fn (array $item): array => ['number' => $item['number'], 'excess' => $item['amount'] - $amountLimit])
                                ->sortByDesc('excess')
                                ->values();
                        @endphp
                        @forelse($overLimitRows as $item)
                            <tr><td>{{ $item['number'] }}</td><td>{{ number_format($item['excess'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">No number exceeds this Agent's Amount Limit.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </aside>
    </div>

    @if(auth()->user()->isAdmin() && $saleMode === '2d')
        <section class="sale-panel sale-admin-controls" aria-label="Round controls">
            <h2 style="margin:0;font-size:15px">Round controls · {{ $agentSession->round->round_date->format('Y-m-d') }} · Round {{ $agentSession->round->round_no }}</h2>
            <form method="POST" action="{{ route('admin.settings.rounds.agents.amount-limit', [$agentSession->round, $agentSession->agent]) }}">
                @csrf
                @method('PATCH')
                <label for="sale-amount-limit">Amount Limit · {{ $agentSession->agent->agent_code }} (per Agent)</label>
                <input id="sale-amount-limit" type="number" name="amount_limit" min="0" step="0.01" value="{{ $amountLimit ?? '' }}" placeholder="Not set">
                <button class="button secondary" type="submit" style="margin-top:7px">Save Amount Limit</button>
            </form>
            <form method="POST" action="{{ route('admin.settings.rounds.number-limits', $agentSession->round) }}">
                @csrf
                @method('PATCH')
                <label for="sale-number-limits">Number Limit · blocked values (00–99)</label>
                <p class="muted">Only the listed values are blocked; this does not cap total sales.</p>
                <textarea id="sale-number-limits" name="numbers" placeholder="00, 17, 28">{{ implode(', ', $numberLimits) }}</textarea>
                <button class="button secondary" type="submit" style="margin-top:7px">Save Number Limit</button>
            </form>
            <div>
                <h3>Hot Numbers</h3>
                <form method="POST" action="{{ route('admin.settings.hot-numbers.store', $agentSession->round) }}">
                    @csrf
                    <label class="sr-only" for="sale-hot-numbers">Add 2D Hot Numbers</label>
                    <input id="sale-hot-numbers" type="text" name="numbers" placeholder="Add values: 00, 17, 28" required>
                    <button class="button secondary" type="submit" style="margin-top:7px">Add Hot Numbers</button>
                </form>
                <div class="hot-number-list" aria-label="Current Hot Numbers">
                    @forelse($hotNumbers as $hotNumber)
                        <span class="hot-number-item">
                            {{ $hotNumber->number }}
                            <form method="POST" action="{{ route('admin.settings.hot-numbers.destroy', [$agentSession->round, $hotNumber]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" aria-label="Remove Hot Number {{ $hotNumber->number }}">×</button>
                            </form>
                        </span>
                    @empty
                        <span class="muted">No Hot Numbers configured.</span>
                    @endforelse
                </div>
            </div>
        </section>
    @endif

    @if($saleMode === '2d' && ! $canEnterSales && $agentSession->round->result?->result_2d !== null && $ownSettlement)
        <section class="sale-panel sale-history" aria-label="2D settlement">
            <div class="sale-panel-title"><span>Your 2D settlement for this Round</span><span>{{ $agentSession->round->result->result_2d }}</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Accepted stake</th><th>Winning stake</th><th>Winnings</th><th>Commission</th><th>Business net</th></tr></thead>
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

    <section class="sale-panel sale-history">
        <div class="sale-panel-title">
            <span>Saved {{ strtoupper($saleMode) }} sales</span>
            <span>Latest 50 for this Agent and Round</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Time</th><th>Input</th><th>Status</th><th>Accepted amount</th><th>Numbers</th></tr></thead>
                <tbody>
                @php $history = $saleMode === '2d' ? $saleInputs : $threeDigitSaleInputs; @endphp
                @forelse($history as $saleInput)
                    <tr>
                        <td>{{ $saleInput->created_at->timezone('Asia/Yangon')->format('H:i:s') }}</td>
                        <td><strong>{{ $saleInput->original_input }}</strong></td>
                        <td><span class="pill {{ $saleInput->status === 'accepted' ? '' : 'off' }}">{{ ucfirst($saleInput->status) }}</span></td>
                        <td>{{ number_format((float) ($saleMode === '2d' ? $saleInput->details->where('status', 'accepted')->where('is_excluded', false)->sum('amount') : $saleInput->details->where('status', 'accepted')->sum('amount')), 2) }}</td>
                        <td><div class="sale-number-chips">
                            @foreach($saleInput->details as $detail)
                                <span class="sale-number-chip {{ isset($detail->is_excluded) && $detail->is_excluded ? 'excluded' : ($detail->status === 'accepted' ? '' : 'rejected') }}">{{ $detail->number }}@if(isset($detail->is_excluded) && $detail->is_excluded) · excluded@elseif($detail->status !== 'accepted') · {{ str_replace('_', ' ', $detail->reject_reason ?? $detail->status) }}@endif</span>
                            @endforeach
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No saved sales for this Agent and Round yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@if($saleMode === '2d' && config('app.desktop_mode'))
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
        const saveButton = document.querySelector('[data-save-sales]');
        const storageKey = workspace.dataset.storageKey;
        const mode = workspace.dataset.saleMode;
        const amountLimitValue = @json($amountLimit);
        const amountLimit = Number(amountLimitValue);
        const draftId = () => window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        if (mode === '2d' && window.Thai2D3DSaleEntryKeyboard) {
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
        let agentSold = new Map(@json($agentSoldItems).map(item => [item.number, Number(item.amount)]));
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
                empty.textContent = 'No unsaved inputs. Enter a sale input to stage it here.';
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
                detail.textContent = `${new Date(draft.createdAt).toLocaleTimeString()}${draft.error ? ` · ${draft.error}` : ' · Not saved'}`;
                label.append(strong, detail);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button secondary';
                remove.textContent = 'Remove';
                remove.setAttribute('aria-label', `Remove draft ${draft.input}`);
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
                cell.textContent = 'No matching accepted numbers yet.';
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
                cell.textContent = 'No accepted sales yet.';
                return;
            }
            rows.forEach(([number, amount]) => {
                const row = body.insertRow();
                row.insertCell().textContent = number;
                row.insertCell().textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        };
        const renderOverLimit = () => {
            const body = document.querySelector('[data-over-limit]');
            if (!body) return;
            const rows = [...agentSold.entries()]
                .filter(([, amount]) => amountLimitValue !== null && amount > amountLimit)
                .map(([number, amount]) => [number, amount - amountLimit])
                .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
            body.replaceChildren();
            if (!rows.length) {
                const row = body.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 2;
                cell.className = 'muted';
                cell.textContent = 'No number exceeds this Agent\'s Amount Limit.';
                return;
            }
            rows.forEach(([number, excess]) => {
                const row = body.insertRow();
                row.insertCell().textContent = number;
                row.insertCell().textContent = excess.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        };
        const addAcceptedDetails = details => {
            for (const detail of details) {
                if (detail.is_excluded) continue;
                if (detail.status !== 'accepted') {
                    rejectedCount++;
                    continue;
                }
                sold.set(detail.number, (sold.get(detail.number) || 0) + Number(detail.amount));
                agentSold.set(detail.number, (agentSold.get(detail.number) || 0) + Number(detail.amount));
                acceptedCount++;
                acceptedAmount += Number(detail.amount);
            }
            document.querySelector('[data-accepted-count]').textContent = acceptedCount.toLocaleString();
            document.querySelector('[data-accepted-amount]').textContent = acceptedAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.querySelector('[data-rejected-count]').textContent = rejectedCount.toLocaleString();
            renderSold();
            renderTopSales();
            renderOverLimit();
        };
        try {
            const storedDrafts = localStorage.getItem(storageKey);
            if (storedDrafts) {
                const parsed = JSON.parse(storedDrafts);
                if (!Array.isArray(parsed) || parsed.some(draft => typeof draft.input !== 'string' || typeof draft.id !== 'string')) {
                    throw new Error('Saved drafts have an invalid format.');
                }
                drafts = parsed;
            }
        } catch (error) {
            showStatus(`${error.message} Keep this page open and copy the drafts before clearing browser storage.`, true);
        }
        renderDrafts();
        renderSold();
        renderTopSales();
        renderOverLimit();

        form.addEventListener('submit', event => {
            event.preventDefault();
            const value = input.value.trim();
            if (!value) return;
            const draft = { id: draftId(), input: value, createdAt: new Date().toISOString() };
            drafts.push(draft);
            try {
                persist();
                input.value = '';
                renderDrafts();
                showStatus(`${value} added to Recent Sales. Press Save (F1) to send it to the server.`);
                input.focus();
            } catch (error) {
                drafts.pop();
                showStatus(`Could not save this draft on the device: ${error.message}. Copy the input before retrying.`, true);
            }
        });
        saveButton.addEventListener('click', async () => {
            if (!drafts.length) {
                showStatus('Recent Sales is empty. Add one or more inputs first.');
                input.focus();
                return;
            }
            saveButton.disabled = true;
            let saved = 0;
            let failed = 0;
            for (const draft of [...drafts]) {
                draft.error = '';
                try {
                    persist();
                    const data = new FormData();
                    data.set('_token', workspace.dataset.csrf);
                    data.set('sale_type', mode);
                    data.set('input', draft.input);
                    data.set('client_uuid', draft.id);
                    const response = await fetch(workspace.dataset.saleAction, {
                        method: 'POST',
                        body: data,
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        draft.error = result.message || Object.values(result.errors || {}).flat().join(' ') || `Server returned ${response.status}.`;
                        failed++;
                        persist();
                        renderDrafts();
                        if ([401, 419, 500, 502, 503, 504].includes(response.status)) break;
                        continue;
                    }
                    addAcceptedDetails(result.details || []);
                    drafts = drafts.filter(item => item.id !== draft.id);
                    saved++;
                    persist();
                    renderDrafts();
                } catch (error) {
                    draft.error = 'Connection failed. This input is still saved on this device.';
                    failed++;
                    try { persist(); } catch (storageError) {
                        showStatus(`Connection failed and the local draft could not be updated: ${storageError.message}. Do not close this page.`, true);
                        break;
                    }
                    renderDrafts();
                    break;
                }
            }
            saveButton.disabled = false;
            showStatus(`${saved} input${saved === 1 ? '' : 's'} saved${failed ? `; ${failed} remain in Recent Sales with errors` : ''}.`, failed > 0);
        });
        document.querySelector('[data-focus-agent]').addEventListener('click', () => agent.focus());
        agent.addEventListener('change', () => {
            const selected = agent.selectedOptions[0];
            const claimUrl = selected?.dataset.claimUrl;
            if (claimUrl) {
                const claimForm = document.querySelector('[data-agent-claim-form]');
                if (!claimForm) {
                    showStatus('This Agent is not currently assigned to your account.', true);
                    return;
                }
                claimForm.action = claimUrl;
                claimForm.requestSubmit();
                return;
            }
            if (selected?.value) window.location.assign(selected.value);
        });
        document.querySelector('[data-sold-search]').addEventListener('input', renderSold);
        window.addEventListener('keydown', event => {
            if (event.key === 'F1') {
                event.preventDefault();
                saveButton.click();
            } else if (event.key === 'F2') {
                event.preventDefault();
                agent.focus();
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
