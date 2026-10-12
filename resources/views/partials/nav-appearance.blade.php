<details class="display-settings">
    <summary>{{ __('app.nav.display') }}</summary>
    <div class="display-popover">
        <label for="ui-theme">{{ __('app.display.theme') }}</label>
        <select id="ui-theme">
            <option value="system">{{ __('app.display.system') }}</option>
            <option value="light">{{ __('app.display.light') }}</option>
            <option value="dark">{{ __('app.display.dark') }}</option>
        </select>
        <label for="ui-font-size">{{ __('app.display.font_size') }}</label>
        <select id="ui-font-size">
            <option value="0.9">{{ __('app.display.small') }}</option>
            <option value="1">{{ __('app.display.standard') }}</option>
            <option value="1.1">{{ __('app.display.large') }}</option>
            <option value="1.2">{{ __('app.display.extra_large') }}</option>
        </select>
        <form method="POST" action="{{ route('preferences.display') }}" class="display-form">
            @csrf
            @method('PATCH')
            <label for="ui-clock-format">{{ __('app.display.clock_format') }}</label>
            <select id="ui-clock-format" name="clock_format">
                <option value="24" @selected(($layoutUser?->clockFormat() ?? '24') === '24')>{{ __('app.display.hour_24') }}</option>
                <option value="12" @selected(($layoutUser?->clockFormat() ?? '24') === '12')>{{ __('app.display.hour_12') }}</option>
            </select>
            <label for="ui-currency-label">{{ __('app.display.currency_label') }}</label>
            <input id="ui-currency-label" name="currency_label" maxlength="10" value="{{ $layoutUser?->currencyLabel() ?? config('app.currency', 'MMK') }}">
            <button class="button secondary" type="submit">{{ __('app.common.save') }}</button>
        </form>
        <p class="muted">{{ __('app.display.theme_hint') }}</p>
    </div>
</details>
