@extends('layouts.app', ['title' => __('app.move.preview_title')])

@section('content')
<main class="main">
    <div class="eyebrow">{{ __('app.backup.title') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.move.preview_title') }}</h1>
    <p class="subtitle">
        {{ __('app.move.preview_subtitle', [
            'name' => $report['business']['name'] ?? '?',
            'email' => $report['business']['email'] ?? '?',
            'when' => $report['created_at'] ?? '?',
            'engine' => strtoupper((string) $report['source_engine']),
        ]) }}
    </p>

    @if($report['conflicts'] !== [])
        <div class="error" role="alert">
            <strong>{{ __('app.move.conflict_heading', ['count' => count($report['conflicts'])]) }}</strong>
            <ul style="margin:8px 0 0;padding-left:20px">
                @foreach($report['conflicts'] as $conflict)
                    <li><strong>{{ $conflict['table'] }}</strong> · {{ $conflict['key'] }} — {{ $conflict['reason'] }}</li>
                @endforeach
            </ul>
            <p style="margin:10px 0 0">{{ __('app.move.conflict_advice') }}</p>
        </div>
    @else
        <div class="notice" role="status">{{ __('app.move.nothing_written') }}</div>
    @endif

    @if($report['ignored'] !== [])
        <p class="muted">{{ __('app.move.ignored', ['tables' => implode(', ', $report['ignored'])]) }}</p>
    @endif

    <section class="card" style="margin-bottom:20px">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('app.move.col_table') }}</th>
                        <th>{{ __('app.move.col_bundle') }}</th>
                        <th>{{ __('app.move.col_new') }}</th>
                        <th>{{ __('app.move.col_here') }}</th>
                        <th>{{ __('app.move.col_skipped') }}</th>
                        <th>{{ __('app.move.col_orphan') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($report['tables'] as $table => $counts)
                    @continue($counts['source'] === 0)
                    <tr>
                        <td>{{ $counts['label'] }} <span class="muted">{{ $table }}</span></td>
                        <td>{{ number_format($counts['source']) }}</td>
                        <td><strong>{{ number_format($counts['new']) }}</strong></td>
                        <td>{{ number_format($counts['matched']) }}</td>
                        <td>{{ number_format($counts['skipped']) }}</td>
                        <td>{{ $counts['orphan'] > 0 ? number_format($counts['orphan']) : 0 }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <p class="muted" style="margin-top:14px">
            {{ __('app.move.legend', [
                'new' => number_format($report['totals']['new']),
                'here' => number_format($report['totals']['matched']),
                'skipped' => number_format($report['totals']['skipped']),
                'orphan' => number_format($report['totals']['orphan']),
            ]) }}
        </p>
        <p class="muted">{{ __('app.move.excluded') }}</p>
    </section>

    @if($report['conflicts'] === [] && $report['totals']['new'] > 0)
        <section class="card">
            <h2>{{ __('app.move.apply_heading') }}</h2>
            <p class="muted">{{ __('app.move.apply_warning') }}</p>
            <form method="POST" action="{{ route('admin.business-move.import.apply') }}" style="margin-top:14px">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label style="display:flex;align-items:flex-start;gap:10px;font-weight:500">
                    <input class="checkbox" type="checkbox" name="confirm" value="on">
                    <span>{{ __('app.move.apply_confirm', ['rows' => number_format($report['totals']['new'])]) }}</span>
                </label>
                <div class="row" style="margin-top:16px">
                    <button class="button danger" type="submit">{{ __('app.move.apply') }}</button>
                    <a class="button secondary" href="{{ route('admin.data-backup.index') }}">{{ __('app.move.cancel') }}</a>
                </div>
            </form>
        </section>
    @else
        <div class="row">
            <a class="button secondary" href="{{ route('admin.data-backup.index') }}">{{ __('app.move.cancel') }}</a>
        </div>
    @endif
</main>
@endsection
