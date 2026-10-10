@extends('layouts.app', ['title' => 'Offline sale reviews'])

@section('content')
<main class="main">
    <a href="{{ route('dashboard') }}">← Live sales dashboard</a>
    <h1 class="page-title" style="margin-top:18px">Offline sale review</h1>
    <p class="subtitle">Conflicts are rechecked against server rules at sync time. Approving overrides Hot Numbers, Round limits, or a closed Round; the decision and reviewer are audited.</p>

    @if(session('status'))
        <div class="notice" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="error" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Status</th><th>Received</th><th>Operator</th><th>Agent / Round</th><th>Type</th><th>Input</th><th>Conflict</th><th>Decision</th></tr></thead>
                <tbody>
                @forelse($reviews as $review)
                    <tr>
                        <td><span class="pill {{ $review->status === 'pending' ? 'off' : '' }}">{{ ucfirst(str_replace('_', ' ', $review->status)) }}</span></td>
                        <td>{{ $review->recorded_at?->timezone('Asia/Yangon')->format('Y-m-d H:i:s') ?? 'Unknown device time' }}</td>
                        <td>{{ $review->operator->name }}</td>
                        <td>{{ $review->agent->agent_code }} · {{ $review->round->round_date->format('Y-m-d') }} · R{{ $review->round->round_no }}</td>
                        <td>{{ strtoupper($review->sale_type) }}</td>
                        <td><strong>{{ $review->original_input }}</strong></td>
                        <td>{{ $review->conflict_reason }}</td>
                        <td>
                            @if($review->status === 'pending')
                                <div class="row">
                                    <form method="POST" action="{{ route('admin.offline-reviews.update', $review) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="approve">
                                        <button class="button" type="submit">Approve override</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.offline-reviews.update', $review) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="reject">
                                        <button class="button danger" type="submit">Reject</button>
                                    </form>
                                </div>
                            @else
                                {{ $review->reviewed_at?->timezone('Asia/Yangon')->format('Y-m-d H:i') }} · {{ $review->reviewer?->name ?? 'Admin' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No offline sales need review.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $reviews->links() }}
    </section>
</main>
@endsection
