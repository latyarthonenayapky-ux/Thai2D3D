<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\ThreeDigitDrawAgentSetting;
use App\Models\User;
use App\Services\PeriodSummaryReport;
use App\Services\ThreeDigitDrawPeriodReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeriodReportController extends Controller
{
    private const DETAILS_PER_PAGE = 50;

    public function index(
        Request $request,
        PeriodSummaryReport $report,
        ThreeDigitDrawPeriodReport $threeDigitReport
    ): View {
        [$user, $adminId, $mode, $period, $date, $agentId, $roundNo] = $this->resolveSelection($request);

        $agents = Agent::query()
            ->where('admin_id', $adminId)
            ->when($user->isOperator(), fn ($query) => $mode === '3d'
                ? $query->whereHas('threeDigitDrawSettings', fn ($settings) => $settings->where('handler_id', $user->id))
                : $query->whereHas('roundSettings', fn ($settings) => $settings->where('operator_id', $user->id)))
            ->orderBy('agent_code')
            ->get();

        $reportData = $this->buildReport($report, $threeDigitReport, $user, $adminId, $mode, $period, $date, $agentId, $roundNo);

        $detailRows = $reportData['agent_rows'];
        $currentPage = Paginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $detailRows->forPage($currentPage, self::DETAILS_PER_PAGE)->values(),
            $detailRows->count(),
            self::DETAILS_PER_PAGE,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        return view($mode === '3d' ? 'admin.reports.three-digit' : 'admin.reports.summary', [
            'report' => $reportData,
            'agents' => $agents,
            'selectedAgentId' => $agentId,
            'selectedRoundNo' => $roundNo,
            'isAdmin' => $user->isAdmin(),
            'mode' => $mode,
            'detailRows' => $paginator,
        ]);
    }

    public function export(
        Request $request,
        PeriodSummaryReport $report,
        ThreeDigitDrawPeriodReport $threeDigitReport
    ): StreamedResponse {
        [$user, $adminId, $mode, $period, $date, $agentId, $roundNo] = $this->resolveSelection($request);

        $reportData = $this->buildReport($report, $threeDigitReport, $user, $adminId, $mode, $period, $date, $agentId, $roundNo);
        $rows = $reportData['agent_rows'];
        $totals = $reportData['totals'];
        $currency = $user->currencyLabel();

        $filename = sprintf(
            'thai2d3d-%s-%s-%s-%s.csv',
            $mode,
            $period,
            $reportData['start_date']->toDateString(),
            $reportData['end_date']->toDateString()
        );

        return response()->streamDownload(function () use ($rows, $totals, $mode, $currency): void {
            $output = fopen('php://output', 'w');

            // BOM so Excel opens the Burmese-capable UTF-8 CSV correctly.
            fwrite($output, "\xEF\xBB\xBF");

            if ($mode === '3d') {
                fputcsv($output, [
                    'Agent code', 'Agent name', 'Draw date', 'Winning number', 'Draw handler',
                    "Accepted stakes ({$currency})", "Winning stakes ({$currency})",
                    "Winnings ({$currency})", "Commission ({$currency})", "Business net ({$currency})",
                ]);
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $this->csvText($row['agent_code']),
                        $this->csvText($row['agent_name']),
                        $row['draw_date'],
                        $this->csvText($row['result'] ?? ''),
                        $this->csvText($row['handler_name'] ?? ''),
                        $row['total_stake'],
                        $row['winning_stake'],
                        $row['winnings'],
                        $row['commission'],
                        $row['business_net'],
                    ]);
                }
                fputcsv($output, [
                    'TOTAL', '', '', '', '',
                    $totals['total_stake'], $totals['winning_stake'],
                    $totals['winnings'], $totals['commission'], $totals['business_net'],
                ]);
            } else {
                fputcsv($output, [
                    'Agent code', 'Agent name', 'Date', 'Round', 'Round handler', '2D result',
                    "Accepted stakes ({$currency})", "Winning stakes ({$currency})",
                    "Winnings ({$currency})", "Commission ({$currency})", "Business net ({$currency})",
                ]);
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $this->csvText($row['agent_code']),
                        $this->csvText($row['agent_name']),
                        Carbon::parse($row['date'])->format('Y-m-d'),
                        $row['round_no'],
                        $this->csvText($row['operator_name'] ?? ''),
                        $this->csvText($row['result_2d'] ?? ''),
                        $row['total_stake'],
                        $row['winning_stake'],
                        $row['winnings'],
                        $row['commission'],
                        $row['business_net'],
                    ]);
                }
                fputcsv($output, [
                    'TOTAL', '', '', '', '', '',
                    $totals['total_stake'], $totals['winning_stake'],
                    $totals['winnings'], $totals['commission'], $totals['business_net'],
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: User, 1: int, 2: string, 3: string, 4: string, 5: ?int, 6: ?int}
     */
    private function resolveSelection(Request $request): array
    {
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

        return [$user, $adminId, $mode, $period, $date, $agentId, $roundNo];
    }

    private function buildReport(
        PeriodSummaryReport $report,
        ThreeDigitDrawPeriodReport $threeDigitReport,
        User $user,
        int $adminId,
        string $mode,
        string $period,
        string $date,
        ?int $agentId,
        ?int $roundNo
    ): array {
        return $mode === '3d'
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
    }

    private function csvText(?string $value): string
    {
        $value = (string) $value;

        // Spreadsheet formula guard for user-controlled text cells.
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
