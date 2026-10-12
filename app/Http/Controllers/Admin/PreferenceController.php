<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    private const IDLE_OPTIONS = [0, 15, 30, 60, 120, 240, 480];

    public function locale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(UserPreference::supportedLocales()))],
        ]);

        $request->user()->rememberPreference('locale', $validated['locale']);

        return $this->backWith('app.preferences_saved', ['name' => UserPreference::supportedLocales()[$validated['locale']]]);
    }

    public function display(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clock_format' => ['required', 'in:12,24'],
            'currency_label' => ['required', 'string', 'max:10'],
        ]);

        $user = $request->user();
        $user->rememberPreference('clock_format', $validated['clock_format']);
        $user->rememberPreference('currency_label', strtoupper($validated['currency_label']));

        return $this->backWith('app.preferences_saved');
    }

    public function saleEntry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sale_typing_normalization' => ['required', 'in:on,off'],
        ]);

        $request->user()->rememberPreference(
            'sale_typing_normalization',
            $validated['sale_typing_normalization'] === 'on' ? 'on' : 'off'
        );

        return $this->backWith('app.preferences_saved');
    }

    public function notifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'review_alerts' => ['required', 'in:on,off'],
        ]);

        $request->user()->rememberPreference(
            'review_alerts',
            $validated['review_alerts'] === 'on' ? 'on' : 'off'
        );

        return $this->backWith('app.preferences_saved');
    }

    public function security(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idle_minutes' => ['required', 'integer', 'in:'.implode(',', self::IDLE_OPTIONS)],
        ]);

        $request->user()->rememberPreference('idle_minutes', (string) $validated['idle_minutes']);

        return $this->backWith('app.preferences_saved');
    }

    private function backWith(string $messageKey, array $replace = []): RedirectResponse
    {
        return back()->with('status', __($messageKey, $replace));
    }
}
