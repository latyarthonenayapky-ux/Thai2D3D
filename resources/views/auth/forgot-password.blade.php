@extends('layouts.app', ['title' => 'Reset password'])

@section('content')
<main class="main narrow">
    <div class="eyebrow">Account recovery</div>
    <h1 class="page-title" style="margin-top:8px">Reset your password</h1>
    <p class="subtitle">We’ll email a secure password setup link if the account is eligible.</p>
    <section class="card">
        @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            <button class="button full" type="submit">Send reset link</button>
        </form>
        <div class="footer-link"><a href="{{ route('login') }}">Back to sign in</a></div>
    </section>
</main>
@endsection
