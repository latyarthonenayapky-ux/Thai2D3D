@extends('layouts.app', ['title' => __('app.auth.reset_title')])

@section('content')
<main class="main narrow">
    <div class="eyebrow">{{ __('app.auth.recovery_eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.auth.reset_heading') }}</h1>
    <p class="subtitle">{{ __('app.auth.reset_help') }}</p>
    <section class="card">
        @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">{{ __('app.auth.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            <button class="button full" type="submit">{{ __('app.auth.send_link') }}</button>
        </form>
        <div class="footer-link"><a href="{{ route('login') }}">{{ __('app.auth.back_to_sign_in') }}</a></div>
    </section>
</main>
@endsection
