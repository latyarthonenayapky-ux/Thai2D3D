<!doctype html>
<html lang="{{ request()->user()?->locale() ?? app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/wya-logo.png') }}">
    <title>{{ __('app.licence.title') }}</title>
    <style>
        :root{font-family:ui-sans-serif,system-ui,sans-serif;color:#14211c;background:#f4f7f5}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:start center;padding:24px}
        main{width:min(100%,640px);padding:30px;background:#fff;border:1px solid #d9e3dd;border-radius:14px;box-shadow:0 12px 36px #183a2a12}
        h1{margin:0 0 8px;font-size:25px;letter-spacing:-.04em}h2{margin:26px 0 10px;font-size:16px}p{margin:0 0 14px;color:#5e7168;line-height:1.55}
        label{display:block;margin:16px 0 7px;font-size:13px;font-weight:700}
        textarea{width:100%;min-height:120px;padding:10px 12px;border:1px solid #cbd8d0;border-radius:7px;font:inherit;font-size:12px;word-break:break-all}
        textarea:focus{outline:3px solid #087b5933;border-color:#087b59}
        button{width:100%;min-height:46px;margin-top:18px;border:0;border-radius:7px;background:#087b59;color:#fff;font:inherit;font-weight:700;cursor:pointer}
        a{color:#087b59}
        .brand{display:flex;align-items:center;gap:10px;font-weight:800;margin-bottom:18px}
        .brand img{width:44px;height:44px;border-radius:10px;object-fit:cover}
        .state{display:inline-block;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
        .state.valid{background:#eaf7ef;color:#17633d}.state.grace{background:#fff6e5;color:#8a5a00}
        .state.locked{background:#fff0ed;color:#923723}
        .notice,.error{border-radius:7px;padding:12px 14px;margin:14px 0;font-size:14px}
        .notice{background:#eaf7ef;color:#17633d}.error{background:#fff0ed;color:#923723}
        dl{display:grid;grid-template-columns:auto 1fr;gap:6px 16px;margin:14px 0 0;font-size:14px}
        dt{color:#5e7168;font-size:12px;text-transform:uppercase;letter-spacing:.06em}dd{margin:0;font-weight:600}
        table{border-collapse:collapse;width:100%;font-size:13px;margin-top:10px}
        th,td{text-align:left;padding:8px 10px;border:1px solid #e4ebe6}th{background:#edf4f0;color:#5e7168;font-size:11px;text-transform:uppercase}
        .muted{color:#5e7168;font-size:12px}
        .actions{display:flex;gap:12px;align-items:center;margin-top:22px;flex-wrap:wrap}
        .actions a{min-height:40px;display:inline-flex;align-items:center;padding:0 14px;border:1px solid #cbd8d0;border-radius:7px;font-size:13px;font-weight:700;text-decoration:none;background:#edf4f0;color:#285343}
    </style>
</head>
<body>
<main>
    <div class="brand"><img src="{{ asset('images/wya-logo.png') }}" alt="" width="44" height="44">Winning Yields Always</div>

    @php
        $lockedStates = [\App\Services\Licence\Licence::STATE_EXPIRED, \App\Services\Licence\Licence::STATE_CLOCK_ROLLBACK, \App\Services\Licence\Licence::STATE_INVALID, \App\Services\Licence\Licence::STATE_MISSING];
        $isLocked = in_array($status->state, $lockedStates, true);
    @endphp

    <h1>{{ __('app.licence.title') }}</h1>
    <p>{{ __('app.licence.intro') }}</p>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if (session('licence_locked'))
        <div class="error">{{ __('app.licence.locked') }}</div>
    @endif

    <p>
        <span class="state {{ $status->state === \App\Services\Licence\Licence::STATE_VALID ? 'valid' : ($status->state === \App\Services\Licence\Licence::STATE_GRACE ? 'grace' : 'locked') }}">
            {{ __('app.licence.state_'.$status->state) }}
        </span>
    </p>

    @if ($isLocked)
        <div class="error">{{ __('app.licence.'.$status->state, ['date' => $status->expiresOn() ?? '—']) }}</div>
        <p>{{ __('app.licence.readonly_help') }}</p>
    @elseif ($status->state === \App\Services\Licence\Licence::STATE_GRACE)
        <div class="notice">{{ __('app.licence.grace_note', ['date' => $status->expiresOn() ?? '—', 'grace' => $graceEndsOn]) }}</div>
    @endif

    <dl>
        <dt>{{ __('app.licence.plan') }}</dt><dd>{{ $status->licence?->plan ?? '—' }}</dd>
        <dt>{{ __('app.licence.licence_id') }}</dt><dd>{{ $status->licence?->licence_id ?? '—' }}</dd>
        <dt>{{ __('app.licence.email') }}</dt><dd>{{ $status->licence?->email ?? '—' }}</dd>
        <dt>{{ __('app.licence.issued') }}</dt><dd>{{ $status->licence?->issued_at?->toDateString() ?? '—' }}</dd>
        <dt>{{ __('app.licence.expires') }}</dt><dd>{{ $status->expiresOn() ?? '—' }}</dd>
        <dt>{{ __('app.licence.days_left') }}</dt><dd>{{ $status->daysRemaining() ?? '—' }}</dd>
        @if ($status->state === \App\Services\Licence\Licence::STATE_GRACE)
            <dt>{{ __('app.licence.grace_until') }}</dt><dd>{{ $graceEndsOn }}</dd>
        @endif
        <dt>{{ __('app.licence.seats') }}</dt><dd>{{ __('app.licence.seats_value', ['admins' => $status->licence?->max_admins ?? 0, 'agents' => $status->licence?->max_agents ?? 0]) }}</dd>
    </dl>

    @if ($canManageLicence)
        <h2>{{ __('app.licence.renew_title') }}</h2>
        <p>{{ __('app.licence.renew_help') }}</p>
        <form method="POST" action="{{ route('desktop.licence.store') }}">
            @csrf
            <label for="licence_string">{{ __('app.licence.renew_label') }}</label>
            <textarea id="licence_string" name="licence_string" required maxlength="8192" spellcheck="false"
                >{{ old('licence_string') }}</textarea>
            @error('licence_string')<div class="error">{{ $message }}</div>@enderror
            <button type="submit">{{ __('app.licence.renew_button') }}</button>
        </form>
    @else
        <p class="muted">{{ __('app.licence.owner_only') }}</p>
    @endif

    @if ($history !== [])
        <h2>{{ __('app.licence.history_title') }}</h2>
        <p class="muted">{{ __('app.licence.history_help') }}</p>
        <table>
            <thead>
                <tr>
                    <th>{{ __('app.licence.licence_id') }}</th>
                    <th>{{ __('app.licence.plan') }}</th>
                    <th>{{ __('app.licence.expires') }}</th>
                    <th>{{ __('app.licence.state') }}</th>
                    <th>{{ __('app.licence.installed') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history as $row)
                    <tr>
                        <td>{{ $row['licence']->licence_id }}@if ($row['active']) ★ @endif</td>
                        <td>{{ $row['licence']->plan }}</td>
                        <td>{{ $row['licence']->expires_at->toDateString() }}</td>
                        <td>{{ __('app.licence.state_'.$row['state']) }}</td>
                        <td>{{ $row['licence']->installed_at?->toDateString() ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="actions">
        @auth
            <a href="{{ route('dashboard') }}">{{ __('app.licence.back') }}</a>
            @if (auth()->user()->isAdmin() || auth()->user()->isOwner())
                <a href="{{ route('admin.data-backup.index') }}">{{ __('app.licence.backup') }}</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" style="width:auto;min-height:40px;padding:0 14px;margin:0;background:#edf4f0;color:#285343">{{ __('app.nav.log_out') }}</button>
            </form>
        @else
            <a href="{{ route('login') }}">{{ __('app.licence.go_login') }}</a>
        @endauth
    </div>
</main>
</body>
</html>
