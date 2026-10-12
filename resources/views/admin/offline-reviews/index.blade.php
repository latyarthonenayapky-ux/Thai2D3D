@extends('layouts.app', ['title' => __('app.reviews.title')])

@section('content')
<main class="main">
    <a href="{{ route('dashboard') }}">{{ __('app.reviews.back_dashboard') }}</a>
    <h1 class="page-title" style="margin-top:18px">{{ __('app.reviews.heading') }}</h1>
    <p class="subtitle">{{ __('app.reviews.subtitle') }}</p>

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
                <thead><tr><th>{{ __('app.common.status') }}</th><th>{{ __('app.reviews.col_received') }}</th><th>{{ __('app.reviews.col_operator') }}</th><th>{{ __('app.reviews.col_agent_round') }}</th><th>{{ __('app.reviews.col_type') }}</th><th>{{ __('app.sale.input') }}</th><th>{{ __('app.reviews.col_conflict') }}</th><th>{{ __('app.reviews.col_decision') }}</th></tr></thead>
                <tbody>
                @forelse($reviews as $review)
                    <tr>
                        @php $reviewStatusKey = 'app.reviews.status_'.str_replace('-', '_', strtolower($review->status)); @endphp
                        <td><span class="pill {{ $review->status === 'pending' ? 'off' : '' }}">{{ Illuminate\Support\Facades\Lang::has($reviewStatusKey) ? __($reviewStatusKey) : ucfirst(str_replace('_', ' ', $review->status)) }}</span></td>
                        <td>{{ $review->recorded_at?->timezone('Asia/Yangon')->format('Y-m-d H:i:s') ?? __('app.reviews.unknown_time') }}</td>
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
                                        <button class="button" type="submit">{{ __('app.reviews.approve') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.offline-reviews.update', $review) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="reject">
                                        <button class="button danger" type="submit">{{ __('app.reviews.reject') }}</button>
                                    </form>
                                </div>
                            @else
                                {{ $review->reviewed_at?->timezone('Asia/Yangon')->format('Y-m-d H:i') }} · {{ $review->reviewer?->name ?? __('app.reviews.admin') }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">{{ __('app.reviews.no_reviews') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $reviews->links() }}
    </section>
</main>
@endsection
