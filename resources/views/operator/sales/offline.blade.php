<!doctype html>
<html lang="{{ request()->user()?->locale() ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="offline-sync-url" content="{{ route('operator.offline-sync.store') }}">
    <meta name="offline-session-id" content="{{ $agentSession->id }}">
    <title>{{ __('app.offline.title', ['code' => $agentSession->agent->agent_code]) }}</title>
    <style>
        :root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#14211c;background:#f4f7f5}
        *{box-sizing:border-box}body{margin:0;min-height:100vh}.main{max-width:680px;margin:24px auto;padding:0 16px}
        .card{background:#fff;border:1px solid #e2e9e5;border-radius:14px;padding:20px;margin:16px 0}
        h1{font-size:24px;line-height:1.2;margin:14px 0 8px}h2{font-size:18px;margin:0 0 12px}
        p{line-height:1.5;color:#52645b}label{display:block;font-size:14px;font-weight:700;margin:16px 0 7px}
        input,select{width:100%;min-height:48px;border:1px solid #cdd9d2;border-radius:9px;padding:12px;font:inherit;color:#14211c;background:#fff;font-size:16px}
        .button{display:inline-flex;min-height:46px;align-items:center;justify-content:center;border:0;border-radius:9px;padding:11px 16px;background:#087b59;color:#fff;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}
        .button.secondary{background:#edf4f0;color:#285343}.button:disabled{opacity:.55;cursor:wait}.row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
        .status{padding:12px;border-radius:9px;background:#eaf7ef;color:#17633d}.error{background:#fff0ed;color:#a33d2b}
        .muted{font-size:13px;color:#718078}.queue-item{padding:12px 0;border-top:1px solid #edf1ee;overflow-wrap:anywhere}
        @media(max-width:420px){.main{margin:16px auto}.card{padding:17px}.row>.button{flex:1}}
    </style>
</head>
<body>
<main class="main">
    <a href="{{ route('operator.sales.show', $agentSession) }}">{{ __('app.offline.back_online') }}</a>
    <h1>{{ __('app.offline.heading') }}</h1>
    <p>{{ __('app.offline.meta', ['code' => $agentSession->agent->agent_code, 'name' => $agentSession->agent->agent_name, 'date' => $agentSession->round->round_date->format('Y-m-d'), 'no' => $agentSession->round->round_no]) }}</p>
    <div class="status" role="status">{{ __('app.offline.notice') }}</div>
    <div class="card">
        <form data-offline-queue-form>
            <label for="queued-type">{{ __('app.offline.sale_type') }}</label>
            <select id="queued-type" name="sale_type">
                <option value="2d">2D</option>
                <option value="3d">3D</option>
            </select>
            <label for="queued-input">{{ __('app.offline.sale_input') }}</label>
            <input id="queued-input" name="input" maxlength="255" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
            <div class="row">
                <button class="button" type="submit">{{ __('app.offline.save_phone') }}</button>
                <button class="button secondary" type="button" data-sync-queue>{{ __('app.offline.sync') }}</button>
            </div>
        </form>
    </div>
    <div id="offline-status" class="status" role="status" aria-live="polite">{{ __('app.offline.checking') }}</div>
    <section class="card">
        <h2>{{ __('app.offline.saved_heading') }}</h2>
        <p class="muted">{{ __('app.offline.saved_help') }}</p>
        <div id="offline-queue-list"></div>
    </section>
</main>
<script>window.offlineLabels = @json(__('app.offline.js'));</script>
<script src="{{ asset('offline-sales.js') }}" defer></script>
<script>
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/service-worker.js').catch(() => {});
</script>
</body>
</html>
