<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/InstallKey.php';

use App\Services\InstallKey;

$arguments = array_slice($argv, 1);

if (count($arguments) >= 1) {
    $email = $arguments[0];
} else {
    fwrite(STDOUT, "Thai2D3D install key generator\n");
    fwrite(STDOUT, "==============================\n\n");
    fwrite(STDOUT, "The key is calculated from the admin email the customer sends you\n");
    fwrite(STDOUT, "and installs Thai2D3D with.\n\n");

    $email = prompt('Admin email: ');
}

if (trim($email) === '') {
    fwrite(STDERR, "\nThe admin email is required.\n");

    exit(1);
}

$key = InstallKey::derive($email);

fwrite(STDOUT, "\nEmail: {$email}\n");
fwrite(STDOUT, "Key:   {$key}\n\n");
fwrite(STDOUT, "Send this key to the customer. It unlocks the setup for this exact\n");
fwrite(STDOUT, "email (uppercase email letters and spaces at the edges are\n");
fwrite(STDOUT, "ignored).\n");

function prompt(string $label): string
{
    fwrite(STDOUT, $label);

    return rtrim((string) fgets(STDIN), "\r\n");
}
