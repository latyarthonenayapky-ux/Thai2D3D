<?php

namespace App\Services\Licence;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;

/**
 * One signed licence: payload, Ed25519 signature and the rules that decide
 * whether a copy of WYA may accept writes.
 *
 * The vendor signs licences with the private key kept outside this repository
 * (desktop/keygen/sign-licence.php); only the matching public key ships in the
 * app, so a customer can verify and install a licence but never mint one.
 */
final class Licence
{
    public const VERSION = 1;

    /** Full access continues this many days past expires_at. */
    public const GRACE_DAYS = 7;

    /** A clock behind the last seen time by more than this is tampering. */
    public const CLOCK_ROLLBACK_TOLERANCE_SECONDS = 86400;

    public const STATE_MISSING = 'missing';

    public const STATE_INVALID = 'invalid';

    public const STATE_VALID = 'valid';

    public const STATE_GRACE = 'grace';

    public const STATE_EXPIRED = 'expired';

    public const STATE_CLOCK_ROLLBACK = 'clock_rollback';

    /**
     * Public key of the vendor signing key pair, base64url of the 32-byte
     * sodium Ed25519 public key. Rotate by re-issuing every licence.
     */
    private const EMBEDDED_PUBLIC_KEY = 'GmRFEJPq1dsMQkp73oDiPBXsW_gXR3REDsIxXiYe73g';

    /** Fixed canonical key order; the signature covers these exact bytes. */
    private const FIELDS = ['v', 'id', 'email', 'plan', 'max_admins', 'max_agents', 'issued_at', 'expires_at'];

    private function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $plan,
        public readonly int $maxAdmins,
        public readonly int $maxAgents,
        public readonly string $issuedAt,
        public readonly string $expiresAt,
        public readonly string $licenceString,
    ) {
    }

    public static function isSupported(): bool
    {
        return extension_loaded('sodium')
            && function_exists('sodium_crypto_sign_verify_detached');
    }

    /**
     * Test hook only: the shipped app never sets WYA_LICENCE_PUBLIC_KEY, so
     * production always verifies against the embedded key. The vendor signer
     * runs outside Laravel, where no configuration exists to read.
     */
    public static function publicKey(): string
    {
        // The vendor signer loads this class without a booted framework, where
        // there is no configuration repository to read.
        $container = Container::getInstance();
        $configured = $container->bound('config') ? (string) $container->make('config')->get('licence.public_key') : '';

        return $configured === '' ? self::EMBEDDED_PUBLIC_KEY : $configured;
    }

    /**
     * Canonical payload bytes for a licence: fixed key order, unescaped
     * slashes, no whitespace. The signer and the verifier share this method.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function canonicalPayload(array $fields): string
    {
        $ordered = [];

        foreach (self::FIELDS as $key) {
            $ordered[$key] = $fields[$key] ?? null;
        }

        if ($ordered['v'] === null) {
            $ordered['v'] = self::VERSION;
        }

        $json = json_encode($ordered, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new LicenceException('The licence payload could not be encoded.', LicenceException::REASON_PAYLOAD);
        }

        return $json;
    }

    /**
     * Signs a licence. Vendor tooling only (desktop/keygen); app code never
     * holds a secret key.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function sign(array $fields, string $secretKey): string
    {
        if (! self::isSupported()) {
            throw new LicenceException('PHP sodium is required to sign a licence.', LicenceException::REASON_UNSUPPORTED);
        }

        if (strlen($secretKey) !== 64) {
            throw new LicenceException('The signing key must be a 64-byte sodium secret key.', LicenceException::REASON_KEY);
        }

        $payload = self::canonicalPayload($fields);

        return self::encodeBase64Url($payload).'.'.self::encodeBase64Url(
            sodium_crypto_sign_detached($payload, $secretKey)
        );
    }

    /**
     * @throws LicenceException  for any malformed or untrusted licence string
     */
    public static function parse(string $candidate): self
    {
        if (! self::isSupported()) {
            throw new LicenceException('This build of PHP cannot verify licences (sodium is missing).', LicenceException::REASON_UNSUPPORTED);
        }

        $candidate = trim($candidate);

        if ($candidate === '' || substr_count($candidate, '.') !== 1) {
            throw new LicenceException('The licence string must look like <payload>.<signature>.', LicenceException::REASON_FORMAT);
        }

        [$encodedPayload, $encodedSignature] = explode('.', $candidate, 2);
        $payload = self::decodeBase64Url($encodedPayload);
        $signature = self::decodeBase64Url($encodedSignature);

        if ($payload === null || $payload === '' || strlen($payload) > 4096) {
            throw new LicenceException('The licence payload is not readable.', LicenceException::REASON_PAYLOAD);
        }

        if ($signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new LicenceException('The licence signature is not readable.', LicenceException::REASON_FORMAT);
        }

        $publicKey = self::publicKey();
        $decodedPublicKey = self::decodeBase64Url($publicKey);

        if ($decodedPublicKey === null || strlen($decodedPublicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new LicenceException('The embedded licence key is not usable.', LicenceException::REASON_KEY);
        }

        if (! sodium_crypto_sign_verify_detached($signature, $payload, $decodedPublicKey)) {
            throw new LicenceException('The licence signature does not match this product.', LicenceException::REASON_SIGNATURE);
        }

        $fields = json_decode($payload, true);

        if (! is_array($fields)) {
            throw new LicenceException('The licence payload is not readable.', LicenceException::REASON_PAYLOAD);
        }

        if (array_keys($fields) !== self::FIELDS) {
            throw new LicenceException('The licence payload has unexpected fields.', LicenceException::REASON_FIELDS);
        }

        if ((int) $fields['v'] !== self::VERSION) {
            throw new LicenceException('This licence is for a newer product version.', LicenceException::REASON_VERSION);
        }

        $id = trim((string) $fields['id']);
        $email = self::normalizeEmail((string) $fields['email']);
        $plan = trim((string) $fields['plan']);
        $issuedAt = self::normalizeDate($fields['issued_at']);
        $expiresAt = self::normalizeDate($fields['expires_at']);

        if ($id === '' || strlen($id) > 64 || $email === '' || $plan === '' || $issuedAt === '' || $expiresAt === '') {
            throw new LicenceException('The licence is missing required details.', LicenceException::REASON_FIELDS);
        }

        if ($expiresAt <= $issuedAt) {
            throw new LicenceException('The licence expiry date must be after its issue date.', LicenceException::REASON_ORDER);
        }

        return new self(
            $id,
            $email,
            $plan,
            max(0, (int) $fields['max_admins']),
            max(0, (int) $fields['max_agents']),
            $issuedAt,
            $expiresAt,
            $candidate,
        );
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email, " \t\n\r\0\x0B"));
    }

    public function expiresAtCarbon(): \Carbon\CarbonImmutable
    {
        return \Carbon\CarbonImmutable::parse($this->expiresAt, 'Asia/Yangon')->endOfDay();
    }

    public function graceEndsAt(): \Carbon\CarbonImmutable
    {
        return $this->expiresAtCarbon()->addDays(self::GRACE_DAYS);
    }

    public function isEmail(string $email): bool
    {
        return hash_equals($this->email, self::normalizeEmail($email));
    }

    /**
     * Status of this licence against a moment in time, ignoring the clock
     * rollback rule which needs stored state.
     */
    public function statusAt(\Carbon\CarbonImmutable $now): string
    {
        if ($now->lessThanOrEqualTo($this->expiresAtCarbon())) {
            return self::STATE_VALID;
        }

        return $now->lessThanOrEqualTo($this->graceEndsAt()) ? self::STATE_GRACE : self::STATE_EXPIRED;
    }

    /**
     * Whole days left before the read-only switch, used for the 30/7/1 banner.
     */
    public function daysRemaining(\Carbon\CarbonImmutable $now): int
    {
        $end = $this->expiresAtCarbon();

        if ($now->greaterThan($end)) {
            return 0;
        }

        return $now->startOfDay()->diffInDays($end->startOfDay(), false);
    }

    private static function normalizeDate(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            return '';
        }

        try {
            return \Carbon\CarbonImmutable::parse((string) $value, 'Asia/Yangon')->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    public static function encodeBase64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decodeBase64Url(string $value): ?string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9\-_]/', $value) === 1) {
            return null;
        }

        $padded = str_pad(strtr($value, '-_', '+/'), (int) (ceil(strlen($value) / 4) * 4), '=');
        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
