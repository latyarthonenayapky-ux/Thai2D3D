<?php

declare(strict_types=1);

/**
 * Creates the vendor's Ed25519 signing key pair.
 *
 * The secret half is written to a file outside this repository and is never
 * committed; the public half is printed as the one-line constant to paste into
 * App\Services\Licence\Licence::EMBEDDED_PUBLIC_KEY.
 *
 *   php generate-key.php            # writes ~/.wya-licence-key/secret-key.txt
 *   php generate-key.php --key=D:\keys\wya.txt
 */

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../../app/Services/Licence/Licence.php';

use App\Services\Licence\Licence;

if (! Licence::isSupported()) {
    fwrite(STDERR, "PHP sodium is required.\n");

    exit(1);
}

$keyPath = null;

foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--key=(.*)$/', $argument, $matches) === 1) {
        $keyPath = $matches[1];
    }
}

$keyPath = $keyPath ?: (getenv('HOME') ?: getenv('USERPROFILE')).DIRECTORY_SEPARATOR
    .'.wya-licence-key'.DIRECTORY_SEPARATOR.'secret-key.txt';

if (is_file($keyPath)) {
    fwrite(STDERR, "\nA key already exists at {$keyPath}. Refusing to overwrite issued licences.\n");

    exit(1);
}

$pair = sodium_crypto_sign_keypair();
$secret = Licence::encodeBase64Url(sodium_crypto_sign_secretkey($pair));
$public = Licence::encodeBase64Url(sodium_crypto_sign_publickey($pair));

$directory = dirname($keyPath);

if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
    fwrite(STDERR, "\nCould not create {$directory}\n");

    exit(1);
}

file_put_contents($keyPath, $secret."\n");
chmod($keyPath, 0600);

fwrite(STDOUT, "\nSigning key written to: {$keyPath}\n");
fwrite(STDOUT, "Keep it off this repository, off shared drives, and off any customer machine.\n\n");
fwrite(STDOUT, "Public key constant for app/Services/Licence/Licence.php:\n\n");
fwrite(STDOUT, "    private const EMBEDDED_PUBLIC_KEY = '{$public}';\n\n");
