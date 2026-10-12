<details class="display-settings">
    <summary>{{ __('app.nav.language') }}</summary>
    <div class="display-popover">
        @foreach (\App\Models\UserPreference::supportedLocales() as $code => $label)
            <form method="POST" action="{{ route('preferences.locale') }}" class="inline-form language-form">
                @csrf
                <input type="hidden" name="locale" value="{{ $code }}">
                <button class="button {{ ($layoutUser?->locale() ?? config('app.locale')) === $code ? '' : 'secondary' }}" type="submit">
                    {{ $label }}
                    @if(($layoutUser?->locale() ?? config('app.locale')) === $code)
                        <span aria-hidden="true">&#10003;</span>
                    @endif
                </button>
            </form>
        @endforeach
        <p class="muted">{{ __('app.language.hint') }}</p>
    </div>
</details>
