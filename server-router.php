<?php

/**
 * Router for the bundled `php -S` server (desktop app and local smoke tests).
 *
 * The built-in server hands every request to the router script, so requests for
 * real files under public/ must be released back to it before Laravel runs.
 */

$documentRoot = realpath(__DIR__.DIRECTORY_SEPARATOR.'public');

if ($documentRoot === false) {
    http_response_code(500);
    echo 'The application is missing its public directory.';

    return true;
}

$relative = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? ''));
$relative = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
$requested = realpath($documentRoot.DIRECTORY_SEPARATOR.$relative);

if ($requested !== false
    && is_file($requested)
    && str_starts_with($requested, $documentRoot.DIRECTORY_SEPARATOR)
) {
    return false;
}

require $documentRoot.DIRECTORY_SEPARATOR.'index.php';
