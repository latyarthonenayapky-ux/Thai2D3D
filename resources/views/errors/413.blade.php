<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@lang('app.errors.upload_too_large_title')</title>
    <style>
        body { margin: 0; padding: 48px 20px; background: #f4f5f7; color: #1b1b18;
               font-family: ui-sans-serif, system-ui, "Segoe UI", "Noto Sans Myanmar", sans-serif; }
        main { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #e2e3e6;
               border-radius: 14px; padding: 32px; }
        .code { margin: 0; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: #b42318; }
        h1 { margin: 6px 0 14px; font-size: 22px; }
        p { margin: 0 0 12px; line-height: 1.7; }
        .hint { padding: 12px 14px; background: #f7f7f5; border-left: 3px solid #c9a227; font-size: 14px; }
        a { display: inline-block; margin-top: 14px; color: #1b4d8c; }
    </style>
</head>
<body>
<main>
    <p class="code">413</p>
    <h1>@lang('app.errors.upload_too_large_title')</h1>
    <p>@lang('app.errors.upload_too_large_body')</p>
    <p class="hint">@lang('app.errors.upload_too_large_hint')</p>
    <a href="{{ url('/') }}">@lang('app.common.back')</a>
</main>
</body>
</html>
