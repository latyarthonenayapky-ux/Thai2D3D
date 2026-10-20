<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Answers a usage question from the Burmese/English guides in docs/.
 *
 * The guide excerpts are always the ground truth. When a model is configured the
 * excerpts are handed to it so the reply reads like an answer instead of a
 * search result; without one, or over the per-user daily budget, the raw
 * excerpts are returned, which keeps an offline or keyless install useful.
 */
final class HelpAssistant
{
    private const PROMPT_SECTION_LIMIT = 900;

    public function __construct(
        private readonly DocIndex $index,
        private readonly LlmClient $llm,
    ) {}

    /**
     * @return array{source: string, answer: string, references: array<int, array{doc: string, title: string}>}
     */
    public function answer(string $question, string $page = '', int $userId = 0): array
    {
        $sections = $this->index->search($question, max(1, (int) config('help.context_sections', 4)));

        if ($sections === []) {
            return ['source' => 'none', 'answer' => __('app.help.no_match'), 'references' => []];
        }

        $references = array_map(
            fn (array $section): array => ['doc' => $section['doc'], 'title' => $section['title']],
            $sections,
        );

        if ($this->modelBudgetAvailable($userId)) {
            $reply = $this->llm->ask($this->systemPrompt(), $this->prompt($question, $page, $sections));

            if ($reply !== null) {
                return ['source' => 'model', 'answer' => $reply, 'references' => $references];
            }
        }

        return ['source' => 'docs', 'answer' => $this->compose($sections), 'references' => $references];
    }

    /**
     * @param  array<int, array{doc: string, title: string, body: string}>  $sections
     */
    private function compose(array $sections): string
    {
        $blocks = [];

        foreach ($sections as $section) {
            $blocks[] = '▸ '.$section['title']."\n".$this->truncate($section['body']);
        }

        return implode("\n\n", $blocks);
    }

    private function systemPrompt(): string
    {
        return 'You answer usage questions about Winning Yields Always (WYA), a Thai 2D/3D '
            .'number-bookkeeping app used by an office admin, its operators and its agents. '
            .'Use only the guide excerpts provided. Reply in the language of the question: Burmese '
            .'for Myanmar script, English otherwise. Give short numbered steps naming the menu or '
            .'page to open, and when the user describes an error, explain the likely cause first and '
            .'then the fix. If the excerpts do not cover it, say the guide does not cover it and tell '
            .'them to contact whoever set up their copy. Never invent file paths, server commands, '
            .'prices, payout rates or draw numbers. Never ask for a password or a licence string. '
            .'Reply in plain text with no markdown headings.';
    }

    /**
     * @param  array<int, array{doc: string, title: string, body: string}>  $sections
     */
    private function prompt(string $question, string $page, array $sections): string
    {
        $lines = [];

        if ($page !== '') {
            $lines[] = 'Page the user is on: '.$page;
        }

        $lines[] = 'Question: '.$question;
        $lines[] = '';
        $lines[] = 'Guide excerpts:';

        foreach ($sections as $index => $section) {
            $lines[] = ($index + 1).') ['.$section['doc'].' · '.$section['title'].']';
            $lines[] = $this->truncate($section['body']);
        }

        return implode("\n", $lines);
    }

    private function truncate(string $text): string
    {
        return mb_strlen($text) > self::PROMPT_SECTION_LIMIT
            ? mb_substr($text, 0, self::PROMPT_SECTION_LIMIT).'…'
            : $text;
    }

    /**
     * Counts one attempt per model call so a shared server cannot be drained by
     * one curious account. Zero means the budget is off.
     */
    private function modelBudgetAvailable(int $userId): bool
    {
        if (! $this->llm->configured()) {
            return false;
        }

        $limit = (int) config('help.llm.daily_limit', 40);

        if ($limit <= 0) {
            return true;
        }

        $key = 'help-assistant-'.$userId.'-'.Carbon::now('Asia/Yangon')->toDateString();
        $used = (int) Cache::get($key, 0);

        if ($used >= $limit) {
            return false;
        }

        Cache::put($key, $used + 1, Carbon::now('Asia/Yangon')->addDay());

        return true;
    }
}
