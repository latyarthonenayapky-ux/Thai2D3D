<?php

namespace App\Services;

/**
 * Derives the install pass key from the admin email the customer installs with.
 *
 * The desktop keygen (desktop/keygen) mirrors this exact algorithm in
 * JavaScript and must stay byte-identical. Changing PEPPER invalidates every
 * pass key that was already issued to customers.
 */
class InstallKey
{
    private const PEPPER = '44ac1d7eaa014d7672430368ca925b6d87d38c9081a508fd9c697cb8524a1eb3';

    private const KEY_GROUPS = 4;

    private const KEY_GROUP_SIZE = 5;

    public static function derive(string $email): string
    {
        $digest = hash_hmac('sha256', self::payload($email), self::PEPPER, true);
        $encoded = substr(self::base32($digest), 0, self::KEY_GROUPS * self::KEY_GROUP_SIZE);

        return implode('-', str_split($encoded, self::KEY_GROUP_SIZE));
    }

    public static function matches(string $email, string $candidate): bool
    {
        return hash_equals(
            self::fingerprint(self::derive($email)),
            self::fingerprint($candidate)
        );
    }

    /**
     * ASCII-only lowercase and trim so PHP, the browser keygen and the
     * command-line keygen agree for every possible input.
     */
    private static function payload(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function fingerprint(string $key): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($key)) ?? '';
    }

    private static function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }
}
