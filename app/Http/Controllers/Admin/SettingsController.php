<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminBusinessSetting;
use App\Models\Agent;
use App\Models\CodeRule;
use App\Models\HotNumber;
use App\Models\Round;
use App\Models\RoundSchedule;
use App\Models\ThreeDigitHotNumber;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $request->session()->put('draw_mode', '2d');
        $this->authorizeBusinessAdmin($request);
        $today = Carbon::today('Asia/Yangon');
        $rounds = Round::query()
            ->with('hotNumbers')
            ->where('admin_id', $request->user()->id)
            ->where(function ($query) use ($today): void {
                $query->whereBetween('round_date', [
                    $today->toDateString(),
                    $today->copy()->addDays(7)->toDateString(),
                ])->orWhere(function ($query): void {
                    $query->where('status', 'open')
                        ->where('manual_reopen', true);
                });
            })
            ->orderBy('round_date')
            ->orderBy('round_no')
            ->get();
        $selectedRound = $rounds->firstWhere('id', (int) $request->query('round_id'))
            ?? $rounds->first();

        return view('admin.settings.index', [
            'schedules' => RoundSchedule::query()
                ->where('admin_id', $request->user()->id)
                ->orderBy('round_no')
                ->get(),
            'businessSettings' => AdminBusinessSetting::query()
                ->firstOrCreate(
                    ['admin_id' => $request->user()->id],
                    ['payout_2d_multiplier' => null, 'payout_3d_multiplier' => null]
                ),
            'agents' => Agent::query()
                ->where('admin_id', $request->user()->id)
                ->orderBy('agent_code')
                ->get(),
            'agentRoundSettings' => $selectedRound
                ? $selectedRound->agentRoundSettings()->with('operator')->get()->keyBy('agent_id')
                : collect(),
            'rounds' => $rounds,
            'selectedRound' => $selectedRound,
            'rules' => CodeRule::query()
                ->where('admin_id', $request->user()->id)
                ->whereIn('code', ['W', 'N', 'X'])
                ->orderBy('sort_order')
                ->get(),
            'customNumberLists' => CodeRule::query()
                ->where('admin_id', $request->user()->id)
                ->where('is_system', false)
                ->where('rule_type', 'number_set')
                ->whereNotIn('code', ['W', 'N', 'X'])
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $validator = Validator::make($request->all(), [
            'schedules' => ['required', 'array', 'size:3'],
            'schedules.1.start_time' => ['nullable', 'date_format:H:i:s'],
            'schedules.1.close_time' => ['required', 'date_format:H:i:s'],
            'schedules.1.number_limit_3d' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'schedules.2.start_time' => ['nullable', 'date_format:H:i:s'],
            'schedules.2.close_time' => ['required', 'date_format:H:i:s'],
            'schedules.2.number_limit_3d' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'schedules.3.start_time' => ['nullable', 'date_format:H:i:s'],
            'schedules.3.close_time' => ['required', 'date_format:H:i:s'],
            'schedules.3.number_limit_3d' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $schedules = $request->input('schedules', []);
            $previousClose = null;

            foreach ([1, 2, 3] as $roundNo) {
                $times = $schedules[$roundNo] ?? [];
                $start = $this->secondsSinceMidnight($times['start_time'] ?? null);
                $close = $this->secondsSinceMidnight($times['close_time'] ?? null);

                if ($close === null) {
                    $previousClose = null;

                    continue;
                }

                if ($start !== null && $start >= $close) {
                    $validator->errors()->add(
                        "schedules.{$roundNo}.close_time",
                        'The close time must be later than the start time.'
                    );
                }

                if ($previousClose !== null && $close <= $previousClose) {
                    $validator->errors()->add(
                        "schedules.{$roundNo}.close_time",
                        'Rounds must be ordered and cannot overlap.'
                    );
                }

                if ($start !== null && $previousClose !== null && $start < $previousClose) {
                    $validator->errors()->add(
                        "schedules.{$roundNo}.start_time",
                        'A start time cannot be earlier than the previous Round close time.'
                    );
                }

                $previousClose = $close;
            }
        });

        $validated = $validator->validate();

        DB::transaction(function () use ($validated, $request): void {
            foreach ([1, 2, 3] as $roundNo) {
                RoundSchedule::query()
                    ->where('admin_id', $request->user()->id)
                    ->where('round_no', $roundNo)
                    ->update([
                        'start_time' => $validated['schedules'][$roundNo]['start_time'],
                        'close_time' => $validated['schedules'][$roundNo]['close_time'],
                        'number_limit_3d' => $validated['schedules'][$roundNo]['number_limit_3d'] ?? null,
                        'updated_at' => now(),
                    ]);
            }

            $today = Carbon::today('Asia/Yangon');
            for ($offset = 0; $offset <= 7; $offset++) {
                $date = $today->copy()->addDays($offset);
                foreach (RoundSchedule::query()->where('admin_id', $request->user()->id)->orderBy('round_no')->get() as $schedule) {
                    $this->syncUnopenedRound($date, $schedule);
                }
            }

            $this->applyScheduleToFutureRounds($today, $request->user()->id);
        });

        return redirect()->route('admin.settings.index')
            ->with('status', 'Round schedule saved. Opened rounds were left unchanged.');
    }

    public function updatePayoutSettings(Request $request): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $validated = $request->validate([
            'payout_2d_multiplier' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999.99'],
            'payout_3d_multiplier' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);
        abort_if($validated === [], 422, 'Submit at least one payout multiplier.');

        AdminBusinessSetting::query()->firstOrCreate(
            ['admin_id' => $request->user()->id],
            ['payout_2d_multiplier' => null, 'payout_3d_multiplier' => null]
        )->fill($validated)->save();

        $mode = array_key_exists('payout_3d_multiplier', $validated) ? '3D' : '2D';

        return back()->with('status', "{$mode} payout multiplier saved in business settings.");
    }

    public function updateAgentThreeDigitCommission(Request $request, Agent $agent): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        abort_unless((int) $agent->admin_id === (int) $request->user()->id, 404);
        $validated = $request->validate([
            'commission_3d_percent' => ['required', 'numeric', 'between:0,100'],
        ]);
        $agent->update($validated);

        return back()->with('status', "3D commission saved for {$agent->agent_code}.");
    }

    public function storeAgent(Request $request): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $validated = $request->validate([
            'agent_code' => ['required', 'string', 'max:255', 'unique:agents,agent_code'],
            'agent_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'commission_2d_percent' => ['required', 'numeric', 'between:0,100'],
            'commission_3d_percent' => ['required', 'numeric', 'between:0,100'],
        ]);

        Agent::query()->create($validated + [
            'admin_id' => $request->user()->id,
            'status' => 'active',
        ]);

        return back()->with('status', 'Agent created. An Operator claims it by opening it for a Round.');
    }

    public function updateAgent(Request $request, Agent $agent): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        abort_unless($agent->admin_id === $request->user()->id, 404);
        $validated = $request->validate([
            'agent_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('agents', 'agent_code')->ignore($agent->id),
            ],
            'agent_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'commission_2d_percent' => ['required', 'numeric', 'between:0,100'],
            'commission_3d_percent' => ['required', 'numeric', 'between:0,100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $agent->update($validated);

        return back()->with('status', 'Agent settings saved.');
    }

    public function updateAgentRoundAmountLimit(Request $request, Round $round, Agent $agent): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $this->authorizeRound($request, $round);
        abort_unless($agent->admin_id === $request->user()->id, 404);

        $validated = $request->validate([
            'amount_limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);
        $amountLimit = $validated['amount_limit'] ?? null;
        $setting = $round->agentRoundSettings()->where('agent_id', $agent->id)->first();

        if ($amountLimit === null || $amountLimit === '') {
            if ($setting?->operator_id !== null) {
                $setting->update(['amount_limit' => null]);
            } else {
                $setting?->delete();
            }
        } else {
            $round->agentRoundSettings()->updateOrCreate(
                ['agent_id' => $agent->id],
                ['amount_limit' => $amountLimit]
            );
        }

        return back()
            ->with('status', "Amount Limit saved for {$agent->agent_name}. This threshold monitors sales only and does not reject them.");
    }

    public function updateAgentRoundAmountLimit3d(Request $request, Round $round, Agent $agent): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $this->authorizeRound($request, $round);
        abort_unless($agent->admin_id === $request->user()->id, 404);

        $validated = $request->validate([
            'amount_limit_3d' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);
        $amountLimit = $validated['amount_limit_3d'] ?? null;
        $setting = $round->agentRoundSettings()->where('agent_id', $agent->id)->first();

        if ($amountLimit === null || $amountLimit === '') {
            $setting?->update(['amount_limit_3d' => null]);
            if ($setting && $setting->operator_id === null && $setting->amount_limit === null) {
                $setting->delete();
            }
        } else {
            $round->agentRoundSettings()->updateOrCreate(
                ['agent_id' => $agent->id],
                ['amount_limit_3d' => $amountLimit]
            );
        }

        return redirect()->route('admin.settings.index', ['round_id' => $round->id])
            ->with('status', "3D Amount Limit saved for {$agent->agent_name}. This threshold monitors sales only and does not reject them.");
    }

    public function storeHotNumbers(Request $request, Round $round): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $this->authorizeRound($request, $round);
        $validated = $request->validate([
            'numbers' => ['required', 'string', 'max:20000'],
        ]);

        $numbers = preg_split('/[,\s]+/u', trim($validated['numbers']), -1, PREG_SPLIT_NO_EMPTY);
        if ($numbers === []) {
            return back()->withErrors(['numbers' => 'Enter at least one two-digit Hot Number.'])->withInput();
        }

        $invalid = array_values(array_filter($numbers, fn (string $number): bool => ! preg_match('/\A[0-9]{2}\z/D', $number)));

        if ($invalid !== []) {
            return back()->withErrors([
                'numbers' => 'Each Hot Number must be exactly two digits (00–99). Invalid: '.implode(', ', array_unique($invalid)),
            ])->withInput();
        }

        $now = now();
        $rows = collect($numbers)->unique()->map(fn (string $number): array => [
            'round_id' => $round->id,
            'number' => $number,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        $inserted = DB::table('hot_numbers')->insertOrIgnore($rows);

        return back()
            ->with('status', "{$inserted} Hot Number(s) added; duplicates were skipped.");
    }

    public function destroyHotNumber(Request $request, Round $round, HotNumber $hotNumber): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $this->authorizeRound($request, $round);
        abort_unless($hotNumber->round_id === $round->id, 404);
        $hotNumber->delete();

        return back()
            ->with('status', 'Hot Number removed.');
    }

    public function storeThreeDigitHotNumbers(Request $request, Round $round): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $this->authorizeRound($request, $round);
        $validated = $request->validate([
            'numbers' => ['required', 'string', 'max:20000'],
        ]);
        $numbers = preg_split('/[,\s]+/u', trim($validated['numbers']), -1, PREG_SPLIT_NO_EMPTY);
        $invalid = array_values(array_filter($numbers, fn (string $number): bool => ! preg_match('/\A[0-9]{3}\z/D', $number)));
        if ($numbers === [] || $invalid !== []) {
            return back()->withErrors([
                'numbers_3d' => $numbers === []
                    ? 'Enter at least one three-digit Hot Number.'
                    : 'Each 3D Hot Number must be exactly three digits (000–999). Invalid: '.implode(', ', array_unique($invalid)),
            ])->withInput();
        }

        $now = now();
        $rows = collect($numbers)->unique()->map(fn (string $number): array => [
            'round_id' => $round->id,
            'number' => $number,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        $inserted = DB::table('three_digit_hot_numbers')->insertOrIgnore($rows);

        return redirect()->route('admin.settings.index', ['round_id' => $round->id])
            ->with('status', "{$inserted} 3D Hot Number(s) added; duplicates were skipped.");
    }

    public function destroyThreeDigitHotNumber(Request $request, Round $round, ThreeDigitHotNumber $hotNumber): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $this->authorizeRound($request, $round);
        abort_unless($hotNumber->round_id === $round->id, 404);
        $hotNumber->delete();

        return redirect()->route('admin.settings.index', ['round_id' => $round->id])
            ->with('status', '3D Hot Number removed.');
    }

    public function updateConfiguredRule(Request $request, CodeRule $codeRule): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        abort_unless($codeRule->admin_id === $request->user()->id, 404);
        abort_unless(in_array($codeRule->code, ['W', 'N', 'X'], true) || ($this->isCustomNumberList($codeRule) && ! $codeRule->is_system), 404);

        $validated = $request->validate([
            'numbers' => ['nullable', 'string', 'max:20000'],
            'is_active' => ['required', Rule::in(['0', '1', 0, 1, true, false])],
        ]);
        $numbers = preg_split('/[,\s]+/u', trim($validated['numbers'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $invalid = array_values(array_filter($numbers, fn (string $number): bool => ! preg_match('/\A[0-9]{2}\z/D', $number)));

        if ($invalid !== []) {
            return back()->withErrors([
                "rules.{$codeRule->code}" => 'Rule values must each be exactly two digits (00–99).',
            ])->withInput();
        }

        $numbers = array_values(array_unique($numbers));
        if ((bool) $validated['is_active'] && $numbers === []) {
            return back()->withErrors([
                "rules.{$codeRule->code}" => 'An active rule must contain at least one number.',
            ])->withInput();
        }

        $codeRule->update([
            'rule_config' => ['numbers' => $numbers],
            'is_active' => (bool) $validated['is_active'],
        ]);

        return back()->with('status', "{$codeRule->code} rule saved.");
    }

    public function storeCustomNumberList(Request $request): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $reservedCodes = ['A', 'B', 'F', 'N', 'P', 'R', 'W', 'X'];
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'size:1',
                'regex:/\A[A-Z]\z/',
                Rule::notIn($reservedCodes),
                Rule::unique('code_rules', 'code')->where('admin_id', $request->user()->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'numbers' => ['required', 'string', 'max:20000'],
        ]);
        $numbers = preg_split('/[,\s]+/u', trim($validated['numbers']), -1, PREG_SPLIT_NO_EMPTY);
        $invalid = array_values(array_filter($numbers, fn (string $number): bool => ! preg_match('/\A[0-9]{2}\z/D', $number)));

        if ($invalid !== []) {
            return back()->withErrors([
                'custom_numbers' => 'Custom list values must each be exactly two digits (00–99).',
            ])->withInput();
        }

        $numbers = array_values(array_unique($numbers));
        if ($numbers === []) {
            return back()->withErrors([
                'custom_numbers' => 'Enter at least one two-digit number.',
            ])->withInput();
        }

        CodeRule::query()->create([
            'admin_id' => $request->user()->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => 'Admin-managed custom number list',
            'rule_type' => 'number_set',
            'rule_config' => ['numbers' => $numbers],
            'allow_bracket' => true,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 1000,
        ]);

        return back()->with('status', "Custom number list {$validated['code']} created. Use {$validated['code']}<amount> in sale input.");
    }

    public function destroyCustomNumberList(Request $request, CodeRule $codeRule): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        abort_unless($codeRule->admin_id === $request->user()->id && $this->isCustomNumberList($codeRule) && ! $codeRule->is_system, 404);
        $codeRule->delete();

        return back()->with('status', "Custom number list {$codeRule->code} removed.");
    }

    public function reopenRound(Round $round): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless($round->admin_id === auth()->id(), 404);
        abort_if($round->status === 'open', 409, 'This round is already open.');

        DB::transaction(function () use ($round): void {
            $lockedRound = Round::query()->lockForUpdate()->findOrFail($round->id);
            abort_if($lockedRound->status === 'open', 409, 'This round is already open.');

            $lockedRound->forceFill([
                'status' => 'open',
                'opened_at' => now('Asia/Yangon'),
                'manual_reopen' => true,
                'manually_closed' => false,
            ])->save();
            $lockedRound->sessions()
                ->where('status', 'closed')
                ->update([
                    'status' => 'open',
                    'closed_at' => null,
                    'updated_at' => now(),
                ]);
        });

        return back()->with('status', "Round {$round->round_no} reopened manually. It will remain open until the next Round opens.");
    }

    public function closeRound(Round $round): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless((int) $round->admin_id === (int) auth()->id(), 404);

        DB::transaction(function () use ($round): void {
            $lockedRound = Round::query()->lockForUpdate()->findOrFail($round->id);
            abort_unless((int) $lockedRound->admin_id === (int) auth()->id(), 404);
            abort_if($lockedRound->status !== 'open', 409, 'This Round is already closed.');

            $closedAt = now('Asia/Yangon');
            $lockedRound->forceFill([
                'status' => 'closed',
                'manual_reopen' => false,
                'manually_closed' => true,
            ])->save();
            $lockedRound->sessions()
                ->where('status', 'open')
                ->update([
                    'status' => 'closed',
                    'closed_at' => $closedAt,
                    'updated_at' => $closedAt,
                ]);
        });

        return back()->with('status', "Round {$round->round_no} closed manually. Its Agent sessions are now read-only.");
    }

    public function updateRoundNumberLimits(Request $request, Round $round): RedirectResponse
    {
        $this->authorizeBusinessAdmin($request);
        $this->authorizeRound($request, $round);

        $validated = $request->validate([
            'numbers' => ['nullable', 'string', 'max:20000'],
        ]);
        $rawNumbers = trim((string) ($validated['numbers'] ?? ''));
        $numbers = $rawNumbers === ''
            ? []
            : preg_split('/[,\s]+/u', $rawNumbers, -1, PREG_SPLIT_NO_EMPTY);
        $invalid = array_values(array_filter(
            $numbers,
            fn (string $number): bool => ! preg_match('/\A[0-9]{2}\z/D', $number)
        ));

        if ($invalid !== []) {
            return back()->withErrors([
                'numbers' => 'Each Number Limit value must be exactly two digits (00–99). Invalid: '.implode(', ', array_unique($invalid)),
            ])->withInput();
        }

        $round->update(['number_limits' => array_values(array_unique($numbers))]);

        return back()->with('status', 'Round Number Limit saved. Listed 00–99 values are blocked from 2D sales.');
    }

    private function syncUnopenedRound(Carbon $date, RoundSchedule $schedule): void
    {
        if ($schedule->close_time === null) {
            return;
        }

        $round = Round::query()
            ->where('admin_id', $schedule->admin_id)
            ->whereDate('round_date', $date->toDateString())
            ->where('round_no', $schedule->round_no)
            ->first();

        if ($round && (
            $round->status === 'open'
            || $round->opened_at !== null
            || $round->sessions()->exists()
        )) {
            return;
        }

        $attributes = [
            'start_time' => $schedule->start_time,
            'close_time' => $schedule->close_time,
            'number_limit_3d' => $schedule->number_limit_3d,
            'status' => 'closed',
            'manual_reopen' => false,
        ];

        if ($round) {
            $round->update($attributes);

            return;
        }

        Round::query()->create($attributes + [
            'admin_id' => $schedule->admin_id,
            'round_date' => $date->toDateString(),
            'round_no' => $schedule->round_no,
        ]);
    }

    private function applyScheduleToFutureRounds(Carbon $today, int $adminId): void
    {
        $schedules = RoundSchedule::query()->where('admin_id', $adminId)->get()->keyBy('round_no');
        $rounds = Round::query()
            ->where('admin_id', $adminId)
            ->whereDate('round_date', '>', $today->toDateString())
            ->where('status', 'closed')
            ->whereNull('opened_at')
            ->whereDoesntHave('sessions')
            ->get();

        foreach ($rounds as $round) {
            $schedule = $schedules->get($round->round_no);
            if (! $schedule) {
                continue;
            }

            $round->update([
                'start_time' => $schedule->start_time,
                'close_time' => $schedule->close_time,
                'number_limit_3d' => $schedule->number_limit_3d,
            ]);
        }
    }

    private function secondsSinceMidnight(?string $time): ?int
    {
        if ($time === null || ! preg_match('/\A\d{2}:\d{2}:\d{2}\z/D', $time)) {
            return null;
        }

        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    private function isCustomNumberList(CodeRule $codeRule): bool
    {
        return preg_match('/\A[A-Z]\z/', $codeRule->code) === 1
            && ! in_array($codeRule->code, ['A', 'B', 'F', 'N', 'P', 'R', 'W', 'X'], true)
            && $codeRule->rule_type === 'number_set';
    }

    private function authorizeBusinessAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function authorizeRound(Request $request, Round $round): void
    {
        abort_unless($round->admin_id === $request->user()->id, 404);
    }
}
