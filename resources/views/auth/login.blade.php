@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
<main class="main narrow">
    <div class="eyebrow">Secure workspace</div>
    <h1 class="page-title" style="margin-top:8px">Sign in to Thai2D3D</h1>
    <p class="subtitle">Use your administrator- or operator-issued account.</p>
    <section class="card">
        @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                <input class="checkbox" type="checkbox" name="remember" value="1"> Keep me signed in
            </label>
            <button class="button full" type="submit">Sign in</button>
        </form>
        <div class="footer-link"><a href="{{ route('password.request') }}">Forgot your password?</a></div>
        <p class="muted">Accounts are issued by an administrator. Public registration is disabled.</p>
    </section>
</main>
@endsection
