<?php

declare(strict_types=1);

/**
 * Vendor licence signer.
 *
 * Runs on the vendor's machine only, with the bundled PHP runtime
 * (desktop/runtime/php-win/php.exe). The private key lives outside this
 * repository, in ~/.wya-licence-key/secret-key.txt by default, and the app
 * ships only the matching public key, so a customer can never mint a licence.
 *
 *   php sign-licence.php --email=owner@example.com --plan=standard --years=1
 *
 * Every issuance is appended to issued-licences.csv next to this script, which
 * is the vendor's payment ledger for licences.
 */

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../app/Services/Licence/Licence.php';
require __DIR__.'/../../app/Services/Licence/LicenceException.php';

use App\Services\Licence\Licence;
use App\Services\Licence\LicenceException;

date_default_timezone_set('Asia/Yangon');

if (! Licence::isSupported()) {
    fwrite(STDERR, "PHP sodium is required. Run this with the bundled php.exe, or a PHP built with sodium.\n");

    exit(1);
}

$options = parse_options(array_slice($argv, 1));

if (isset($options['help'])) {
    fwrite(STDOUT, usage());
    exit(0);
}

$keyPath = resolve_key_path($options['key'] ?? null);
$secretKey = load_secret_key($keyPath);

$email = prompt_value($options, 'email', 'Customer email: ');
$plan = trim((string) ($options['plan'] ?? prompt('Plan [standard]: ', 'standard')));
$id = trim((string) ($options['id'] ?? prompt('Licence id [WYA-'.date('Y').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT).']: ', '')));
$years = max(1, (int) ($options['years'] ?? prompt('Licence term in years [1]: ', '1')));
$maxAdmins = (int) ($options['max_admins'] ?? prompt('Max admins [1]: ', '1'));
$maxAgents = (int) ($options['max_agents'] ?? prompt('Max agents [50]: ', '50'));
$paymentRef = trim((string) ($options['payment_ref'] ?? prompt('Payment reference: ', '')));

$issuedAt = date('Y-m-d');
$expiresAt = date('Y-m-d', strtotime($issuedAt.' +'.$years.' year'));

try {
    $licenceString = Licence::sign([
        'v' => Licence::VERSION,
        'id' => $id,
        'email' => Licence::normalizeEmail($email),
        'plan' => $plan,
        'max_admins' => $maxAdmins,
        'max_agents' => $maxAgents,
        'issued_at' => $issuedAt,
        'expires_at' => $expiresAt,
    ], $secretKey);

    // Round trip before handing it over: the customer must not be the first to
    // discover a bad licence.
    $verified = Licence::parse($licenceString);
} catch (LicenceException $exception) {
    fwrite(STDERR, "\n".$exception->getMessage()."\n");

    exit(1);
}

append_ledger([
    'id' => $verified->id,
    'email' => $verified->email,
    'plan' => $verified->plan,
    'issued' => $verified->issuedAt,
    'expiry' => $verified->expiresAt,
    'payment_ref' => $paymentRef,
]);

fwrite(STDOUT, "\nLicence for {$verified->email} ({$verified->plan}) valid until {$verified->expiresAt}\n\n");
fwrite(STDOUT, $licenceString."\n\n");
fwrite(STDOUT, "Paste this into the customer's setup screen or Settings > Licence.\n");
fwrite(STDOUT, "Recorded in ".ledger_path()."\n");

exit(0);

function parse_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $argument, $matches) === 1) {
            $options[str_replace('-', '_', $matches[1])] = $matches[2] ?? '1';
        }
    }

    return $options;
}

function resolve_key_path(?string $explicit): string
{
    $path = $explicit
        ?? (getenv('WYA_LICENCE_SECRET_KEY') ?: default_key_dir().DIRECTORY_SEPARATOR.'secret-key.txt');

    if (! is_file($path)) {
        fwrite(STDERR, "\nNo signing key at {$path}\n");
        fwrite(STDERR, "Create one first: php generate-key.php --key={$path}\n");

        exit(1);
    }

    return $path;
}

function default_key_dir(): string
{
    $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: getcwd());

    return $home.DIRECTORY_SEPARATOR.'.wya-licence-key';
}

function load_secret_key(string $path): string
{
    $contents = trim((string) file_get_contents($path));
    $decoded = Licence::decodeBase64Url($contents);

    if ($decoded === null || strlen($decoded) !== 64) {
        fwrite(STDERR, "\nThe key file must hold the base64url of a 64-byte sodium secret key.\n");

        exit(1);
    }

    return $decoded;
}

function append_ledger(array $row): void
{
    $path = ledger_path();
    $isNew = ! is_file($path);
    $handle = fopen($path, 'a');

    if ($handle === false) {
        fwrite(STDERR, "\nCould not open the ledger at {$path}; the licence is still valid.\n");

        return;
    }

    if ($isNew) {
        fputcsv($handle, ['id', 'email', 'plan', 'issued', 'expiry', 'payment-ref']);
    }

    fputcsv($handle, array_values($row));
    fclose($handle);
}

function ledger_path(): string
{
    return __DIR__.DIRECTORY_SEPARATOR.'issued-licences.csv';
}

function prompt_value(array $options, string $name, string $label): string
{
    $value = trim((string) ($options[$name] ?? prompt($label, '')));

    if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        fwrite(STDERR, "\nA valid customer email is required.\n");

        exit(1);
    }

    return $value;
}

function prompt(string $label, string $default = ''): string
{
    fwrite(STDOUT, $label);

    $line = fgets(STDIN);
    $value = $line === false ? '' : rtrim($line, "\r\n");

    return $value === '' ? $default : $value;
}

function usage(): string
{
    return <<<TEXT
    Usage: php sign-licence.php --email=<customer email> [options]

      --id            licence id            (default WYA-<year>-<random>)
      --plan          plan name             (default standard)
      --years         term in years         (default 1)
      --max-admins    admin seats           (default 1)
      --max-agents    agent seats           (default 50)
      --payment-ref   ledger note           (default empty)
      --key           private key file      (default ~/.wya-licence-key/secret-key.txt)

    TEXT;
}
