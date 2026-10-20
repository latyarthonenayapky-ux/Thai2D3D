<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Help drawer
    |--------------------------------------------------------------------------
    |
    | The drawer is offered on the hosted install only: a desktop copy runs
    | offline, has no business asking a remote model anything, and its licence
    | gate already decides what the user may do. Answers are built from the
    | guides below, so only the customer-facing files are indexed.
    |
    | A model is optional. With no key the drawer quotes the matching guide
    | sections; with one it rewrites them into a short reply. The key belongs in
    | the server's .env only and is never sent to the browser.
    |
    */

    'docs' => [
        'USER_MANUAL_MM.md',
        'TROUBLESHOOTING_MM.md',
        'AMOUNT_LIMITS_MM.md',
        'AGENT_CASH_LEDGER_MM.md',
        'ROLES_AND_ACCESS_MM.md',
        'LOCALIZATION_MM.md',
        'LICENCE_MM.md',
        'round-scheduling.md',
        'mobile-agent-sales.md',
    ],

    'question_limit' => (int) env('HELP_QUESTION_LIMIT', 400),

    'context_sections' => (int) env('HELP_CONTEXT_SECTIONS', 4),

    'llm' => [

        'provider' => env('HELP_LLM_PROVIDER', 'anthropic'),

        'api_key' => env('HELP_LLM_API_KEY'),

        'model' => env('HELP_LLM_MODEL', ''),

        'base_url' => env('HELP_LLM_BASE_URL', ''),

        'timeout' => (int) env('HELP_LLM_TIMEOUT', 8),

        'max_output_tokens' => (int) env('HELP_LLM_MAX_TOKENS', 700),

        'daily_limit' => (int) env('HELP_LLM_DAILY_LIMIT', 40),

    ],

];
