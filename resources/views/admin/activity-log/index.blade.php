@extends('layouts.app', ['title' => __('app.activity.title')])

@section('content')
<main class="main">
    <div class="eyebrow">{{ __('app.settings.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.activity.title') }}</h1>
    <p class="subtitle">{{ __('app.activity.subtitle') }}</p>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif

    <section class="card" style="margin-bottom:20px">
        <form method="GET" action="{{ route('admin.activity-log.index') }}" class="grid" style="align-items:end">
            <div>
                <label for="user_id">{{ __('app.activity.filter_account') }}</label>
                <select id="user_id" name="user_id">
                    <option value="">{{ __('app.activity.filter_all') }}</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('user_id', (string) ($filters['user_id'] ?? '')) === (string) $account->id)>
                            {{ $account->name }} · {{ __('app.roles.'.$account->role) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="action">{{ __('app.activity.filter_action') }}</label>
                <input id="action" name="action" type="text" maxlength="80" value="{{ old('action', $filters['action'] ?? '') }}" placeholder="login, sale_entry, data_backup">
            </div>
            <div><button class="button secondary" type="submit">{{ __('app.activity.apply') }}</button></div>
            <div>
                <a class="button secondary" href="{{ route('admin.activity-log.export', array_filter($filters)) }}">
                    {{ __('app.activity.export') }}
                </a>
            </div>
        </form>
    </section>

    <section class="card">
        @if($entries->isEmpty())
            <p class="muted">{{ __('app.activity.empty') }}</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('app.activity.when') }}</th>
                            <th>{{ __('app.activity.account') }}</th>
                            <th>{{ __('app.activity.action') }}</th>
                            <th>{{ __('app.activity.ip') }}</th>
                            <th>{{ __('app.activity.detail') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($entries as $entry)
                        <tr>
                            <td>{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                {{ $entry->user?->name ?? '—' }}
                                @if($entry->user)
                                    <span class="muted" style="display:block;font-size:12px">{{ __('app.roles.'.$entry->user->role) }}</span>
                                @endif
                            </td>
                            <td><code>{{ $entry->action }}</code></td>
                            <td>{{ $entry->ip ?? '—' }}</td>
                            <td>
                                @if($entry->meta)
                                    <ul style="margin:0;padding-left:18px">
                                        @foreach($entry->meta as $key => $value)
                                            <li>{{ $key }}: {{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="muted">{{ __('app.common.none') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin-top:14px">{{ __('app.activity.page', ['current' => $entries->currentPage(), 'last' => $entries->lastPage()]) }}</p>
            <div class="row" style="margin-top:10px">
                @if($entries->onFirstPage())
                    <span class="button secondary" aria-disabled="true">{{ __('app.activity.previous') }}</span>
                @else
                    <a class="button secondary" href="{{ $entries->previousPageUrl() }}">{{ __('app.activity.previous') }}</a>
                @endif
                @if($entries->hasMorePages())
                    <a class="button" href="{{ $entries->nextPageUrl() }}">{{ __('app.activity.next') }}</a>
                @endif
            </div>
        @endif
    </section>
</main>
@endsection
