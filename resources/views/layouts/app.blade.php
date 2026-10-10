<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Thai2D3D' }} · Thai2D3D</title>
    <style>
        :root{font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-synthesis:none;color-scheme:light;--page:#f4f7f5;--surface:#fff;--surface-soft:#edf4f0;--text:#14211c;--muted:#5e7168;--border:#d9e3dd;--gridline:#e4ebe6;--accent:#087b59;--accent-hover:#066c4e;--accent-soft:#e3f2eb;--accent-text:#285343;--danger-bg:#fff0ed;--danger-text:#923723;--notice-bg:#eaf7ef;--notice-text:#17633d;--clock-yellow:#765300;--clock-orange:#96390e;--clock-red:#b4232c;--font-scale:1}
        html[data-theme=dark]{color-scheme:dark;--page:#111916;--surface:#19231e;--surface-soft:#26342d;--text:#e7eee9;--muted:#aabbb0;--border:#37483e;--gridline:#344239;--accent:#54c59a;--accent-hover:#73d6ad;--accent-soft:#244b3a;--accent-text:#d5eee1;--danger-bg:#4b2923;--danger-text:#ffd0c2;--notice-bg:#204333;--notice-text:#c3f0d2;--clock-yellow:#f4ce67;--clock-orange:#ffad7a;--clock-red:#ff8585}
        @media(prefers-color-scheme:dark){html[data-theme=system]{color-scheme:dark;--page:#111916;--surface:#19231e;--surface-soft:#26342d;--text:#e7eee9;--muted:#aabbb0;--border:#37483e;--gridline:#344239;--accent:#54c59a;--accent-hover:#73d6ad;--accent-soft:#244b3a;--accent-text:#d5eee1;--danger-bg:#4b2923;--danger-text:#ffd0c2;--notice-bg:#204333;--notice-text:#c3f0d2;--clock-yellow:#f4ce67;--clock-orange:#ffad7a;--clock-red:#ff8585}}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--page);color:var(--text);font-size:calc(1rem * var(--font-scale))}a{color:var(--accent);text-decoration:none}a:hover{text-decoration:underline}
        .shell{min-height:100vh}.topbar{min-height:58px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:16px;padding:7px max(20px,calc((100vw - 1480px)/2));-webkit-app-region:drag}.topbar a,.topbar button,.topbar input,.topbar select,.topbar summary,.topbar form,.topbar .top-actions,.topbar .primary-nav,.topbar .window-controls{-webkit-app-region:no-drag}
        .primary-nav{display:flex;align-items:center;gap:4px;flex-wrap:wrap}.primary-nav>a,.nav-menu>summary{display:inline-flex;align-items:center;min-height:38px;padding:7px 10px;border-radius:5px;color:var(--text);font-size:13px;font-weight:700;cursor:pointer;list-style:none}.primary-nav>a:hover,.nav-menu>summary:hover,.primary-nav>a[aria-current=page]{background:var(--accent-soft);color:var(--accent-text);text-decoration:none}.nav-menu{position:relative}.nav-menu>summary::-webkit-details-marker{display:none}.nav-popover{position:absolute;top:calc(100% + 7px);left:0;z-index:20;min-width:190px;padding:6px;background:var(--surface);border:1px solid var(--border);box-shadow:0 8px 24px #0002;border-radius:7px}.nav-popover>a{display:block;padding:9px 10px;border-radius:4px;color:var(--text);font-size:13px}.nav-popover>a:hover{background:var(--surface-soft);text-decoration:none}.nav-popover .display-settings{padding:8px 10px}.nav-popover .display-settings summary{color:var(--text);font-size:13px}.nav-popover .display-popover{position:static;width:auto;margin-top:8px;box-shadow:none;padding:8px}.header-tools{display:flex;align-items:center;justify-content:flex-end;gap:14px}.top-actions{display:flex;align-items:center;gap:12px;color:var(--muted);font-size:13px;flex-wrap:wrap}
        .brand{font-weight:800;font-size:18px;letter-spacing:-.04em;color:var(--text);white-space:nowrap}
        .window-controls{display:flex;align-items:center;gap:4px;-webkit-app-region:no-drag}.window-controls button{width:34px;height:30px;border:0;border-radius:4px;background:transparent;color:var(--text);font:inherit;font-size:14px;cursor:pointer}.window-controls button:hover{background:var(--surface-soft)}.window-controls button:last-child:hover{background:#c42b1c;color:#fff}
        .round-clock{min-width:112px;text-align:center;color:var(--accent);font-variant-numeric:tabular-nums}.clock-time{display:block;font-size:18px;font-weight:800;line-height:1.1}.clock-date{display:block;margin-top:3px;font-size:11px;color:var(--muted)}.round-clock[data-state=yellow]{color:var(--clock-yellow)}.round-clock[data-state=orange]{color:var(--clock-orange)}.round-clock[data-state=red]{color:var(--clock-red)}
        .main{max-width:1280px;margin:32px auto;padding:0 24px}.page-title{margin:0;font-size:28px;letter-spacing:-.04em}.subtitle{margin:8px 0 24px;color:var(--muted)}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;box-shadow:0 6px 20px #183a2a0a}
        .narrow{max-width:440px;margin:9vh auto}.eyebrow{color:var(--accent);text-transform:uppercase;font-size:11px;font-weight:800;letter-spacing:.12em}
        label{display:block;font-size:13px;font-weight:700;margin:16px 0 7px}input,select,textarea{width:100%;border:1px solid var(--border);border-radius:7px;padding:11px 12px;font:inherit;color:var(--text);background:var(--surface)}textarea{min-height:110px;resize:vertical}input:focus,select:focus,textarea:focus{outline:3px solid color-mix(in srgb,var(--accent) 28%,transparent);border-color:var(--accent)}
        .button{display:inline-flex;justify-content:center;align-items:center;border:0;border-radius:7px;background:var(--accent);color:#fff;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer}.button:hover{background:var(--accent-hover);text-decoration:none}
        .button.secondary{background:var(--surface-soft);color:var(--accent-text)}.button.danger{background:var(--danger-bg);color:var(--danger-text)}.button.full{width:100%;margin-top:20px}
        .row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.between{display:flex;justify-content:space-between;align-items:center;gap:16px}
        .notice{border-radius:7px;padding:12px 14px;margin:16px 0;background:var(--notice-bg);color:var(--notice-text);font-size:14px}.error{border-radius:7px;padding:12px 14px;margin:16px 0;background:var(--danger-bg);color:var(--danger-text);font-size:14px}
        .muted{color:var(--muted);font-size:13px}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;font-size:14px;background:var(--surface)}th,td{text-align:left;padding:11px 12px;border:1px solid var(--gridline);white-space:nowrap}th{color:var(--muted);background:var(--surface-soft);font-size:11px;text-transform:uppercase;letter-spacing:.06em}
        .pill{display:inline-block;border-radius:4px;background:var(--notice-bg);color:var(--notice-text);padding:4px 8px;font-size:12px;font-weight:700}.pill.off{background:var(--danger-bg);color:var(--danger-text)}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.card h2{margin:0 0 8px;font-size:17px}.card p{color:var(--muted);line-height:1.55}.alert-space{min-height:1px}
        .checkbox{width:auto}.inline-form{display:inline}.footer-link{margin-top:18px;font-size:14px}
        .display-settings{position:relative}.display-settings summary{cursor:pointer;list-style:none;color:var(--accent);font-weight:700}.display-settings summary::-webkit-details-marker{display:none}.display-popover{position:absolute;right:0;top:calc(100% + 10px);z-index:20;width:230px;padding:14px;background:var(--surface);border:1px solid var(--border);box-shadow:0 8px 24px #0002;border-radius:8px}.display-popover label{margin:8px 0 4px}.display-popover select{padding:8px}
        .mode-switch{display:flex;align-items:center;gap:6px}.mode-switch select{width:auto;min-width:80px;padding:7px 9px;font-size:13px}
        .mode-picker{max-width:620px}.mode-options{display:grid;grid-template-columns:1fr 1fr;border:1px solid var(--border);background:var(--surface);border-radius:10px;overflow:hidden}.mode-option{display:grid;gap:6px;text-align:left;padding:22px;background:transparent;color:var(--text);border:0;border-right:1px solid var(--border);cursor:pointer;font:inherit}.mode-option:last-child{border-right:0}.mode-option.is-selected{background:var(--accent-soft);box-shadow:inset 0 -3px var(--accent)}.mode-option-title{font-size:24px;font-weight:800}.mode-option-copy{color:var(--muted);font-size:13px}
        .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.clock-countdown{display:block;margin-top:3px;font-size:10px;font-weight:700}
        @media(max-width:760px){.topbar{padding:10px 12px;align-items:flex-start;flex-wrap:wrap}.primary-nav{width:100%;gap:2px}.primary-nav>a,.nav-menu>summary{padding:6px 8px;font-size:12px}.header-tools{width:100%;justify-content:space-between;align-items:center}.top-actions{gap:10px;font-size:12px;flex:1;justify-content:flex-end}.top-actions>span{flex-basis:100%;text-align:right}.round-clock{min-width:96px}.main{margin:24px auto;padding:0 16px}.narrow{margin:7vh auto}.card{padding:18px}.page-title{font-size:24px}}
        @media(max-width:430px){.header-tools{gap:12px}.top-actions{max-width:calc(100% - 108px)}.top-actions a{white-space:nowrap}.nav-popover{left:auto;right:0}.display-popover{position:static}}
    </style>
</head>
<body>
@php
    $layoutNow = \Carbon\Carbon::now('Asia/Yangon');
    $layoutCloseAt = null;
    $layoutMode = request()->query('mode', session('draw_mode', '2d'));
    if (request()->routeIs('three-digit.sales.*', 'admin.three-digit.*') || $layoutMode === '3d') {
        $layoutMode = '3d';
    }
    $layoutCloseLabel = $layoutMode === '3d' ? 'Draw' : 'Round';
    $layoutStatementUrl = route('operator.sales.workspace', ['view' => 'agent']);
    if (isset($agentSession)) {
        $layoutStatementUrl = route('operator.sales.show', [$agentSession, 'mode' => $layoutMode, 'view' => 'agent']);
    } elseif (isset($selectedAgent) && $selectedAgent) {
        $layoutStatementUrl = route('three-digit.sales.workspace', ['agent_id' => $selectedAgent->id, 'view' => 'agent']);
    }
    if (auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isOperator())) {
        $layoutAdminId = auth()->user()->isAdmin() ? auth()->id() : auth()->user()->admin_id;
        if ($layoutAdminId && $layoutMode === '3d') {
            $layoutDraw = \App\Models\ThreeDigitDraw::query()
                ->where('admin_id', $layoutAdminId)
                ->whereDate('draw_date', $layoutNow->toDateString())
                ->where('status', 'open')
                ->first();
            if ($layoutDraw) {
                $layoutCloseAt = \Carbon\Carbon::parse(
                    $layoutDraw->draw_date->toDateString().' '.$layoutDraw->result_time,
                    'Asia/Yangon'
                );
            }
        } elseif ($layoutAdminId) {
            $layoutRound = \App\Models\Round::query()
                ->where('admin_id', $layoutAdminId)
                ->whereDate('round_date', $layoutNow->toDateString())
                ->where('status', 'open')
                ->orderBy('close_time')
                ->first();
            if ($layoutRound) {
            $layoutCloseAt = \Carbon\Carbon::parse(
                $layoutRound->round_date->toDateString().' '.$layoutRound->close_time,
                'Asia/Yangon'
            );
            }
        }
    }
@endphp
<div class="shell">
    @auth
        <header class="topbar">
            <nav class="primary-nav" aria-label="Primary navigation">
                <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a>
                @if(auth()->user()->isAdmin() || auth()->user()->isOperator())
                    <details class="nav-menu">
                        <summary>Sale Entry</summary>
                        <div class="nav-popover">
                            <a href="{{ route('operator.sales.workspace') }}">2D Sale Entry</a>
                            <a href="{{ route('three-digit.sales.workspace') }}">3D Sale Entry</a>
                        </div>
                    </details>
                    <a href="{{ route('reports.summary', ['mode' => $layoutMode]) }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif>Report</a>
                @endif
                @if(auth()->user()->isAdmin())
                    <a href="{{ $layoutMode === '3d' ? route('admin.three-digit.index') : route('admin.settings.index') }}" @if(request()->routeIs('admin.settings.*', 'admin.three-digit.*')) aria-current="page" @endif>Setting</a>
                @endif
                <details class="nav-menu">
                    <summary>Menu</summary>
                    <div class="nav-popover">
                        <a href="{{ route('manual') }}" @if(request()->routeIs('manual')) aria-current="page" @endif>Manual</a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.settings.index') }}#agents">Add Agent</a>
                            <a href="{{ route('admin.users.index') }}">Add Operator</a>
                        @endif
                        @if(auth()->user()->isAdmin() || auth()->user()->isOperator())
                            <a href="{{ $layoutStatementUrl }}">Agent Statement</a>
                        @endif
                        <details class="display-settings">
                            <summary>Display</summary>
                            <div class="display-popover">
                                <label for="ui-theme">Theme</label>
                                <select id="ui-theme">
                                    <option value="system">System</option>
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                                <label for="ui-font-size">Font size</label>
                                <select id="ui-font-size">
                                    <option value="0.9">Small</option>
                                    <option value="1">Standard</option>
                                    <option value="1.1">Large</option>
                                    <option value="1.2">Extra large</option>
                                </select>
                            </div>
                        </details>
                    </div>
                </details>
            </nav>
            <div class="header-tools">
                <div class="round-clock" id="round-clock" data-state="normal"
                    data-server-now="{{ $layoutNow->getTimestampMs() }}"
                    data-close-label="{{ $layoutCloseLabel }}"
                    @if($layoutCloseAt) data-close-at="{{ $layoutCloseAt->getTimestampMs() }}" @endif>
                    <time class="clock-time" id="clock-time">{{ $layoutNow->format('H:i:s') }}</time>
                    <time class="clock-date" id="clock-date">{{ $layoutNow->format('F j, Y') }}</time>
                    <span class="clock-countdown" id="clock-countdown" aria-live="polite" hidden></span>
                </div>
                <div class="top-actions">
                    <span>{{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline-form">
                        @csrf
                        <button type="submit" class="button secondary">Log out</button>
                    </form>
                </div>
                @if(env('THAI2D3D_DESKTOP') === 'true')
                    <div class="window-controls" aria-label="Window controls">
                        <button type="button" data-window-action="minimize" aria-label="Minimize window" title="Minimize">−</button>
                        <button type="button" data-window-action="toggleMaximize" aria-label="Maximize or restore window" title="Maximize or restore">□</button>
                        <button type="button" data-window-action="close" aria-label="Close window" title="Close">×</button>
                    </div>
                @endif
            </div>
        </header>
    @else
        <header class="topbar">
            <a class="brand" href="{{ route('login') }}">Thai2D3D</a>
            <div class="round-clock" id="round-clock" data-state="normal"
                data-server-now="{{ $layoutNow->getTimestampMs() }}"
                data-close-label="{{ $layoutCloseLabel }}">
                <time class="clock-time" id="clock-time">{{ $layoutNow->format('H:i:s') }}</time>
                <time class="clock-date" id="clock-date">{{ $layoutNow->format('F j, Y') }}</time>
            </div>
            @if(env('THAI2D3D_DESKTOP') === 'true')
                <div class="window-controls" aria-label="Window controls">
                    <button type="button" data-window-action="minimize" aria-label="Minimize window" title="Minimize">−</button>
                    <button type="button" data-window-action="toggleMaximize" aria-label="Maximize or restore window" title="Maximize or restore">□</button>
                    <button type="button" data-window-action="close" aria-label="Close window" title="Close">×</button>
                </div>
            @endif
        </header>
    @endauth
    @yield('content')
</div>
<script>
    (() => {
        const windowControls = window.thai2d3dWindow;
        if (windowControls) {
            document.querySelectorAll('[data-window-action]').forEach(button => {
                const action = button.dataset.windowAction;
                if (typeof windowControls[action] === 'function') {
                    button.addEventListener('click', windowControls[action]);
                }
            });
        }
        const root = document.documentElement;
        const theme = document.getElementById('ui-theme');
        const fontSize = document.getElementById('ui-font-size');
        if (theme && fontSize) {
            const themeKey = 'thai2d3d-ui-theme';
            const fontKey = 'thai2d3d-font-scale';
            const savedTheme = localStorage.getItem(themeKey);
            const savedFont = localStorage.getItem(fontKey);
            theme.value = ['system', 'light', 'dark'].includes(savedTheme) ? savedTheme : 'system';
            fontSize.value = ['0.9', '1', '1.1', '1.2'].includes(savedFont) ? savedFont : '1';
            root.dataset.theme = theme.value;
            root.style.setProperty('--font-scale', fontSize.value);
            theme.addEventListener('change', () => {
                root.dataset.theme = theme.value;
                localStorage.setItem(themeKey, theme.value);
            });
            fontSize.addEventListener('change', () => {
                root.style.setProperty('--font-scale', fontSize.value);
                localStorage.setItem(fontKey, fontSize.value);
            });
        }
        const clock = document.getElementById('round-clock');
        const time = document.getElementById('clock-time');
        const date = document.getElementById('clock-date');
        const countdown = document.getElementById('clock-countdown');
        if (!clock || !time || !date) return;
        const serverNow = Number(clock.dataset.serverNow);
        const closeAt = Number(clock.dataset.closeAt || 0);
        const startedAt = Date.now();
        const formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Yangon', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
        });
        const dateFormatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Yangon', year: 'numeric', month: 'long', day: 'numeric',
        });
        const tick = () => {
            const now = serverNow + Date.now() - startedAt;
            const dateValue = new Date(now);
            time.textContent = formatter.format(dateValue);
            date.textContent = dateFormatter.format(dateValue);
            if (!closeAt || !countdown) return;
            const remaining = Math.max(0, closeAt - now);
            const minutes = Math.ceil(remaining / 60000);
            clock.dataset.state = remaining === 0 ? 'red' : minutes <= 2 ? 'red' : minutes <= 5 ? 'orange' : minutes <= 10 ? 'yellow' : 'normal';
            countdown.hidden = false;
            countdown.textContent = remaining === 0
                ? `${clock.dataset.closeLabel || 'Round'} closed`
                : `Closes in ${String(Math.floor(remaining / 3600000)).padStart(2, '0')}:${String(Math.floor((remaining % 3600000) / 60000)).padStart(2, '0')}:${String(Math.floor((remaining % 60000) / 1000)).padStart(2, '0')}`;
        };
        tick();
        window.setInterval(tick, 1000);
    })();
</script>
</body>
</html>
