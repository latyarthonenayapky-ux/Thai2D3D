@extends('layouts.app', ['title' => __('app.backup.title')])

@section('content')
<main class="main">
    <div class="eyebrow">{{ __('app.settings.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.backup.title') }}</h1>
    <p class="subtitle">{{ __('app.backup.subtitle') }}</p>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error" role="alert">
            <strong>{{ __('app.settings.review_problem') }}</strong>
            <ul style="margin:8px 0 0;padding-left:20px">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card" id="backup-create" style="margin-bottom:20px">
        <div class="between">
            <div>
                <h2>{{ __('app.backup.create') }}</h2>
                <p style="margin:0" class="muted">{{ __('app.backup.engine') }}: <strong>{{ strtoupper($driver) }}</strong> · {{ __('app.backup.location') }}: <code>{{ $directory }}</code></p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.data-backup.store') }}" style="margin-top:14px">
            @csrf
            <button class="button" type="submit">{{ __('app.backup.create') }}</button>
        </form>
    </section>

    <section class="card" id="backup-list" style="margin-bottom:20px">
        <h2>{{ __('app.backup.stored_backups') }}</h2>
        @if($backups->isEmpty())
            <p class="muted">{{ __('app.backup.empty') }}</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('app.backup.file') }}</th>
                            <th>{{ __('app.backup.engine') }}</th>
                            <th>{{ __('app.backup.size') }}</th>
                            <th>{{ __('app.backup.created_at') }}</th>
                            <th>{{ __('app.common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($backups as $backup)
                        <tr>
                            <td><code>{{ $backup['name'] }}</code></td>
                            <td><span class="pill {{ $backup['engine'] === $driver ? '' : 'off' }}">{{ strtoupper($backup['engine']) }}</span></td>
                            <td>{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                            <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['created_at'])->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="row">
                                    <a class="button secondary" href="{{ route('admin.data-backup.download', $backup['name']) }}">{{ __('app.backup.download') }}</a>
                                    <form method="POST" action="{{ route('admin.data-backup.destroy', $backup['name']) }}" onsubmit="return confirm('{{ __('app.backup.confirm_remove') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button danger" type="submit">{{ __('app.backup.remove') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card" id="business-move" style="margin-bottom:20px">
        <h2>{{ __('app.move.title') }}</h2>
        <p class="muted">{{ __('app.move.subtitle') }}</p>

        <div style="margin-top:14px">
            <a class="button secondary" href="{{ route('admin.business-move.export') }}">{{ __('app.move.export') }}</a>
            <p class="muted" style="margin-top:8px">{{ __('app.move.export_help') }}</p>
        </div>

        <form method="POST" action="{{ route('admin.business-move.import.preview') }}" enctype="multipart/form-data" style="margin-top:16px">
            @csrf
            <div>
                <label for="move-bundle">{{ __('app.move.pick') }}</label>
                <input id="move-bundle" name="bundle" type="file" accept=".json,application/json">
            </div>
            <button class="button" type="submit" style="margin-top:12px">{{ __('app.move.preview') }}</button>
            <p class="muted" style="margin-top:8px">{{ __('app.move.import_help') }}</p>
        </form>
    </section>

    <section class="card" id="backup-restore">
        <h2>{{ __('app.backup.restore_title') }}</h2>
        <p class="muted">{{ __('app.backup.restore_warning') }}</p>
        <form method="POST" action="{{ route('admin.data-backup.restore') }}" enctype="multipart/form-data" style="margin-top:14px">
            @csrf
            <div>
                <label for="restore-upload">{{ __('app.backup.restore_pick') }}</label>
                <input id="restore-upload" name="upload" type="file" accept=".sql,text/plain">
            </div>
            @if($backups->isNotEmpty())
                <div style="margin-top:14px">
                    <label for="restore-stored">{{ __('app.backup.restore_or') }}</label>
                    <select id="restore-stored" name="stored">
                        <option value="">{{ __('app.common.none') }}</option>
                        @foreach($backups as $backup)
                            <option value="{{ $backup['name'] }}" @selected(old('stored') === $backup['name'])>{{ $backup['name'] }} · {{ strtoupper($backup['engine']) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <label style="display:flex;align-items:flex-start;gap:10px;font-weight:500;margin-top:16px">
                <input class="checkbox" id="restore-confirm" type="checkbox" name="confirm" value="on" @checked(old('confirm') === 'on')>
                <span>{{ __('app.backup.restore_confirm') }}</span>
            </label>
            <button class="button danger" type="submit" style="margin-top:16px">{{ __('app.backup.confirm') }}</button>
        </form>
    </section>
</main>
@endsection
