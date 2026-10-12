@extends('layouts.app', ['title' => __('app.sale.entry').' · 3D'])

@section('content')
<style>
    .three-sale{max-width:1500px;margin:16px auto;padding:0 20px}
    .three-sale-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:12px}
    .three-sale-heading h1{margin:0;font-size:23px}
    .three-sale-meta{display:flex;gap:12px;color:var(--muted);font-size:13px;margin-top:6px}
    .three-sale-grid{display:grid;grid-template-columns:minmax(245px,.75fr) minmax(400px,1.5fr) minmax(185px,.6fr);gap:12px;align-items:start}
    .three-sale-panel{min-width:0;background:var(--surface);border:1px solid var(--border)}
    .three-sale-title{padding:10px 12px;background:var(--surface-soft);border-bottom:1px solid var(--border);font-size:13px;font-weight:800}
    .three-sale-controls{padding:12px}
    .three-sale-controls label{margin:0 0 6px;font-size:12px}
    .three-sale-controls input,.three-sale-controls select{padding:9px 10px;border-radius:4px}
    .three-sale-controls button{margin-top:12px}
    .three-sale-entry{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;margin-top:16px}
    .three-sale-entry button{margin:0;min-height:40px}
    .three-sale-shortcuts{margin-top:8px;color:var(--muted);font-size:11px}
    .three-draft-list{max-height:240px;overflow:auto}
    .three-draft{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border-bottom:1px dashed var(--gridline);font-size:13px}
    .three-draft small{display:block;color:var(--muted);margin-top:3px}
    .three-draft button{padding:4px 8px;font-size:11px}
    .three-empty{padding:12px;color:var(--muted);font-size:12px}
    .three-sold-table{border-collapse:collapse;width:100%;font-size:13px}
    .three-sold-table th,.three-sold-table td{padding:7px 10px;border:1px solid var(--gridline);font-variant-numeric:tabular-nums}
    .three-sold-table th{background:var(--surface-soft)}
    .three-sold-table td:last-child{text-align:right}
    .three-sale-ledger{max-height:min(65vh,600px);overflow:auto}
    .three-stat{padding:12px;border-bottom:1px solid var(--gridline)}
    .three-stat:last-child{border-bottom:0}
    .three-stat-label{display:block;color:var(--muted);font-size:10px;font-weight:800;text-transform:uppercase}
    .three-stat-value{display:block;margin-top:5px;font-size:21px;font-weight:800;font-variant-numeric:tabular-nums}
    .three-save{margin:12px;width:calc(100% - 24px);min-height:44px}
    .three-history{margin-top:14px}
    .three-history th,.three-history td{padding:8px;font-size:12px}
    .three-chip{display:inline-block;margin:2px;padding:2px 5px;border:1px solid var(--gridline)}
    .three-chip.rejected{color:var(--danger-text);background:var(--danger-bg)}
    @media(max-width:980px){.three-sale-grid{grid-template-columns:minmax(230px,.8fr) minmax(350px,1.2fr)}.three-sale-stats{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,1fr)}.three-stat{border-right:1px solid var(--gridline);border-bottom:0}}
    @media(max-width:680px){.three-sale{margin:12px auto;padding:0 10px}.three-sale-grid{grid-template-columns:1fr}.three-sale-ledger-panel{grid-row:2}.three-sale-stats{grid-column:auto}.three-sale-ledger{max-height:440px}.three-sale-heading{flex-direction:column}}
</style>
<main class="three-sale"
      data-three-draft-workspace
      data-sale-action="{{ $draw && $selectedAgent ? route('three-digit.sales.store', [$draw, $selectedAgent]) : '' }}"
      data-batch-action="{{ $draw && $selectedAgent ? route('three-digit.sales.store-batch', [$draw, $selectedAgent]) : '' }}"
      data-csrf="{{ csrf_token() }}"
      data-storage-key="thai2d3d-3d-drafts-{{ auth()->id() }}-{{ $draw?->id ?? 'next' }}-{{ $selectedAgent?->id ?? 'none' }}">
    <header class="three-sale-heading">
        <div>
            <h1>{{ __('app.sale.entry') }} · 3D</h1>
            <div class="three-sale-meta">
                @if($draw)
                    <span>{{ __('app.three.draw_closes', ['date' => $draw->draw_date->format('Y-m-d'), 'time' => '15:30']) }}</span>
                    <span>{{ __('app.common.status_'.$draw->status) }}</span>
                @elseif($nextDraw)
                    <span>{{ __('app.three.next_draw_link', ['date' => $nextDraw->draw_date->format('Y-m-d'), 'time' => '15:30']) }}</span>
                @endif
                @if($roundWide)
                    <a href="{{ route('three-digit.sales.workspace', ['agent_id' => $selectedAgent?->id, 'view' => 'agent']) }}">{{ __('app.sale.statement') }}</a>
                @elseif($agentStatement)
                    <a href="{{ route('three-digit.sales.workspace') }}">{{ __('app.sale.live_all') }}</a>
                @endif
                <a href="{{ route('operator.sales.workspace') }}">2D {{ __('app.sale.entry') }}</a>
            </div>
        </div>
    </header>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="notice" data-three-sale-status role="status" aria-live="polite" hidden></div>

    @if(! $draw || $draw->status !== 'open')
        <section class="three-sale-panel">
            <div class="three-sale-title">{{ __('app.three.closed_title') }}</div>
            <div class="three-sale-controls">
                <p>{{ __('app.three.closed_help') }}</p>
                @if($nextDraw)<p>{{ __('app.three.next_draw_prefix') }} <strong>{{ $nextDraw->draw_date->format('Y-m-d') }}</strong> {{ __('app.three.next_draw_suffix') }}</p>@endif
            </div>
        </section>
    @else
        <div class="three-sale-grid">
            <section class="three-sale-panel">
                <div class="three-sale-title">{{ __('app.three.agent_and_input') }}</div>
                <div class="three-sale-controls">
                    <label for="three-sale-agent">{{ __('app.sale.agent') }}</label>
                    <select id="three-sale-agent">
                        <option value="">{{ __('app.three.choose_agent') }}</option>
                        @foreach($agents as $agent)
                            @php $agentAssignment = $assignments->get($agent->id); @endphp
                            <option value="{{ route('three-digit.sales.workspace', ['agent_id' => $agent->id, 'view' => $agentStatement ? 'agent' : null]) }}"
                                @selected($selectedAgent?->id === $agent->id)
                                @disabled($agentAssignment && (int) $agentAssignment->handler_id !== (int) auth()->id())>
                                {{ $agent->agent_code }} · {{ $agent->agent_name }}@if($agentAssignment && (int) $agentAssignment->handler_id !== (int) auth()->id()) {{ __('app.three.assigned_to', ['name' => $agentAssignment->handler?->name ?? __('app.three.another_user')]) }}@endif
                            </option>
                        @endforeach
                    </select>
                    @if($selectedAgent && ! $assignment)
                        <form method="POST" action="{{ route('three-digit.sales.claim', [$draw, $selectedAgent]) }}">
                            @csrf
                            <button class="button" type="submit">{{ __('app.three.claim_agent') }}</button>
                        </form>
                    @elseif($assignment && (int) $assignment->handler_id !== (int) auth()->id())
                        <p class="error">{{ __('app.three.claimed_by', ['name' => $assignment->handler?->name]) }}</p>
                    @elseif($selectedAgent)
                        <p class="muted">{{ __('app.three.assigned_to_you') }}</p>
                    @endif
                    @if($canEnterSales)
                        <form class="three-sale-entry" data-three-draft-form>
                            <div>
                                <label for="three-sale-input">{{ __('app.three.input_label') }}</label>
                                <input id="three-sale-input" maxlength="255" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="{{ __('app.three.input_placeholder') }}" required>
                            </div>
                            <button class="button secondary" type="submit">{{ __('app.sale.add') }}</button>
                        </form>
                        <div class="three-sale-shortcuts">Enter · {{ __('app.sale.shortcut_add') }} &nbsp; F3 · {{ __('app.sale.shortcut_input') }} &nbsp; F1 · {{ __('app.three.shortcut_save') }}</div>
                    @else
                        <p class="muted">{{ __('app.three.claim_help') }}</p>
                    @endif
                </div>
                @if($canEnterSales)
                    <div class="three-sale-title">{{ __('app.sale.recent_sales') }} · <span data-three-draft-count>0</span></div>
                    <div class="three-draft-list" data-three-draft-list><div class="three-empty">{{ __('app.three.draft_empty') }}</div></div>
                    <button class="button three-save" type="button" data-three-save>{{ __('app.sale.save_button') }}</button>
                @endif
            </section>

            <section class="three-sale-panel three-sale-ledger-panel">
                <div class="three-sale-title">
                    @if($roundWide)
                        {{ __('app.sale.live_all') }}
                    @elseif($agentStatement && $selectedAgent)
                        {{ __('app.sale.statement_for', ['code' => $selectedAgent->agent_code]) }}
                    @else
                        {{ __('app.three.live_highest') }}
                    @endif
                </div>
                <div class="three-sale-ledger">
                    <table class="three-sold-table">
                        <thead><tr><th>{{ __('app.sale.number_3d') }}</th><th>{{ __('app.sale.accepted_amount') }}</th></tr></thead>
                        <tbody data-three-sold-list>
                        @forelse($soldItems as $item)
                            <tr><td>{{ $item['number'] }}</td><td>{{ number_format($item['amount'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">{{ __('app.three.no_accepted_draw') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="three-sale-panel three-sale-stats">
                <div class="three-stat"><span class="three-stat-label">{{ __('app.sale.accepted_numbers') }}</span><span class="three-stat-value" data-three-accepted-count>{{ number_format($acceptedCount) }}</span></div>
                <div class="three-stat"><span class="three-stat-label">{{ __('app.sale.accepted_amount') }}</span><span class="three-stat-value" data-three-accepted-amount>{{ number_format($acceptedAmount, 2) }}</span></div>
                <div class="three-stat"><span class="three-stat-label">{{ __('app.sale.rejected_numbers') }}</span><span class="three-stat-value" data-three-rejected-count>{{ number_format($rejectedCount) }}</span></div>
                <div class="three-stat"><span class="three-stat-label">{{ __('app.sale.limit_3d') }}</span><span class="three-stat-value">{{ $draw->number_limit ? number_format($draw->number_limit) : __('app.sale.not_set') }}</span></div>
            </aside>
        </div>

        @if($canEnterSales)
            <section class="three-sale-panel three-history">
                <div class="three-sale-title">{{ __('app.three.saved_sales', ['code' => $selectedAgent->agent_code]) }}</div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>{{ __('app.sale.time') }}</th><th>{{ __('app.sale.input') }}</th><th>{{ __('app.sale.status') }}</th><th>{{ __('app.sale.accepted_amount') }}</th><th>{{ __('app.sale.numbers') }}</th></tr></thead>
                        <tbody>
                        @forelse($saleInputs as $saleInput)
                            <tr>
                                <td>{{ $saleInput->created_at->timezone('Asia/Yangon')->format('H:i:s') }}</td>
                                <td>{{ $saleInput->original_input }}</td>
                                <td>{{ __('app.sale.status_'.$saleInput->status) }}</td>
                                <td>{{ number_format((float) $saleInput->details->where('status', 'accepted')->sum('amount'), 2) }}</td>
                                <td>
                                    @foreach($saleInput->details as $detail)
                                        @php $reasonKey = 'app.sale.reason_'.strtolower((string) ($detail->reject_reason ?? 'rejected')); @endphp
                                        <span class="three-chip {{ $detail->status === 'accepted' ? '' : 'rejected' }}">{{ $detail->number }}@if($detail->status !== 'accepted') · {{ Illuminate\Support\Facades\Lang::has($reasonKey) ? __($reasonKey) : str_replace('_', ' ', (string) ($detail->reject_reason ?? 'rejected')) }}@endif</span>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">{{ __('app.three.no_saved_sales') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</main>
@if($canEnterSales)
<script>
    (() => {
        const workspace = document.querySelector('[data-three-draft-workspace]');
        const form = document.querySelector('[data-three-draft-form]');
        const input = document.getElementById('three-sale-input');
        const select = document.getElementById('three-sale-agent');
        const list = document.querySelector('[data-three-draft-list]');
        const count = document.querySelector('[data-three-draft-count]');
        const status = document.querySelector('[data-three-sale-status]');
        const saveButton = document.querySelector('[data-three-save]');
        const storageKey = workspace.dataset.storageKey;
        const t = @json(__('app.three.js'));
        const fmt = (text, vars = {}) => text.replace(/:(\w+)/g, (token, key) =>
            Object.prototype.hasOwnProperty.call(vars, key) ? String(vars[key]) : token);
        let drafts = [];
        let sold = new Map(@json($soldItems).map(item => [item.number, Number(item.amount)]));
        let acceptedCount = Number(document.querySelector('[data-three-accepted-count]').textContent.replaceAll(',', ''));
        let acceptedAmount = Number(document.querySelector('[data-three-accepted-amount]').textContent.replaceAll(',', ''));
        let rejectedCount = Number(document.querySelector('[data-three-rejected-count]').textContent.replaceAll(',', ''));
        const displayStatus = (message, error = false) => {
            status.hidden = false; status.classList.toggle('error', error); status.classList.toggle('notice', !error); status.textContent = message;
        };
        const persist = () => localStorage.setItem(storageKey, JSON.stringify(drafts));
        const render = () => {
            count.textContent = String(drafts.length);
            list.replaceChildren();
            if (!drafts.length) {
                const empty = document.createElement('div'); empty.className = 'three-empty'; empty.textContent = t.draft_empty; list.append(empty); return;
            }
            drafts.forEach(draft => {
                const row = document.createElement('div'); row.className = 'three-draft';
                const content = document.createElement('span'); const strong = document.createElement('strong'); strong.textContent = draft.input;
                const note = document.createElement('small'); note.textContent = draft.error || t.not_saved; content.append(strong, note);
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button secondary'; remove.textContent = t.remove;
                remove.addEventListener('click', () => { drafts = drafts.filter(item => item.id !== draft.id); persist(); render(); });
                row.append(content, remove); list.append(row);
            });
        };
        const renderSold = () => {
            const body = document.querySelector('[data-three-sold-list]'); body.replaceChildren();
            const rows = [...sold.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
            if (!rows.length) { const row = body.insertRow(); const cell = row.insertCell(); cell.colSpan = 2; cell.textContent = t.no_accepted; return; }
            rows.forEach(([number, amount]) => { const row = body.insertRow(); row.insertCell().textContent = number; row.insertCell().textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); });
        };
        try { const stored = localStorage.getItem(storageKey); if (stored) drafts = JSON.parse(stored); }
        catch (error) { displayStatus(fmt(t.drafts_unreadable, {error: error.message}), true); }
        render();
        const stageInputs = values => {
            const staged = [];
            for (const raw of values) {
                const value = raw.trim();
                if (!value) continue;
                staged.push({ id: window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`, input: value, createdAt: new Date().toISOString() });
            }
            if (!staged.length) return false;
            drafts.push(...staged);
            try { persist(); render(); input.focus(); return true; }
            catch (error) {
                drafts = drafts.filter(draft => !staged.includes(draft));
                displayStatus(fmt(t.draft_save_failed, {error: error.message}), true);
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
        select?.addEventListener('change', () => { if (select.value) window.location.assign(select.value); });
        saveButton.addEventListener('click', async () => {
            if (!drafts.length) { displayStatus(t.empty_queue); input.focus(); return; }
            saveButton.disabled = true; let saved = 0; let failed = 0; let fatal = false;
            const pending = [...drafts];
            const batchSize = 50;
            for (let index = 0; index < pending.length; index += batchSize) {
                const chunk = pending.slice(index, index + batchSize);
                try {
                    const response = await fetch(workspace.dataset.batchAction, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': workspace.dataset.csrf },
                        body: JSON.stringify({ inputs: chunk.map(draft => ({ input: draft.input, client_uuid: draft.id })) }),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = result.message || fmt(t.server_status, {status: response.status});
                        for (const draft of chunk) { draft.error = message; failed++; }
                        persist(); render();
                        if ([401, 419, 500, 502, 503, 504].includes(response.status)) break;
                        continue;
                    }
                    for (const draft of chunk) {
                        const item = (result.results || []).find(entry => entry.client_uuid === draft.id);
                        if (!item) { draft.error = t.no_response; failed++; continue; }
                        if (item.status === 'saved') {
                            for (const detail of item.details || []) {
                                if (detail.status === 'accepted') { sold.set(detail.number, (sold.get(detail.number) || 0) + Number(detail.amount)); acceptedCount++; acceptedAmount += Number(detail.amount); }
                                else rejectedCount++;
                            }
                            drafts = drafts.filter(row => row.id !== draft.id); saved++;
                        } else {
                            draft.error = item.message || t.server_rejected; failed++;
                        }
                    }
                    persist(); render();
                } catch (error) {
                    for (const draft of chunk) { draft.error = t.connection_failed; failed++; }
                    try { persist(); } catch (storageError) { displayStatus(fmt(t.connection_storage_failed, {error: storageError.message}), true); fatal = true; break; }
                    render(); break;
                }
            }
            document.querySelector('[data-three-accepted-count]').textContent = acceptedCount.toLocaleString();
            document.querySelector('[data-three-accepted-amount]').textContent = acceptedAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.querySelector('[data-three-rejected-count]').textContent = rejectedCount.toLocaleString();
            renderSold(); saveButton.disabled = false;
            if (!fatal) displayStatus(fmt(saved === 1 ? t.saved_single : t.saved_plural, {
                count: saved,
                extra: failed ? fmt(t.saved_failed, {count: failed}) : '',
            }), failed > 0);
        });
        window.addEventListener('keydown', event => {
            if (event.key === 'F1') { event.preventDefault(); saveButton.click(); }
            else if (event.key === 'F2') { event.preventDefault(); select?.focus(); }
            else if (event.key === 'F3') { event.preventDefault(); input.focus(); }
        });
    })();
</script>
@endif
@endsection
