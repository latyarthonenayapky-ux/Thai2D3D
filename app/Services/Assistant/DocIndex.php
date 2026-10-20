<?php

declare(strict_types=1);

namespace App\Services\Assistant;

/**
 * Offline keyword index over the Markdown guides in docs/.
 *
 * Burmese has no word separators, so a plain word split finds nothing. English
 * and digit runs are indexed as words and Myanmar runs as character bigrams,
 * which matches a typed question against the guide text without needing a
 * tokenizer extension.
 */
final class DocIndex
{
    // A Burmese question makes one run plus its bigrams per phrase, so a low cap
    // would silently drop the tail of a two-sentence question.
    private const MAX_TERMS = 120;

    private const EXCERPT_LIMIT = 700;

    /** @var array<int, array{doc: string, title: string, trail: string, body: string}>|null */
    private ?array $sections = null;

    /** @var array<string, int> */
    private array $documentFrequency = [];

    /**
     * @return array<int, array{doc: string, title: string, body: string}>
     */
    public function search(string $question, int $limit = 4): array
    {
        $terms = $this->terms($question);

        if ($terms === []) {
            return [];
        }

        $sections = $this->sections();

        if ($sections === []) {
            return [];
        }

        $total = count($sections);
        $scored = [];

        foreach ($sections as $index => $section) {
            $head = mb_strtolower($section['trail'].' '.$section['title']);
            $body = mb_strtolower($section['body']);
            $score = 0.0;

            foreach ($terms as $term) {
                $frequency = $this->documentFrequency[$term] ?? 0;

                if ($frequency === 0) {
                    continue;
                }

                $inverse = log(1.0 + ($total - $frequency + 0.5) / ($frequency + 0.5));
                $inHeading = substr_count($head, $term) > 0;

                // Length is how much to trust a hit: a matched word or phrase is
                // rarely a coincidence, while a two-codepoint Myanmar bigram lands
                // all over the corpus. Weighed once per term rather than per
                // occurrence, so a section stuffed with common syllables cannot
                // outvote the one that answers the question. A heading is short
                // enough to trust, so a heading hit counts double.
                if ($inHeading || substr_count($body, $term) > 0) {
                    $score += $inverse * $this->specificity($term) * ($inHeading ? 2.0 : 1.0);
                }
            }

            if ($score > 0.0) {
                $scored[] = ['score' => $score, 'index' => $index, 'hits' => $terms];
            }
        }

        usort($scored, fn (array $a, array $b): int => [$b['score'], $a['index']] <=> [$a['score'], $b['index']]);

        $results = [];

        foreach (array_slice($scored, 0, max(1, $limit)) as $match) {
            $section = $sections[$match['index']];
            $results[] = [
                'doc' => $section['doc'],
                'title' => $section['title'],
                'body' => $this->excerpt($section['body'], $match['hits']),
            ];
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    public function terms(string $text, int $limit = self::MAX_TERMS): array
    {
        $text = mb_strtolower(trim($text));
        $terms = [];

        // Two characters is the floor because 2D and 3D are the vocabulary of this
        // app; anything shorter is a fragment rather than a word.
        if (preg_match_all('/[a-z0-9][a-z0-9\-_.]+/', $text, $words) > 0) {
            $terms = $words[0];
        }

        // Splitting on anything outside the Myanmar block leaves one syllable run
        // per phrase; the run itself is a term and its bigrams catch reworded text.
        foreach (preg_split('/[^\x{1000}-\x{103F}\x{A9E0}-\x{A9FF}]+/u', $text) ?: [] as $run) {
            $length = mb_strlen($run);

            if ($length < 2) {
                continue;
            }

            $terms[] = $run;

            for ($offset = 0; $offset < $length - 1; $offset++) {
                $first = mb_substr($run, $offset, 1);

                // A syllable begins with a consonant or an independent vowel. A
                // pair whose first character is a sign or medial (U+102B-U+103E,
                // U+105A-U+105D) straddles a syllable boundary, appears all over
                // the corpus and matches nothing a reader means, so indexing those
                // is what makes unrelated guide sections outvote the right one.
                if ($this->isCombiningMark($first)) {
                    continue;
                }

                $terms[] = $first.mb_substr($run, $offset + 1, 1);
            }
        }

        return array_slice(array_values(array_unique($terms)), 0, max(1, $limit));
    }

    private function isCombiningMark(string $character): bool
    {
        $code = mb_ord($character, 'UTF-8');

        return ($code >= 0x102B && $code <= 0x103E)
            || ($code >= 0x105A && $code <= 0x105D);
    }

    /**
     * How much a matched term is worth, growing with its length.
     *
     * Two Myanmar codepoints are a syllable fragment, indexed only so a reworded
     * question still finds its guide; left at full weight a handful of those
     * coincidences outvotes the section that matches the word the reader typed.
     */
    private function specificity(string $term): float
    {
        $length = mb_strlen($term);
        $discount = $length <= 2 && preg_match('/^[\x{1000}-\x{103F}]/u', $term) === 1 ? 0.4 : 1.0;

        return $discount * (1.0 + $length);
    }

    /**
     * @return array<int, array{doc: string, title: string, trail: string, body: string}>
     */
    private function sections(): array
    {
        if ($this->sections !== null) {
            return $this->sections;
        }

        $sections = [];

        foreach ((array) config('help.docs', []) as $file) {
            $path = base_path('docs/'.$file);

            if (! is_file($path)) {
                continue;
            }

            $sections = array_merge($sections, $this->parse($file, (string) file_get_contents($path)));
        }

        $this->documentFrequency = [];

        foreach ($sections as $section) {
            $seen = [];

            // Every term of a section counts here; the query-time MAX_TERMS cap is
            // only a guard for typed questions. A capped corpus pass would leave
            // terms that are really present reading as absent, and the terms that
            // survive the cut decide the ranking instead of the question.
            foreach ($this->terms($section['trail'].' '.$section['title'].' '.$section['body'], PHP_INT_MAX) as $term) {
                $seen[$term] = true;
            }

            foreach (array_keys($seen) as $term) {
                $this->documentFrequency[$term] = ($this->documentFrequency[$term] ?? 0) + 1;
            }
        }

        return $this->sections = $sections;
    }

    /**
     * @return array<int, array{doc: string, title: string, trail: string, body: string}>
     */
    private function parse(string $file, string $markdown): array
    {
        $sections = [];
        $trail = [];
        $title = $file;
        $body = [];

        // \R is deliberately not used: outside UTF mode PCRE treats the single byte
        // 0x85 as NEL, and 0x85 is a Myanmar continuation byte, so \R cuts
        // characters in half.
        foreach (preg_split("/\r\n|\r|\n/", $markdown) ?: [] as $line) {
            if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $heading) === 1) {
                if (trim(implode("\n", $body)) !== '') {
                    $sections[] = $this->section($file, $title, $trail, $body);
                }

                $body = [];
                $text = trim(str_replace('#', '', $heading[2]));
                $depth = strlen($heading[1]);
                $trail = array_slice($trail, 0, $depth - 1);
                $trail[$depth - 1] = $text;
                $title = $text === '' ? $file : $text;

                continue;
            }

            $body[] = $line;
        }

        if (trim(implode("\n", $body)) !== '') {
            $sections[] = $this->section($file, $title, $trail, $body);
        }

        return $sections;
    }

    /**
     * @param  array<int, string>  $trail
     * @param  array<int, string>  $body
     * @return array{doc: string, title: string, trail: string, body: string}
     */
    private function section(string $file, string $title, array $trail, array $body): array
    {
        $text = preg_replace('/^\s*\|?-+\|?.*$/m', '', implode("\n", $body)) ?? '';
        $text = str_replace(['```', '**'], '', $text);
        $text = trim((string) preg_replace('/[ \t]+/', ' ', $text));
        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", $text));

        return [
            'doc' => $file,
            'title' => $title,
            'trail' => implode(' › ', array_filter($trail)),
            'body' => $text,
        ];
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function excerpt(string $body, array $terms): string
    {
        $lower = mb_strtolower($body);
        $position = null;

        foreach ($terms as $term) {
            $found = mb_strpos($lower, $term);

            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }

        $start = max(0, (int) ($position ?? 0) - 120);

        if (mb_strlen($body) - $start > self::EXCERPT_LIMIT) {
            return '…'.trim(mb_substr($body, $start, self::EXCERPT_LIMIT)).'…';
        }

        return $start > 0 ? '…'.mb_substr($body, $start) : $body;
    }
}
