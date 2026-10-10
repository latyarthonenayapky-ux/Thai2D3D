<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ThreeDigitDraw;
use App\Models\ThreeDigitDrawHotNumber;
use App\Models\ThreeDigitDrawResult;
use App\Models\ThreeDigitDrawResultChange;
use App\Services\SyncThreeDigitDraws;
use App\Services\ThreeDigitDrawSettlementCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ThreeDigitDrawController extends Controller
{
    public function index(Request $request, SyncThreeDigitDraws $drawSync): View
    {
        $request->session()->put('draw_mode', '3d');
        $this->authorizeAdmin($request);
        $drawSync->syncForAdmin($request->user(), Carbon::now('Asia/Yangon'));
        $draws = ThreeDigitDraw::query()
            ->with('hotNumbers')
            ->where('admin_id', $request->user()->id)
            ->whereDate('draw_date', '>=', Carbon::today('Asia/Yangon')->subMonth()->toDateString())
            ->orderByDesc('draw_date')
            ->get();

        foreach ($draws as $draw) {
            $draw->accepted_count = DB::table('three_digit_sale_details as d')
                ->join('three_digit_sale_inputs as i', 'i.id', '=', 'd.three_digit_sale_input_id')
                ->where('i.three_digit_draw_id', $draw->id)
                ->where('d.status', 'accepted')
                ->count();
            $draw->accepted_amount = (float) DB::table('three_digit_sale_details as d')
                ->join('three_digit_sale_inputs as i', 'i.id', '=', 'd.three_digit_sale_input_id')
                ->where('i.three_digit_draw_id', $draw->id)
                ->where('d.status', 'accepted')
                ->sum('d.amount');
        }

        return view('admin.three-digit.index', [
            'draws' => $draws,
            'agents' => Agent::query()->where('admin_id', $request->user()->id)->orderBy('agent_code')->get(),
        ]);
    }

    public function updateSettings(Request $request, ThreeDigitDraw $draw): RedirectResponse
    {
        $this->authorizeDraw($request, $draw);
        $this->ensureUpcomingDraw($draw);
        $validated = $request->validate([
            'number_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
        ]);
        $draw->update(['number_limit' => $validated['number_limit'] ?: null]);

        return back()->with('status', '3D Draw Number Limit saved.');
    }

    public function storeHotNumbers(Request $request, ThreeDigitDraw $draw): RedirectResponse
    {
        $this->authorizeDraw($request, $draw);
        $this->ensureUpcomingDraw($draw);
        $validated = $request->validate(['numbers' => ['required', 'string', 'max:20000']]);
        $numbers = preg_split('/[,\s]+/u', trim($validated['numbers']), -1, PREG_SPLIT_NO_EMPTY);
        $invalid = array_values(array_filter($numbers, fn (string $number): bool => preg_match('/\A[0-9]{3}\z/D', $number) !== 1));
        if ($numbers === [] || $invalid !== []) {
            return back()->withErrors([
                'draw_hot_numbers' => $numbers === []
                    ? 'Enter at least one three-digit Hot Number.'
                    : '3D Hot Numbers must be exactly three digits (000–999).',
            ])->withInput();
        }

        $now = now();
        ThreeDigitDrawHotNumber::query()->insertOrIgnore(
            collect($numbers)->unique()->map(fn (string $number): array => [
                'three_digit_draw_id' => $draw->id,
                'number' => $number,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        return back()->with('status', '3D Hot Numbers saved for this Draw.');
    }

    public function destroyHotNumber(Request $request, ThreeDigitDraw $draw, ThreeDigitDrawHotNumber $hotNumber): RedirectResponse
    {
        $this->authorizeDraw($request, $draw);
        $this->ensureUpcomingDraw($draw);
        abort_unless((int) $hotNumber->three_digit_draw_id === (int) $draw->id, 404);
        $hotNumber->delete();

        return back()->with('status', '3D Hot Number removed.');
    }

    public function results(Request $request): View
    {
        $request->session()->put('draw_mode', '3d');
        $this->authorizeAdmin($request);

        return view('admin.three-digit.results', [
            'draws' => ThreeDigitDraw::query()
                ->with('result')
                ->where('admin_id', $request->user()->id)
                ->where(function ($query): void {
                    $today = Carbon::today('Asia/Yangon')->toDateString();
                    $query->whereDate('draw_date', '<', $today)
                        ->orWhere(function ($query) use ($today): void {
                            $query->whereDate('draw_date', $today)
                                ->whereTime('result_time', '<=', Carbon::now('Asia/Yangon')->format('H:i:s'));
                        });
                })
                ->orderByDesc('draw_date')
                ->paginate(25),
            'draw' => null,
        ]);
    }

    public function showResult(
        Request $request,
        ThreeDigitDraw $draw,
        ThreeDigitDrawSettlementCalculator $calculator
    ): View {
        $request->session()->put('draw_mode', '3d');
        $this->authorizeDraw($request, $draw);
        abort_unless($this->drawHasClosed($draw), 409, 'The 3D result can be entered after the Draw closes at 15:30.');
        $draw->load('result.changes.changedBy');

        return view('admin.three-digit.results', [
            'draws' => collect([$draw]),
            'draw' => $draw,
            'settlement' => $calculator->calculate($draw),
        ]);
    }

    public function updateResult(Request $request, ThreeDigitDraw $draw): RedirectResponse
    {
        $request->session()->put('draw_mode', '3d');
        $this->authorizeDraw($request, $draw);
        abort_unless($this->drawHasClosed($draw), 409, 'The 3D result can be entered after the Draw closes.');
        $validated = $request->validate(['result' => ['nullable', 'regex:/\A[0-9]{3}\z/D']]);

        DB::transaction(function () use ($request, $draw, $validated): void {
            $result = ThreeDigitDrawResult::query()->firstOrCreate(
                ['three_digit_draw_id' => $draw->id, 'admin_id' => $request->user()->id],
                ['result' => null, 'updated_by' => $request->user()->id]
            );
            $result = ThreeDigitDrawResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();
            $newValue = ($validated['result'] ?? '') === '' ? null : $validated['result'];
            if ($newValue !== $result->result) {
                ThreeDigitDrawResultChange::query()->create([
                    'three_digit_draw_result_id' => $result->id,
                    'changed_by' => $request->user()->id,
                    'old_value' => $result->result,
                    'new_value' => $newValue,
                ]);
                $result->update(['result' => $newValue, 'updated_by' => $request->user()->id]);
            }
        });

        return redirect()->route('admin.three-digit.results.show', $draw)
            ->with('status', '3D result saved and this Draw settlement recalculated.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }

    private function authorizeDraw(Request $request, ThreeDigitDraw $draw): void
    {
        $this->authorizeAdmin($request);
        abort_unless((int) $draw->admin_id === (int) $request->user()->id, 404);
    }

    private function drawHasClosed(ThreeDigitDraw $draw): bool
    {
        $now = Carbon::now('Asia/Yangon');
        $closesAt = Carbon::parse($draw->draw_date->toDateString().' '.$draw->result_time, 'Asia/Yangon');

        return $now->greaterThanOrEqualTo($closesAt);
    }

    private function ensureUpcomingDraw(ThreeDigitDraw $draw): void
    {
        abort_unless(
            $draw->draw_date->toDateString() > Carbon::today('Asia/Yangon')->toDateString(),
            409,
            'Number limits and Hot Numbers can only be changed before Draw day.'
        );
    }
}
