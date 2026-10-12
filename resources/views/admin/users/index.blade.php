@extends('layouts.app', ['title' => __('app.users.title')])

@section('content')
<main class="main">
    <div class="between">
        <div>
            <div class="eyebrow">{{ __('app.settings.eyebrow') }}</div>
            <h1 class="page-title" style="margin-top:8px">{{ __('app.users.heading') }}</h1>
            <p class="subtitle">{{ __('app.users.subtitle') }}</p>
        </div>
        <a class="button secondary" href="{{ route('dashboard') }}">{{ __('app.nav.dashboard') }}</a>
    </div>

    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="card" style="margin-bottom:20px">
        <h2>{{ __('app.users.create_account') }}</h2>
        <p class="muted">
            @if(auth()->user()->isOwner())
                {!! __('app.users.owner_help') !!}
            @else
                {{ __('app.users.operator_help') }}
            @endif
            {{ __('app.users.password_help') }}
        </p>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="name">{{ __('app.users.name') }}</label>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
                </div>
                <div>
                    <label for="email">{{ __('app.auth.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                </div>
                @if(auth()->user()->isOwner())
                    <div>
                        <label for="role">{{ __('app.users.role') }}</label>
                        <select id="role" name="role" required>
                            <option value="admin" selected>{{ __('app.roles.admin') }}</option>
                            <option value="temp_admin">{{ __('app.users.temp_admin_option') }}</option>
                        </select>
                    </div>
                @else
                    <input type="hidden" name="role" value="operator">
                @endif
                <div>
                    <label for="password">{{ __('app.auth.password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required minlength="6">
                    <span class="muted" style="font-size:12px">{{ __('app.users.password_min') }}</span>
                </div>
                <div>
                    <label for="password_confirmation">{{ __('app.auth.confirm_password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="6">
                </div>
            </div>
            <button class="button" style="margin-top:18px" type="submit">{{ __('app.users.submit') }}</button>
        </form>
    </section>

    <section class="card">
        <h2>{{ __('app.users.accounts') }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('app.users.col_name') }}</th><th>{{ __('app.users.col_email') }}</th><th>{{ __('app.users.col_role') }}</th><th>{{ __('app.common.status') }}</th><th>{{ __('app.common.action') }}</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            {{ $user->isTempAdmin() ? __('app.users.temp_admin') : __('app.roles.'.$user->role) }}
                            @if($user->expires_at)
                                <small class="muted" style="display:block">{{ $user->isExpired() ? __('app.users.expired') : __('app.users.expires') }} {{ $user->expires_at->format('Y-m-d H:i') }}</small>
                            @endif
                        </td>
                        <td><span class="pill {{ $user->isExpired() || ! $user->status ? 'off' : '' }}">{{ $user->isExpired() ? __('app.users.expired') : ($user->status ? __('app.users.active') : __('app.users.inactive')) }}</span></td>
                        <td>
                            @if(!$user->isOwner() && ($user->isOperator() || auth()->user()->isOwner()))
                                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="inline-form">
                                    @csrf
                                    @method('PATCH')
                                    <button class="button {{ $user->status ? 'danger' : 'secondary' }}" type="submit">
                                        {{ $user->status ? __('app.users.deactivate') : __('app.users.activate') }}
                                    </button>
                                </form>
                            @else
                                <span class="muted">{{ $user->isOwner() ? __('app.users.owner_protected') : __('app.users.owner_only') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('app.users.no_users') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px">{{ $users->links() }}</div>
    </section>
</main>
@endsection
