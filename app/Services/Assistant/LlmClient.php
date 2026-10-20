<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin chat-completion client for the help drawer.
 *
 * Deliberately silent on failure: the drawer always has the guide excerpts to
 * show, so a missing key, a timeout or a bad response must never surface as an
 * error page or leak the configured endpoint.
 */
final class LlmClient
{
    public function configured(): bool
    {
        return $this->provider() !== null
            && (string) config('help.llm.api_key', '') !== ''
            && (string) config('help.llm.model', '') !== '';
    }

    public function ask(string $system, string $prompt): ?string
    {
        $provider = $this->provider();

        if ($provider === null) {
            return null;
        }

        $maxTokens = max(120, (int) config('help.llm.max_output_tokens', 700));

        try {
            $request = Http::timeout(max(2, (int) config('help.llm.timeout', 8)))
                ->acceptJson();

            $response = $provider === 'anthropic'
                ? $request->withHeaders(['x-api-key' => (string) config('help.llm.api_key'), 'anthropic-version' => '2023-06-01'])
                    ->post($this->endpoint('/v1/messages'), [
                        'model' => (string) config('help.llm.model'),
                        'max_tokens' => $maxTokens,
                        'system' => $system,
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                    ])
                : $request->withToken((string) config('help.llm.api_key'))
                    ->post($this->endpoint('/chat/completions'), [
                        'model' => (string) config('help.llm.model'),
                        'max_tokens' => $maxTokens,
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                    ]);

            if ($response->failed()) {
                Log::warning('Help assistant model call rejected', ['provider' => $provider, 'status' => $response->status()]);

                return null;
            }

            return $this->text($provider, $response->json() ?? []) ?? null;
        } catch (\Throwable $exception) {
            Log::warning('Help assistant model call failed', ['provider' => $provider, 'reason' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function text(string $provider, array $payload): ?string
    {
        if ($provider === 'anthropic') {
            $parts = $payload['content'] ?? [];
            $text = '';

            foreach (is_array($parts) ? $parts : [] as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'text') {
                    $text .= ($part['text'] ?? '');
                }
            }

            return $this->clean($text);
        }

        $content = $payload['choices'][0]['message']['content'] ?? '';

        return $this->clean(is_array($content) ? '' : (string) $content);
    }

    private function clean(string $text): ?string
    {
        $text = trim(preg_replace('/[ \t]{2,}/', ' ', $text) ?? '');

        return $text === '' ? null : $text;
    }

    private function endpoint(string $path): string
    {
        $provider = $this->provider() ?? 'anthropic';
        $base = (string) config('help.llm.base_url', '');
        $base = $base !== '' ? $base : (
            $provider === 'anthropic' ? 'https://api.anthropic.com' : 'https://api.openai.com/v1'
        );

        return rtrim($base, '/').$path;
    }

    private function provider(): ?string
    {
        $provider = strtolower((string) config('help.llm.provider', 'anthropic'));

        return in_array($provider, ['anthropic', 'openai'], true) ? $provider : null;
    }
}
