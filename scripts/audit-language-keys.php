<?php

$en = require __DIR__.'/../lang/en/app.php';
$my = require __DIR__.'/../lang/my/app.php';

$flatten = function (array $tree, string $prefix = '') use (&$flatten): array {
    $out = [];

    foreach ($tree as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $out = array_merge($out, $flatten($value, $path));

            continue;
        }

        $out[$path] = (string) $value;
    }

    return $out;
};

$flatEn = $flatten($en);
$flatMy = $flatten($my);

$missing = array_diff_key($flatEn, $flatMy);
$extra = array_diff_key($flatMy, $flatEn);
$untranslated = [];

foreach (array_intersect_key($flatEn, $flatMy) as $path => $value) {
    if ($flatMy[$path] === $value && ! preg_match('/\x{1000}-\x{109F}/u', $value)) {
        $untranslated[] = $path;
    }
}

printf("keys en=%d my=%d".PHP_EOL, count($flatEn), count($flatMy));
printf("missing in my=%d".PHP_EOL, count($missing));
foreach ($missing as $path) {
    echo "  - $path".PHP_EOL;
}
printf("extra in my=%d".PHP_EOL, count($extra));
foreach ($extra as $path) {
    echo "  + $path".PHP_EOL;
}
printf("identical without burmese=%d".PHP_EOL, count($untranslated));
foreach ($untranslated as $path) {
    echo "  = $path".PHP_EOL;
}

// Burmese copy legitimately reuses a few Latin punctuation marks.
$allowed = ['·', '§', '–', '’', '“', '”', '×', '€', '™'];
$suspects = 0;

foreach ($flatMy as $path => $value) {
    $stripped = str_replace($allowed, '', $value);

    if (preg_match('/[\x{0080}-\x{00FF}\x{FFFD}\x{FFFF}]/u', $stripped)) {
        $suspects++;
        printf('suspect mojibake: '.$path.' = '.$value.PHP_EOL);
    }
}

printf("suspect mojibake=%d".PHP_EOL, $suspects);
