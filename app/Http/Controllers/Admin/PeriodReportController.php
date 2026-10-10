<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\ThreeDigitDrawAgentSetting;
use App\Services\PeriodSummaryReport;
use App\Services\ThreeDigitDrawPeriodReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PeriodReportController extends Controller
{
    public function index(
        Request $request,
        PeriodSummaryReport $report,
        ThreeDigitDrawPeriodReport $threeDigitReport
    ): View {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);
        abort_if($user->admin_id === null && $user->isOperator(), 403);
        $adminId = $user->isAdmin() ? (int) $user->id : (int) $user->admin_id;

        $today = Carbon::today('Asia/Yangon')->toDateString();
        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['2d', '3d'])],
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'agent_id' => [
                'nullable',
                'integer',
                Rule::exists('agents', 'id')->where('admin_id', $adminId),
            ],
            'round_no' => ['nullable', Rule::in(['1', '2', '3', 1, 2, 3])],
        ]);
        $mode = $validated['mode'] ?? $request->session()->get('draw_mode', '2d');
        $request->session()->put('draw_mode', $mode);
        $period = $validated['period'] ?? 'daily';
        $date = $validated['date'] ?? $today;
        $agentId = isset($validated['agent_id']) ? (int) $validated['agent_id'] : null;
        $roundNo = isset($validated['round_no']) ? (int) $validated['round_no'] : null;

        if ($agentId !== null && $user->isOperator()) {
            $authorized = $mode === '3d'
                ? ThreeDigitDrawAgentSetting::query()
                    ->where('agent_id', $agentId)
                    ->where('handler_id', $user->id)
                    ->exists()
                : AgentRoundSetting::query()
                    ->where('agent_id', $agentId)
                    ->where('operator_id', $user->id)
                    ->exists();
            abort_unless($authorized, 404);
        }

        $agents = Agent::query()
            ->where('admin_id', $adminId)
            ->when($user->isOperator(), fn ($query) => $mode === '3d'
                ? $query->whereHas('threeDigitDrawSettings', fn ($settings) => $settings->where('handler_id', $user->id))
                : $query->whereHas('roundSettings', fn ($settings) => $settings->where('operator_id', $user->id)))
            ->orderBy('agent_code')
            ->get();

        $reportData = $mode === '3d'
            ? $threeDigitReport->build(
                $adminId,
                $period,
                $date,
                $user->isOperator() ? (int) $user->id : null,
                $agentId
            )
            : $report->build(
                $adminId,
                $period,
                $date,
                $user->isOperator() ? (int) $user->id : null,
                $agentId,
                $roundNo
            );

        return view($mode === '3d' ? 'admin.reports.three-digit' : 'admin.reports.summary', [
            'report' => $reportData,
            'agents' => $agents,
            'selectedAgentId' => $agentId,
            'selectedRoundNo' => $roundNo,
            'isAdmin' => $user->isAdmin(),
            'mode' => $mode,
        ]);
    }
}
