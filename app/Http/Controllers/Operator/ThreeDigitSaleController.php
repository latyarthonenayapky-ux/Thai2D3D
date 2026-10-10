<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ThreeDigitDraw;
use App\Models\ThreeDigitDrawAgentSetting;
use App\Models\ThreeDigitSaleDetail;
use App\Models\ThreeDigitSaleInput;
use App\Services\SyncThreeDigitDraws;
use App\Services\ThreeDigitSaleProcessor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class ThreeDigitSaleController extends Controller
{
    public function workspace(Request $request, SyncThreeDigitDraws $drawSync): View|RedirectResponse
    {
        $request->session()->put('draw_mode', '3d');
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);
        $agentStatement = $request->query('view') === 'agent';
        $roundWide = $user->isAdmin() && ! $agentStatement;
        $adminId = $user->isAdmin() ? $user->id : $user->admin_id;
        abort_if($adminId === null, 403);
        $admin = $user->isAdmin() ? $user : $user->admin;
        $now = Carbon::now('Asia/Yangon');
        $drawSync->syncForAdmin($admin, $now);

        $draw = ThreeDigitDraw::query()
            ->where('admin_id', $adminId)
            ->whereDate('draw_date', $now->toDateString())
            ->first();
        $nextDraw = ThreeDigitDraw::query()
            ->where('admin_id', $adminId)
            ->whereDate('draw_date', '>', $now->toDateString())
            ->orderBy('draw_date')
            ->first();
        $agents = Agent::query()
            ->where('admin_id', $adminId)
            ->where('status', 'active')
            ->orderBy('agent_code')
            ->get();

        if (! $draw || $draw->status !== 'open') {
            return view('operator.sales.three-digit', [
                'draw' => $draw,
                'nextDraw' => $nextDraw,
                'agents' => $agents,
                'assignments' => collect(),
                'selectedAgent' => null,
                'assignment' => null,
                'saleInputs' => collect(),
                'soldItems' => [],
                'acceptedCount' => 0,
                'acceptedAmount' => 0.0,
                'rejectedCount' => 0,
                'canEnterSales' => false,
                'agentStatement' => $agentStatement,
                'roundWide' => $roundWide,
            ]);
        }

        $assignments = ThreeDigitDrawAgentSetting::query()
            ->with('handler')
            ->where('three_digit_draw_id', $draw->id)
            ->get()
            ->keyBy('agent_id');
        $selectedAgent = null;
        $assignment = null;
        $saleInputs = collect();
        $soldItems = [];
        $acceptedCount = 0;
        $acceptedAmount = 0.0;
        $rejectedCount = 0;

        if ($request->filled('agent_id')) {
            $selectedAgent = $agents->firstWhere('id', (int) $request->query('agent_id'));
            abort_unless($selectedAgent, 404);
            $assignment = $assignments->get($selectedAgent->id);
            if ($assignment && (int) $assignment->handler_id === (int) $user->id) {
                $saleInputs = ThreeDigitSaleInput::query()
                    ->with('details')
                    ->where('three_digit_draw_id', $draw->id)
                    ->where('agent_id', $selectedAgent->id)
                    ->latest()
                    ->limit(50)
                    ->get();
                $stats = ThreeDigitSaleDetail::query()
                    ->whereHas('saleInput', fn ($query) => $query
                        ->where('three_digit_draw_id', $draw->id)
                        ->where('agent_id', $selectedAgent->id))
                    ->selectRaw("SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                        SUM(CASE WHEN status = 'accepted' THEN amount ELSE 0 END) as accepted_amount,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
                    ->first();
                $acceptedCount = (int) ($stats?->accepted_count ?? 0);
                $acceptedAmount = (float) ($stats?->accepted_amount ?? 0);
                $rejectedCount = (int) ($stats?->rejected_count ?? 0);
                $soldItems = ThreeDigitSaleDetail::query()
                    ->join('three_digit_sale_inputs', 'three_digit_sale_inputs.id', '=', 'three_digit_sale_details.three_digit_sale_input_id')
                    ->where('three_digit_sale_inputs.three_digit_draw_id', $draw->id)
                    ->where('three_digit_sale_inputs.agent_id', $selectedAgent->id)
                    ->where('three_digit_sale_details.status', 'accepted')
                    ->select('three_digit_sale_details.number')
                    ->selectRaw('SUM(three_digit_sale_details.amount) as amount')
                    ->groupBy('three_digit_sale_details.number')
                    ->orderByDesc('amount')
                    ->get()
                    ->map(fn ($row): array => ['number' => $row->number, 'amount' => (float) $row->amount])
                    ->all();
            }
        }

        if ($roundWide) {
            $stats = ThreeDigitSaleDetail::query()
                ->whereHas('saleInput', fn ($query) => $query->where('three_digit_draw_id', $draw->id))
                ->selectRaw("SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                    SUM(CASE WHEN status = 'accepted' THEN amount ELSE 0 END) as accepted_amount,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
                ->first();
            $acceptedCount = (int) ($stats?->accepted_count ?? 0);
            $acceptedAmount = (float) ($stats?->accepted_amount ?? 0);
            $rejectedCount = (int) ($stats?->rejected_count ?? 0);
            $soldItems = ThreeDigitSaleDetail::query()
                ->join('three_digit_sale_inputs', 'three_digit_sale_inputs.id', '=', 'three_digit_sale_details.three_digit_sale_input_id')
                ->where('three_digit_sale_inputs.three_digit_draw_id', $draw->id)
                ->where('three_digit_sale_details.status', 'accepted')
                ->select('three_digit_sale_details.number')
                ->selectRaw('SUM(three_digit_sale_details.amount) as amount')
                ->groupBy('three_digit_sale_details.number')
                ->orderByDesc('amount')
                ->orderBy('three_digit_sale_details.number')
                ->get()
                ->map(fn ($row): array => ['number' => $row->number, 'amount' => (float) $row->amount])
                ->all();
        }

        return view('operator.sales.three-digit', [
            'draw' => $draw,
            'nextDraw' => $nextDraw,
            'agents' => $agents,
            'assignments' => $assignments,
            'selectedAgent' => $selectedAgent,
            'assignment' => $assignment,
            'saleInputs' => $saleInputs,
            'soldItems' => $soldItems,
            'acceptedCount' => $acceptedCount,
            'acceptedAmount' => $acceptedAmount,
            'rejectedCount' => $rejectedCount,
            'agentStatement' => $agentStatement,
            'roundWide' => $roundWide,
            'canEnterSales' => $selectedAgent !== null
                && $assignment !== null
                && (int) $assignment->handler_id === (int) $user->id,
        ]);
    }

    public function claim(Request $request, ThreeDigitDraw $draw, Agent $agent): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);
        $adminId = $user->isAdmin() ? $user->id : $user->admin_id;
        abort_unless((int) $draw->admin_id === (int) $adminId, 404);
        abort_unless((int) $agent->admin_id === (int) $adminId && $agent->status === 'active', 404);
        abort_unless($draw->status === 'open' && $this->drawIsOpenNow($draw), 409, 'This 3D Draw is not open.');

        DB::transaction(function () use ($draw, $agent, $user): void {
            ThreeDigitDraw::query()->whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $setting = ThreeDigitDrawAgentSetting::query()
                ->where('three_digit_draw_id', $draw->id)
                ->where('agent_id', $agent->id)
                ->lockForUpdate()
                ->first();
            if ($setting && $setting->handler_id !== null && (int) $setting->handler_id !== (int) $user->id) {
                throw ValidationException::withMessages(['agent' => 'This Agent is already assigned to another account for this 3D Draw.']);
            }
            if ($setting === null) {
                ThreeDigitDrawAgentSetting::query()->create([
                    'three_digit_draw_id' => $draw->id,
                    'agent_id' => $agent->id,
                    'handler_id' => $user->id,
                ]);
            } elseif ($setting->handler_id === null) {
                $setting->update(['handler_id' => $user->id]);
            }
        });

        return redirect()->route('three-digit.sales.workspace', ['agent_id' => $agent->id])
            ->with('status', "{$agent->agent_code} is assigned to you for this 3D Draw.");
    }

    public function store(
        Request $request,
        ThreeDigitDraw $draw,
        Agent $agent,
        ThreeDigitSaleProcessor $processor
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);
        $adminId = $user->isAdmin() ? $user->id : $user->admin_id;
        abort_unless((int) $draw->admin_id === (int) $adminId, 404);
        abort_unless((int) $agent->admin_id === (int) $adminId, 404);
        $validated = $request->validate([
            'input' => ['required', 'string', 'max:255'],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        try {
            $saleInput = $processor->processForDraw(
                $validated['input'],
                $draw,
                $agent,
                $user,
                $validated['client_uuid'] ?? null
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['input' => $exception->getMessage()]);
        }

        $details = $saleInput->details()->get(['number', 'amount', 'status', 'reject_reason']);
        $message = sprintf(
            '%s · %d accepted, %d rejected.',
            ucfirst($saleInput->status),
            $details->where('status', 'accepted')->count(),
            $details->where('status', 'rejected')->count()
        );
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $saleInput->status,
                'message' => $message,
                'details' => $details,
            ]);
        }

        return back()->with('status', $message);
    }

    private function drawIsOpenNow(ThreeDigitDraw $draw): bool
    {
        $now = Carbon::now('Asia/Yangon');

        return $now->toDateString() === $draw->draw_date->toDateString()
            && $now->format('H:i:s') >= $draw->open_time
            && $now->format('H:i:s') < $draw->result_time;
    }
}
