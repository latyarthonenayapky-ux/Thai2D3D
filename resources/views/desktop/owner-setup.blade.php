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
        .hint{margin-top:12px;font-size:12px}
    </style>
</head>
<body>
<main>
    <h1>Set up Thai2D3D</h1>
    <p>This standalone copy stores its data on this computer. Create the local Owner account to begin.</p>
    <form method="POST" action="{{ route('desktop.owner-setup.store') }}">
        @csrf
        <label for="name">Owner name</label>
        <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
        @error('name')<div class="error">{{ $message }}</div>@enderror

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
        @error('email')<div class="error">{{ $message }}</div>@enderror

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password">
        <button type="submit">Create Owner account</button>
        <p class="hint">Use at least 12 characters. Keep this password safe; this offline database is separate from any online server.</p>
    </form>
</main>
</body>
</html>
