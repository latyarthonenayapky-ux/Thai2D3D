@extends('layouts.app', ['title' => __('app.rounds.title')])

@section('content')
<main class="main">
    <div class="eyebrow">{{ __('app.rounds.eyebrow') }}</div>
    <h1 class="page-title" style="margin-top:8px">{{ __('app.rounds.heading') }}</h1>
    <p class="subtitle">{{ __('app.rounds.subtitle') }}</p>
    <p class="row"><a class="button" href="{{ route('reports.summary', ['mode' => '2d']) }}">{{ __('app.rounds.summaries_2d') }}</a><a class="button secondary" href="{{ route('admin.three-digit.results') }}">{{ __('app.rounds.draw_results_3d') }}</a><a class="button secondary" href="{{ route('reports.summary', ['mode' => '3d']) }}">{{ __('app.rounds.summaries_3d') }}</a></p>

    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('app.common.date') }}</th><th>{{ __('app.common.round') }}</th><th>{{ __('app.rounds.col_result_2d') }}</th><th>{{ __('app.rounds.col_settlement') }}</th></tr></thead>
                <tbody>
                @forelse($rounds as $round)
                    <tr>
                        <td>{{ $round->round_date->format('Y-m-d') }}</td>
                        <td>{{ $round->round_no }}</td>
                        <td>{{ $round->result?->result_2d ?? __('app.rounds.not_entered') }}</td>
                        <td><a class="button secondary" href="{{ route('admin.rounds.settlement', $round) }}">{{ __('app.rounds.open_settlement') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('app.rounds.no_rounds') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px">{{ $rounds->links() }}</div>
    </section>
</main>
@endsection
