@extends('layouts.app', ['title' => 'Round settlements'])

@section('content')
<main class="main">
    <div class="eyebrow">Business reports</div>
    <h1 class="page-title" style="margin-top:8px">Round results and settlements</h1>
    <p class="subtitle">Closed Rounds belonging to your business. Results, correction history, and recalculated 2D settlement are available per Round.</p>
    <p class="row"><a class="button" href="{{ route('reports.summary', ['mode' => '2d']) }}">2D summaries</a><a class="button secondary" href="{{ route('admin.three-digit.results') }}">3D Draw results and settlements</a><a class="button secondary" href="{{ route('reports.summary', ['mode' => '3d']) }}">3D summaries</a></p>

    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Round</th><th>2D result</th><th>Settlement</th></tr></thead>
                <tbody>
                @forelse($rounds as $round)
                    <tr>
                        <td>{{ $round->round_date->format('Y-m-d') }}</td>
                        <td>{{ $round->round_no }}</td>
                        <td>{{ $round->result?->result_2d ?? 'Not entered' }}</td>
                        <td><a class="button secondary" href="{{ route('admin.rounds.settlement', $round) }}">Open 2D result / settlement</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No closed Rounds yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px">{{ $rounds->links() }}</div>
    </section>
</main>
@endsection
