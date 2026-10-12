<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/wya-logo.png') }}">
    <title>{{ __('app.owner.title') }}</title>
    <style>
        :root{font-family:ui-sans-serif,system-ui,sans-serif;color:#14211c;background:#f4f7f5}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}
        main{width:min(100%,460px);padding:30px;background:#fff;border:1px solid #d9e3dd;border-radius:14px;box-shadow:0 12px 36px #183a2a12}
        h1{margin:0 0 8px;font-size:25px;letter-spacing:-.04em}p{margin:0 0 22px;color:#5e7168;line-height:1.5}
        label{display:block;margin:16px 0 7px;font-size:13px;font-weight:700}
        input{width:100%;min-height:46px;padding:10px 12px;border:1px solid #cbd8d0;border-radius:7px;font:inherit}
        input:focus{outline:3px solid #087b5933;border-color:#087b59}
        button{width:100%;min-height:46px;margin-top:22px;border:0;border-radius:7px;background:#087b59;color:#fff;font:inherit;font-weight:700;cursor:pointer}
        .error{margin-top:5px;color:#923723;font-size:13px}
        .hint{margin-top:12px;margin-bottom:0;font-size:12px}
        .step{margin:0 0 4px;color:#087b59;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
        .wizard-hidden{display:none}
    </style>
</head>
<body>
<main>
    <img src="{{ asset('images/wya-logo.png') }}" alt="" width="64" height="64" style="width:64px;height:64px;border-radius:14px;object-fit:cover;margin-bottom:14px">
    <h1>{{ __('app.owner.title') }}</h1>
    <p>{{ __('app.owner.intro') }}</p>
    <form method="POST" action="{{ route('desktop.owner-setup.store') }}" id="setup-form">
        @csrf
        <div id="step-1">
            <div class="step">{{ __('app.owner.step1') }}</div>

            <label for="name">{{ __('app.owner.name_label') }}</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
            @error('name')<div class="error">{{ $message }}</div>@enderror

            <label for="email">{{ __('app.owner.email_label') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
            @error('email')<div class="error">{{ $message }}</div>@enderror

            <label for="password">{{ __('app.auth.password') }}</label>
            <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password">
            @error('password')<div class="error">{{ $message }}</div>@enderror

            <label for="password_confirmation">{{ __('app.auth.confirm_password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6" autocomplete="new-password">
            <button type="submit" id="continue-button">{{ __('app.owner.continue') }}</button>
            <p class="hint">{{ __('app.owner.password_hint') }}</p>
        </div>
        <div id="step-2" class="{{ $errors->any() ? '' : 'wizard-hidden' }}">
            <div class="step">{{ __('app.owner.step2') }}</div>

            <label for="install_key">{{ __('app.owner.install_key_label') }}</label>
            <input id="install_key" name="install_key" value="{{ old('install_key') }}" maxlength="64" autocomplete="off" spellcheck="false">
            @error('install_key')<div class="error">{{ $message }}</div>@enderror

            <p class="hint">{{ __('app.owner.install_key_help') }}</p>
            <button type="submit">{{ __('app.owner.create_owner') }}</button>
        </div>
    </form>
    <noscript><style>.wizard-hidden{display:block}</style></noscript>
    <script>
        (function () {
            'use strict';

            var passwordMismatch = @json(__('app.owner.password_mismatch'));
            var form = document.getElementById('setup-form');
            var stepOne = document.getElementById('step-1');
            var stepTwo = document.getElementById('step-2');
            var keyField = document.getElementById('install_key');
            var password = document.getElementById('password');
            var confirmation = document.getElementById('password_confirmation');

            // When the page reloads after a server error both steps are shown;
            // the key field then needs the same required flag the wizard sets.
            if (!stepTwo.classList.contains('wizard-hidden')) {
                keyField.required = true;
            }

            form.addEventListener('submit', function (event) {
                if (!stepTwo.classList.contains('wizard-hidden')) return;

                event.preventDefault();

                if (password.value !== confirmation.value) {
                    confirmation.setCustomValidity(passwordMismatch);
                    confirmation.reportValidity();
                    confirmation.setCustomValidity('');
                    return;
                }

                stepOne.classList.add('wizard-hidden');
                stepTwo.classList.remove('wizard-hidden');
                keyField.required = true;
                keyField.focus();
            });
        })();
    </script>
</main>
</body>
</html>
