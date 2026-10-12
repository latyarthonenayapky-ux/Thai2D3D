@extends('layouts.app', ['title' => __('app.sale.entry').' · 2D'])

@section('content')
<main class="main narrow">
    <section class="card">
        <h1 class="page-title" style="font-size:22px">{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="row">
            <a class="button secondary" href="{{ route('dashboard') }}">{{ __('app.nav.dashboard') }}</a>
            <a class="button secondary" href="{{ route('three-digit.sales.workspace') }}">{{ __('app.sale.entry_3d') }}</a>
        </div>
    </section>
</main>
@endsection
