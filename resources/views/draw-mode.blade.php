@extends('layouts.app', ['title' => 'Choose sales mode'])

@section('content')
<main class="main narrow mode-picker">
    <h1 class="page-title">Choose a sales mode</h1>
    <p class="subtitle">2D runs three Rounds each day. 3D Draws are held on the 1st and 16th of each month.</p>
    <form method="POST" action="{{ route('draw-mode.store') }}" id="draw-mode-form">
        @csrf
        <input type="hidden" name="mode" id="draw-mode-value" value="2d">
        <div class="mode-options" role="radiogroup" aria-label="Sales mode">
            <button class="mode-option is-selected" type="button" role="radio" aria-checked="true" data-mode-choice="2d" autofocus>
                <span class="mode-option-title">2D</span>
                <span class="mode-option-copy">Daily · Three Rounds</span>
            </button>
            <button class="mode-option" type="button" role="radio" aria-checked="false" data-mode-choice="3d">
                <span class="mode-option-title">3D</span>
                <span class="mode-option-copy">Monthly · 1st and 16th</span>
            </button>
        </div>
        <button class="button full" type="submit">Enter 2D</button>
    </form>
</main>
<script>
    (() => {
        const form = document.getElementById('draw-mode-form');
        const input = document.getElementById('draw-mode-value');
        const submit = form.querySelector('[type="submit"]');
        const choices = [...form.querySelectorAll('[data-mode-choice]')];
        const select = mode => {
            input.value = mode;
            choices.forEach(choice => {
                const active = choice.dataset.modeChoice === mode;
                choice.classList.toggle('is-selected', active);
                choice.setAttribute('aria-checked', String(active));
            });
            submit.textContent = `Enter ${mode.toUpperCase()}`;
        };
        choices.forEach((choice, index) => {
            choice.addEventListener('click', () => select(choice.dataset.modeChoice));
            choice.addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    select(choice.dataset.modeChoice);
                    form.requestSubmit();
                    return;
                }
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                event.preventDefault();
                const next = choices[(index + (event.key === 'ArrowRight' ? 1 : -1) + choices.length) % choices.length];
                select(next.dataset.modeChoice);
                next.focus();
            });
        });
        form.addEventListener('submit', event => {
            if (input.value === '2d') return;
            event.preventDefault();
            sessionStorage.setItem('thai2d3d-preferred-mode', input.value);
            form.requestSubmit();
        });
        window.addEventListener('keydown', event => {
            if (event.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    })();
</script>
@endsection
