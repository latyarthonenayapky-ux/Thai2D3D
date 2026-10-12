@extends('layouts.app', ['title' => __('app.auth.new_password_title')])

@section('content')
<main class="main narrow">
    <div class="eyebrow">{{ __('app.auth.recovery_eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.auth.new_password_title') }}</h1>
    <p class="subtitle">{{ __('app.auth.new_password_help') }}</p>
    <section class="card">
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">{{ __('app.auth.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            <label for="password">{{ __('app.auth.new_password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="6" required>
            <label for="password_confirmation">{{ __('app.auth.confirm_password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="6" required>
            <button class="button full" type="submit">{{ __('app.auth.save_password') }}</button>
        </form>
    </section>
</main>
@endsection
