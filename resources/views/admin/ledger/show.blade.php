@extends('layouts.app', ['title' => __('app.ledger.statement_title', ['agent' => $agent->agent_name])])

@section('content')
<main class="main">
    <div class="eyebrow">{{ __('app.settings.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">
        {{ __('app.ledger.statement_title', ['agent' => $agent->agent_name]) }}
    </h1>
    <p class="subtitle">{{ $agent->agent_code }} · {{ $agent->phone ?: __('app.common.not_set') }}</p>

    <div class="row" style="margin-bottom:16px">
        <a class="button secondary" href="{{ route('admin.ledger.index') }}">{{ __('app.ledger.back_to_ledger') }}</a>
    </div>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="notice" role="alert">{{ $errors->first() }}</div>
    @endif

    @php
        $closing = $statement['totals']['closing'];
    @endphp

    <div class="grid" style="margin-bottom:20px">
        <div class="card">
            <div class="eyebrow">{{ __('app.ledger.opening') }}</div>
            <h2 style="font-size:24px;margin-top:8px">{{ number_format($statement['opening'], 2) }}</h2>
        </div>
        <div class="card">
            <div class="eyebrow">{{ __('app.ledger.period_in') }}</div>
            <h2 style="font-size:24px;margin-top:8px">{{ number_format($statement['totals']['in'], 2) }}</h2>
        </div>
        <div class="card">
            <div class="eyebrow">{{ __('app.ledger.period_out') }}</div>
            <h2 style="font-size:24px;margin-top:8px">{{ number_format($statement['totals']['out'], 2) }}</h2>
        </div>
        <div class="card">
            <div class="eyebrow">{{ __('app.ledger.closing') }}</div>
            <h2 style="font-size:24px;margin-top:8px">{{ number_format($closing, 2) }}</h2>
        </div>
    </div>

    <p class="muted" style="margin-bottom:20px">
        @if($closing > 0)
            {{ __('app.ledger.held', ['amount' => number_format($closing, 2)]) }}
        @elseif($closing < 0)
            {{ __('app.ledger.owed', ['amount' => number_format(abs($closing), 2)]) }}
        @else
            {{ __('app.ledger.settled') }}
        @endif
    </p>

    <section class="card" style="margin-bottom:20px">
        <h2 style="font-size:18px;margin:0 0 14px">{{ __('app.ledger.add_heading') }}</h2>
        <form method="POST" action="{{ route('admin.ledger.store', $agent) }}" class="grid" style="align-items:end">
            @csrf
            <div>
                <label for="type">{{ __('app.ledger.type') }}</label>
                <select id="type" name="type" required>
                    @foreach(\App\Models\AgentLedgerEntry::TYPES as $type)
                        <option value="{{ $type }}" @selected(old('type', 'deposit') === $type)>{{ __('app.ledger.type_'.$type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="amount">{{ __('app.ledger.amount') }}</label>
                <input id="amount" name="amount" type="number" step="0.01" min="0.01" max="999999999.99"
                    value="{{ old('amount') }}" required inputmode="decimal">
            </div>
            <div>
                <label for="occurs_on">{{ __('app.ledger.date') }}</label>
                <input id="occurs_on" name="occurs_on" type="date" value="{{ old('occurs_on', $today) }}" required>
            </div>
            <div>
                <label for="note">{{ __('app.ledger.note') }}</label>
                <input id="note" name="note" type="text" maxlength="255" value="{{ old('note') }}"
                    placeholder="{{ __('app.ledger.note_placeholder') }}">
            </div>
            <div><button class="button" type="submit">{{ __('app.ledger.save') }}</button></div>
        </form>
    </section>

    <section class="card" style="margin-bottom:20px">
        <form method="GET" action="{{ route('admin.ledger.show', $agent) }}" class="grid" style="align-items:end">
            <div>
                <label for="from">{{ __('app.ledger.filter_from') }}</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div>
                <label for="to">{{ __('app.ledger.filter_to') }}</label>
                <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div><button class="button secondary" type="submit">{{ __('app.ledger.filter_apply') }}</button></div>
        </form>
    </section>

    <section class="card">
        @if($statement['rows']->isEmpty())
            <p class="muted">{{ __('app.ledger.empty_entries') }}</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('app.ledger.date') }}</th>
                            <th>{{ __('app.ledger.type') }}</th>
                            <th>{{ __('app.ledger.amount') }}</th>
                            <th>{{ __('app.ledger.running_balance') }}</th>
                            <th>{{ __('app.ledger.note') }}</th>
                            <th>{{ __('app.ledger.recorded_by') }}</th>
                            <th>{{ __('app.common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($statement['rows']->reverse() as $entry)
                        <tr>
                            <td>{{ $entry->occurs_on->format('Y-m-d') }}</td>
                            <td>{{ __('app.ledger.type_'.$entry->type) }}</td>
                            <td>
                                <strong>{{ $entry->isMoneyIn() ? '+' : '-' }}{{ number_format((float) $entry->amount, 2) }}</strong>
                            </td>
                            <td>{{ number_format($entry->running_balance, 2) }}</td>
                            <td>{{ $entry->note ?: '—' }}</td>
                            <td>{{ $entry->recordedBy?->name ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.ledger.destroy', [$agent, $entry]) }}"
                                    onsubmit="return confirm('{{ __('app.ledger.delete_confirm') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button danger" type="submit">{{ __('app.ledger.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>
@endsection
