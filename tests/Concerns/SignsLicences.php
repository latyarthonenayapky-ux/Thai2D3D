<?php

namespace Tests\Concerns;

use App\Services\Licence\Licence;
use PHPUnit\Framework\Attributes\Before;

/**
 * Signs throwaway licences for tests.
 *
 * Verification is pointed at a generated public key through
 * config('licence.public_key'), so the suite never needs the vendor private
 * key. LicenceSignatureTest covers the real embedded key with a fixed vector.
 */
trait SignsLicences
{
    /** @var array{secret: string, public: string}|null */
    private ?array $testSigningKey = null;

    #[Before]
    protected function requireSodium(): void
    {
        if (! Licence::isSupported()) {
            $this->markTestSkipped('The licence tests need the PHP sodium extension.');
        }
    }

    protected function signingKey(): array
    {
        if ($this->testSigningKey === null) {
            $pair = sodium_crypto_sign_keypair();
            $this->testSigningKey = [
                'secret' => sodium_crypto_sign_secretkey($pair),
                'public' => Licence::encodeBase64Url(sodium_crypto_sign_publickey($pair)),
            ];
        }

        config(['licence.public_key' => $this->testSigningKey['public']]);

        return $this->testSigningKey;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function issueLicence(string $email, string $issuedAt, string $expiresAt, array $overrides = []): string
    {
        $key = $this->signingKey();

        return Licence::sign(array_merge([
            'v' => Licence::VERSION,
            'id' => 'WYA-TEST-0001',
            'email' => Licence::normalizeEmail($email),
            'plan' => 'standard',
            'max_admins' => 1,
            'max_agents' => 50,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ], $overrides), $key['secret']);
    }

    protected function activeLicence(string $email, string $daysFromNow = '+365 days'): string
    {
        $now = \Carbon\CarbonImmutable::now('Asia/Yangon');

        return $this->issueLicence(
            $email,
            $now->toDateString(),
            $now->modify($daysFromNow)->toDateString(),
        );
    }
}
