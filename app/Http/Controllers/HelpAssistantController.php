<?php

namespace App\Http\Controllers;

use App\Services\Assistant\HelpAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The help drawer's single endpoint, hosted installs only.
 *
 * Agents and operators ask in their own words, so the question is capped and
 * throttled rather than trusted; nothing here writes to the application, which
 * keeps it available to a read-only account on the server as well.
 */
class HelpAssistantController extends Controller
{
    public function __invoke(Request $request, HelpAssistant $assistant): JsonResponse
    {
        abort_if(config('app.desktop_mode'), 404);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:'.config('help.question_limit', 400)],
            'page' => ['nullable', 'string', 'max:120'],
        ]);

        $reply = $assistant->answer(
            str_replace(["\r", "\n"], ' ', $validated['question']),
            $validated['page'] ?? '',
            (int) $request->user()?->getAuthIdentifier(),
        );

        return response()->json([
            'source' => $reply['source'],
            'answer' => $reply['answer'],
            'references' => $reply['references'],
        ]);
    }
}
