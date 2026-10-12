@extends('layouts.app', ['title' => __('app.auth.sign_in')])

@section('content')
<main class="main narrow">
    <div class="eyebrow">{{ __('app.auth.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.auth.heading') }}</h1>
    <p class="subtitle">{{ __('app.auth.subtitle') }}</p>
    <section class="card">
        @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email">{{ __('app.auth.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            <label for="password">{{ __('app.auth.password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                <input class="checkbox" type="checkbox" name="remember" value="1"> {{ __('app.auth.remember') }}
            </label>
            <button class="button full" type="submit">{{ __('app.auth.sign_in') }}</button>
        </form>
        <div class="footer-link"><a href="{{ route('password.request') }}">{{ __('app.auth.forgot') }}</a></div>
        <p class="muted">{{ __('app.auth.accounts_note') }}</p>
    </section>
</main>
@endsection
