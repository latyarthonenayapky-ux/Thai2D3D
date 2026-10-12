<?php

/**
 * Mirror the source tree into the packaged release folders so a copied folder
 * always runs the current app. Runtime state (storage, bootstrap/cache, vendor)
 * and .env are never copied or pruned.
 *
 * Usage: php scripts/sync-release.php [VPS|Windows|all] [--dry-run]
 */

$root = str_replace('\\', '/', dirname(__DIR__));
$dryRun = in_array('--dry-run', $argv, true);
$which = 'all';

foreach (array_slice($argv, 1) as $argument) {
    if ($argument !== '--dry-run') {
        $which = $argument;
    }
}

$common = ['app', 'config', 'database', 'lang', 'public', 'resources', 'routes'];
$files = ['artisan', 'composer.json', 'composer.lock', 'server-router.php', 'bootstrap/app.php', 'bootstrap/providers.php'];

$targets = [
    'VPS' => [
        'dest' => $root.'/release/VPS',
        'maps' => [
            ...array_map(fn (string $dir): array => [$dir, $dir], [...$common, 'deploy', 'docs', 'scripts', 'tests']),
            ['.', '.'],
        ],
        'rootFiles' => [...$files, 'README.md', 'PRODUCT.md', 'phpunit.xml', 'package.json', 'vite.config.js', '.env.example'],
    ],
    'Windows' => [
        'dest' => $root.'/release/Windows/Thai2D3D/resources/laravel',
        'maps' => array_map(fn (string $dir): array => [$dir, $dir], $common),
        'rootFiles' => $files,
        'extraMaps' => [['docs', $root.'/release/Windows/Thai2D3D']],
    ],
];

// Packaged runtime state must survive untouched inside the release copy.
$preserve = ['vendor', 'node_modules', 'bootstrap/cache', 'bootstrap/cache/.gitignore', 'public/storage', 'storage'];
$neverCopy = '/(^|\/)(\.env([.].*)?|\.sqlite(\.journal)?)$/';

$isPreserved = function (string $relative) use ($preserve): bool {
    foreach ($preserve as $path) {
        if ($relative === $path || str_starts_with($relative, $path.'/')) {
            return true;
        }
    }

    return false;
};

$sourceFiles = function (string $dir) use ($root, $isPreserved, $neverCopy): array {
    $source = $root.'/'.$dir;

    if (! is_dir($source)) {
        return [];
    }

    $wanted = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);

        if ($isPreserved($relative) || preg_match($neverCopy, $relative)) {
            continue;
        }

        $wanted[$relative] = $file->getPathname();
    }

    return $wanted;
};

$staleFiles = function (string $base, string $prefix) use ($isPreserved, $neverCopy): array {
    if (! is_dir($base.'/'.$prefix)) {
        return [];
    }

    $found = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base.'/'.$prefix, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($base) + 1);

        if ($isPreserved($relative) || preg_match($neverCopy, $relative)) {
            continue;
        }

        $found[$relative] = $file->getPathname();
    }

    return $found;
};

foreach ($targets as $name => $target) {
    if ($which !== 'all' && strcasecmp($which, $name) !== 0) {
        continue;
    }

    if (! is_dir($target['dest'])) {
        fwrite(STDERR, "Missing release folder: {$target['dest']}\n");
        exit(1);
    }

    $copied = 0;
    $pruned = 0;
    $maps = $target['maps'];

    foreach ($target['extraMaps'] ?? [] as [$dir, $base]) {
        $maps[] = [$dir, $dir, $base];
    }

    foreach ($maps as $map) {
        [$dir, $relativePrefix] = $map;
        $baseOverride = $map[2] ?? null;

        if ($dir === '.' && $relativePrefix === '.') {
            continue;
        }

        $wanted = $sourceFiles($dir);
        $base = $baseOverride ?? $target['dest'];

        foreach ($wanted as $relative => $absolute) {
            $to = $base.'/'.$relative;

            if (is_file($to) && md5_file($to) === md5_file($absolute)) {
                continue;
            }

            if (! is_dir(dirname($to))) {
                mkdir(dirname($to), 0777, true);
            }

            if (! $dryRun) {
                copy($absolute, $to);
            }
            $copied++;
        }

        foreach ($staleFiles($base, $relativePrefix) as $relative => $absolute) {
            if (isset($wanted[$relative])) {
                continue;
            }

            if (! $dryRun) {
                unlink($absolute);
            }
            $pruned++;

            if ($dryRun) {
                echo "  prune: {$relative}\n";
            }
        }
    }

    foreach ($target['rootFiles'] as $relative) {
        $from = $root.'/'.$relative;

        if (! is_file($from)) {
            continue;
        }

        $to = $target['dest'].'/'.$relative;

        if (is_file($to) && md5_file($to) === md5_file($from)) {
            continue;
        }

        if (! is_dir(dirname($to))) {
            mkdir(dirname($to), 0777, true);
        }

        if (! $dryRun) {
            copy($from, $to);
        }
        $copied++;
    }

    printf('%-8s %s copied=%-4d pruned=%-4d %s%s', $name, $dryRun ? 'DRY-RUN' : 'SYNC ', $copied, $pruned, $target['dest'], PHP_EOL);
}
