@extends('layouts.app', ['title' => 'Sale Entry · 3D'])

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
      data-csrf="{{ csrf_token() }}"
      data-storage-key="thai2d3d-3d-drafts-{{ auth()->id() }}-{{ $draw?->id ?? 'next' }}-{{ $selectedAgent?->id ?? 'none' }}">
    <header class="three-sale-heading">
        <div>
            <h1>Sale Entry · 3D</h1>
            <div class="three-sale-meta">
                @if($draw)
                    <span>{{ $draw->draw_date->format('Y-m-d') }} · Draw closes 15:30</span>
                    <span>{{ ucfirst($draw->status) }}</span>
                @elseif($nextDraw)
                    <span>Next Draw: {{ $nextDraw->draw_date->format('Y-m-d') }} · 15:30</span>
                @endif
                @if($roundWide)
                    <a href="{{ route('three-digit.sales.workspace', ['agent_id' => $selectedAgent?->id, 'view' => 'agent']) }}">Agent Statement</a>
                @elseif($agentStatement)
                    <a href="{{ route('three-digit.sales.workspace') }}">Live of items sold · all Agents</a>
                @endif
                <a href="{{ route('operator.sales.workspace') }}">2D Sale Entry</a>
            </div>
        </div>
    </header>
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="notice" data-three-sale-status role="status" aria-live="polite" hidden></div>

    @if(! $draw || $draw->status !== 'open')
        <section class="three-sale-panel">
            <div class="three-sale-title">3D sales are currently closed</div>
            <div class="three-sale-controls">
                <p>3D Draws are scheduled for the 1st and 16th of each month. Sales open at 00:00 and close at 15:30 on Draw day.</p>
                @if($nextDraw)<p>Next Draw: <strong>{{ $nextDraw->draw_date->format('Y-m-d') }}</strong> · Opens 00:00 · Result 15:30 (Asia/Yangon)</p>@endif
            </div>
        </section>
    @else
        <div class="three-sale-grid">
            <section class="three-sale-panel">
                <div class="three-sale-title">Agent and input</div>
                <div class="three-sale-controls">
                    <label for="three-sale-agent">Agent</label>
                    <select id="three-sale-agent">
                        <option value="">Choose an Agent</option>
                        @foreach($agents as $agent)
                            @php $agentAssignment = $assignments->get($agent->id); @endphp
                            <option value="{{ route('three-digit.sales.workspace', ['agent_id' => $agent->id, 'view' => $agentStatement ? 'agent' : null]) }}"
                                @selected($selectedAgent?->id === $agent->id)
                                @disabled($agentAssignment && (int) $agentAssignment->handler_id !== (int) auth()->id())>
                                {{ $agent->agent_code }} · {{ $agent->agent_name }}@if($agentAssignment && (int) $agentAssignment->handler_id !== (int) auth()->id()) · Assigned to {{ $agentAssignment->handler?->name ?? 'another user' }}@endif
                            </option>
                        @endforeach
                    </select>
                    @if($selectedAgent && ! $assignment)
                        <form method="POST" action="{{ route('three-digit.sales.claim', [$draw, $selectedAgent]) }}">
                            @csrf
                            <button class="button" type="submit">Claim Agent for this Draw</button>
                        </form>
                    @elseif($assignment && (int) $assignment->handler_id !== (int) auth()->id())
                        <p class="error">This Agent is already claimed for this Draw by {{ $assignment->handler?->name }}.</p>
                    @elseif($selectedAgent)
                        <p class="muted">Assigned to you for this Draw.</p>
                    @endif
                    @if($canEnterSales)
                        <form class="three-sale-entry" data-three-draft-form>
                            <div>
                                <label for="three-sale-input">3D input</label>
                                <input id="three-sale-input" maxlength="255" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="123500 · A1000" required>
                            </div>
                            <button class="button secondary" type="submit">Add</button>
                        </form>
                        <div class="three-sale-shortcuts">Enter · Add to Recent Sales &nbsp; F3 · Input &nbsp; F1 · Save</div>
                    @else
                        <p class="muted">Choose an Agent. The first account to claim an Agent owns it for this Draw only.</p>
                    @endif
                </div>
                @if($canEnterSales)
                    <div class="three-sale-title">Recent Sales · <span data-three-draft-count>0</span></div>
                    <div class="three-draft-list" data-three-draft-list><div class="three-empty">Nothing is sent to the server until Save (F1).</div></div>
                    <button class="button three-save" type="button" data-three-save>Save · F1</button>
                @endif
            </section>

            <section class="three-sale-panel three-sale-ledger-panel">
                <div class="three-sale-title">
                    @if($roundWide)
                        Live of items sold · all Agents
                    @elseif($agentStatement && $selectedAgent)
                        Agent Statement · {{ $selectedAgent->agent_code }}
                    @else
                        Live of items sold · Highest to lowest amount
                    @endif
                </div>
                <div class="three-sale-ledger">
                    <table class="three-sold-table">
                        <thead><tr><th>3D number</th><th>Accepted amount</th></tr></thead>
                        <tbody data-three-sold-list>
                        @forelse($soldItems as $item)
                            <tr><td>{{ $item['number'] }}</td><td>{{ number_format($item['amount'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">No accepted numbers for this Draw yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="three-sale-panel three-sale-stats">
                <div class="three-stat"><span class="three-stat-label">Accepted numbers</span><span class="three-stat-value" data-three-accepted-count>{{ number_format($acceptedCount) }}</span></div>
                <div class="three-stat"><span class="three-stat-label">Accepted amount</span><span class="three-stat-value" data-three-accepted-amount>{{ number_format($acceptedAmount, 2) }}</span></div>
                <div class="three-stat"><span class="three-stat-label">Rejected numbers</span><span class="three-stat-value" data-three-rejected-count>{{ number_format($rejectedCount) }}</span></div>
                <div class="three-stat"><span class="three-stat-label">3D number count limit</span><span class="three-stat-value">{{ $draw->number_limit ? number_format($draw->number_limit) : 'Not set' }}</span></div>
            </aside>
        </div>

        @if($canEnterSales)
            <section class="three-sale-panel three-history">
                <div class="three-sale-title">Saved sales · {{ $selectedAgent->agent_code }} · latest 50</div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Time</th><th>Input</th><th>Status</th><th>Accepted amount</th><th>Numbers</th></tr></thead>
                        <tbody>
                        @forelse($saleInputs as $saleInput)
                            <tr>
                                <td>{{ $saleInput->created_at->timezone('Asia/Yangon')->format('H:i:s') }}</td>
                                <td>{{ $saleInput->original_input }}</td>
                                <td>{{ ucfirst($saleInput->status) }}</td>
                                <td>{{ number_format((float) $saleInput->details->where('status', 'accepted')->sum('amount'), 2) }}</td>
                                <td>
                                    @foreach($saleInput->details as $detail)
                                        <span class="three-chip {{ $detail->status === 'accepted' ? '' : 'rejected' }}">{{ $detail->number }}@if($detail->status !== 'accepted') · {{ str_replace('_', ' ', $detail->reject_reason ?? 'rejected') }}@endif</span>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No saved sales yet.</td></tr>
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
                const empty = document.createElement('div'); empty.className = 'three-empty'; empty.textContent = 'No unsaved inputs. Add an input and press Save (F1).'; list.append(empty); return;
            }
            drafts.forEach(draft => {
                const row = document.createElement('div'); row.className = 'three-draft';
                const content = document.createElement('span'); const strong = document.createElement('strong'); strong.textContent = draft.input;
                const note = document.createElement('small'); note.textContent = draft.error || 'Not saved'; content.append(strong, note);
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button secondary'; remove.textContent = 'Remove';
                remove.addEventListener('click', () => { drafts = drafts.filter(item => item.id !== draft.id); persist(); render(); });
                row.append(content, remove); list.append(row);
            });
        };
        const renderSold = () => {
            const body = document.querySelector('[data-three-sold-list]'); body.replaceChildren();
            const rows = [...sold.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
            if (!rows.length) { const row = body.insertRow(); const cell = row.insertCell(); cell.colSpan = 2; cell.textContent = 'No accepted numbers for this Draw yet.'; return; }
            rows.forEach(([number, amount]) => { const row = body.insertRow(); row.insertCell().textContent = number; row.insertCell().textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); });
        };
        try { const stored = localStorage.getItem(storageKey); if (stored) drafts = JSON.parse(stored); }
        catch (error) { displayStatus(`Could not read saved drafts: ${error.message}. Keep this page open and copy the inputs.`, true); }
        render();
        form.addEventListener('submit', event => {
            event.preventDefault(); const value = input.value.trim(); if (!value) return;
            drafts.push({ id: window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`, input: value, createdAt: new Date().toISOString() });
            try { persist(); input.value = ''; render(); input.focus(); }
            catch (error) { drafts.pop(); displayStatus(`Could not save this draft locally: ${error.message}. Copy it before retrying.`, true); }
        });
        select?.addEventListener('change', () => { if (select.value) window.location.assign(select.value); });
        saveButton.addEventListener('click', async () => {
            if (!drafts.length) { displayStatus('Recent Sales is empty. Add an input first.'); input.focus(); return; }
            saveButton.disabled = true; let saved = 0; let failed = 0;
            for (const draft of [...drafts]) {
                try {
                    const body = new FormData(); body.set('_token', workspace.dataset.csrf); body.set('input', draft.input); body.set('client_uuid', draft.id);
                    const response = await fetch(workspace.dataset.saleAction, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                    const result = await response.json();
                    if (!response.ok) {
                        draft.error = result.message || `Server returned ${response.status}.`; failed++; persist(); render();
                        if ([401, 419, 500, 502, 503, 504].includes(response.status)) break;
                        continue;
                    }
                    for (const detail of result.details || []) {
                        if (detail.status === 'accepted') { sold.set(detail.number, (sold.get(detail.number) || 0) + Number(detail.amount)); acceptedCount++; acceptedAmount += Number(detail.amount); }
                        else rejectedCount++;
                    }
                    drafts = drafts.filter(item => item.id !== draft.id); saved++; persist(); render();
                } catch (error) {
                    draft.error = 'Connection failed; this input is still saved on this device.'; failed++; persist(); render(); break;
                }
            }
            document.querySelector('[data-three-accepted-count]').textContent = acceptedCount.toLocaleString();
            document.querySelector('[data-three-accepted-amount]').textContent = acceptedAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.querySelector('[data-three-rejected-count]').textContent = rejectedCount.toLocaleString();
            renderSold(); saveButton.disabled = false;
            displayStatus(`${saved} input${saved === 1 ? '' : 's'} saved${failed ? `; ${failed} remain in Recent Sales with errors` : ''}.`, failed > 0);
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
