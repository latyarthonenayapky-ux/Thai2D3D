<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\AgentSession;
use App\Models\SaleInput;
use App\Models\ThreeDigitSaleInput;
use App\Services\SaleVoidService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Corrections to saved sales. A void keeps the row for the audit trail and
 * removes it from every accepted total; only an Admin (or the account that
 * voided it, while the Round is still open) may put it back.
 */
class SaleVoidController extends Controller
{
    public function __construct(protected SaleVoidService $voider) {}

    public function void(Request $request, AgentSession $agentSession, SaleInput $saleInput): RedirectResponse
    {
        $this->authorizeSale($request, $agentSession, $saleInput);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $this->voider->void($saleInput, $request->user(), $validated['reason']);

        return redirect()
            ->route('operator.sales.show', [$agentSession, 'mode' => '2d'])
            ->with('status', "Saved sale {$saleInput->original_input} was voided.");
    }

    public function restore(Request $request, AgentSession $agentSession, SaleInput $saleInput): RedirectResponse
    {
        $this->authorizeRestore($request, $agentSession, $saleInput);

        $this->voider->restore($saleInput, $request->user());

        return redirect()
            ->route('operator.sales.show', [$agentSession, 'mode' => '2d'])
            ->with('status', "Voided sale {$saleInput->original_input} was restored.");
    }

    public function voidThreeDigit(Request $request, AgentSession $agentSession, ThreeDigitSaleInput $saleInput): RedirectResponse
    {
        $this->authorizeSale($request, $agentSession, $saleInput);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $this->voider->void($saleInput, $request->user(), $validated['reason']);

        return redirect()
            ->route('operator.sales.show', [$agentSession, 'mode' => '3d'])
            ->with('status', "Saved sale {$saleInput->original_input} was voided.");
    }

    public function restoreThreeDigit(Request $request, AgentSession $agentSession, ThreeDigitSaleInput $saleInput): RedirectResponse
    {
        $this->authorizeRestore($request, $agentSession, $saleInput);

        $this->voider->restore($saleInput, $request->user());

        return redirect()
            ->route('operator.sales.show', [$agentSession, 'mode' => '3d'])
            ->with('status', "Voided sale {$saleInput->original_input} was restored.");
    }

    private function authorizeSale(Request $request, AgentSession $agentSession, Model $sale): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);

        $agentSession->loadMissing(['round', 'agent']);
        $adminId = (int) ($user->isAdmin() ? $user->id : $user->admin_id);
        abort_unless(
            (int) $sale->agent_session_id === (int) $agentSession->id
            && (int) $agentSession->round->admin_id === (int) $adminId
            && (int) $agentSession->agent->admin_id === (int) $adminId,
            404
        );

        if ($user->isOperator()) {
            abort_unless(
                (int) $agentSession->operator_id === (int) $user->id
                && $agentSession->round->status === 'open',
                403,
                'This Round is closed. Ask your Admin to correct the entry.'
            );
        }
    }

    private function authorizeRestore(Request $request, AgentSession $agentSession, Model $sale): void
    {
        $this->authorizeSale($request, $agentSession, $sale);

        $user = $request->user();
        abort_unless($sale->status === 'voided', 404);

        if ($user->isOperator()) {
            abort_unless((int) $user->id === (int) $sale->voided_by, 403);
        }
    }
}
