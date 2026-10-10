<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DrawModeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isOperator(), 403);

        return view('draw-mode', [
            'selectedMode' => $request->session()->get('draw_mode', '2d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isOperator(), 403);
        $validated = $request->validate([
            'mode' => ['required', 'in:2d,3d'],
        ]);
        $request->session()->put('draw_mode', $validated['mode']);

        return redirect()->route($validated['mode'] === '3d'
            ? 'three-digit.sales.workspace'
            : 'operator.sales.workspace');
    }

    public function switch(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isOperator(), 403);
        $validated = $request->validate([
            'mode' => ['required', 'in:2d,3d'],
        ]);
        $request->session()->put('draw_mode', $validated['mode']);

        return redirect()->route($validated['mode'] === '3d'
            ? 'three-digit.sales.workspace'
            : 'operator.sales.workspace');
    }
}
