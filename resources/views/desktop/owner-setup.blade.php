<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set up Thai2D3D</title>
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
    <h1>Set up Thai2D3D</h1>
    <p>This standalone copy stores its data on this computer. Create the local Owner account to begin.</p>
    <form method="POST" action="{{ route('desktop.owner-setup.store') }}" id="setup-form">
        @csrf
        <div id="step-1">
            <div class="step">Step 1 of 2 · Admin account</div>

            <label for="name">Owner name</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
            @error('name')<div class="error">{{ $message }}</div>@enderror

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
            @error('email')<div class="error">{{ $message }}</div>@enderror

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password">
            @error('password')<div class="error">{{ $message }}</div>@enderror

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6" autocomplete="new-password">
            <button type="submit" id="continue-button">Continue</button>
            <p class="hint">Use at least 6 characters. Keep this password safe; this offline database is separate from any online server.</p>
        </div>
        <div id="step-2" class="{{ $errors->any() ? '' : 'wizard-hidden' }}">
            <div class="step">Step 2 of 2 · Install pass key</div>

            <label for="install_key">Install pass key</label>
            <input id="install_key" name="install_key" value="{{ old('install_key') }}" maxlength="64" autocomplete="off" spellcheck="false">
            @error('install_key')<div class="error">{{ $message }}</div>@enderror

            <p class="hint">Get this key from your Thai2D3D provider by sending them the email address you register with. The key only matches that email.</p>
            <p class="hint">Install pass key ကို မိမိထည့်သွင်းလိုသော Email အား ပေးပို့၍ ရယူပါ။</p>
            <button type="submit">Create Owner account</button>
        </div>
    </form>
    <noscript><style>.wizard-hidden{display:block}</style></noscript>
    <script>
        (function () {
            'use strict';

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
                    confirmation.setCustomValidity('Passwords do not match.');
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
