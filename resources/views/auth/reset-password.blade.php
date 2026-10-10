@extends('layouts.app', ['title' => 'Choose a new password'])

@section('content')
<main class="main narrow">
    <div class="eyebrow">Account recovery</div>
    <h1 class="page-title" style="margin-top:8px">Choose a new password</h1>
    <p class="subtitle">Use at least 12 characters for your new password.</p>
    <section class="card">
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            <label for="password">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
            <button class="button full" type="submit">Save password</button>
        </form>
    </section>
</main>
@endsection
