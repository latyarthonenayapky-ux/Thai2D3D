<?php

namespace Tests\Feature;

use App\Services\Licence\Licence;
use App\Services\Licence\LicenceException;
use Carbon\CarbonImmutable;
use Tests\Concerns\SignsLicences;
use Tests\TestCase;

/**
 * The signing rules on their own, with no HTTP and no database.
 *
 * The vector at the top was produced by desktop/keygen/sign-licence.php with
 * the real vendor private key, so it is the contract between the signer and
 * the public key embedded in Licence.php.
 */
class LicenceSignatureTest extends TestCase
{
    use SignsLicences;

    private const VECTOR_EMAIL = 'owner@thai2d3d.local';

    private const VECTOR_LICENCE = 'eyJ2IjoxLCJpZCI6IldZQS0yMDI2LTAwMDEiLCJlbWFpbCI6Im93bmVyQHRoYWkyZDNkLmxvY2FsIiwicGxhbiI6InN0YW5kYXJkIiwibWF4X2FkbWlucyI6MSwibWF4X2FnZW50cyI6NTAsImlzc3VlZF9hdCI6IjIwMjYtMTAtMTQiLCJleHBpcmVzX2F0IjoiMjAyNy0xMC0xNCJ9.zCW4zSHDokhLDSapz3T5VY6rMbzOGlagU6Nf-fHaaG3wQ8s8dJpww3mxEsECBVZFaUaj7SzrSkuT8wj3edLLBQ';

    private const VECTOR_PAYLOAD = '{"v":1,"id":"WYA-2026-0001","email":"owner@thai2d3d.local","plan":"standard","max_admins":1,"max_agents":50,"issued_at":"2026-10-14","expires_at":"2027-10-14"}';

    public function test_the_shipped_public_key_verifies_the_vendor_signer_vector(): void
    {
        config(['licence.public_key' => null]);

        $licence = Licence::parse(self::VECTOR_LICENCE);

        $this->assertSame('WYA-2026-0001', $licence->id);
        $this->assertSame(self::VECTOR_EMAIL, $licence->email);
        $this->assertSame('2027-10-14', $licence->expiresAt);
        $this->assertSame(1, $licence->maxAdmins);
        $this->assertSame(50, $licence->maxAgents);
    }

    public function test_the_signer_and_the_app_agree_on_the_canonical_payload_bytes(): void
    {
        $canonical = Licence::canonicalPayload([
            'expires_at' => '2027-10-14',
            'issued_at' => '2026-10-14',
            'max_agents' => 50,
            'max_admins' => 1,
            'plan' => 'standard',
            'email' => self::VECTOR_EMAIL,
            'id' => 'WYA-2026-0001',
            'v' => Licence::VERSION,
        ]);

        $this->assertSame(self::VECTOR_PAYLOAD, $canonical);
        $this->assertSame(
            self::VECTOR_PAYLOAD,
            base64_decode(strtr(explode('.', self::VECTOR_LICENCE)[0], '-_', '+/'), true)
        );
    }

    public function test_a_licence_signed_by_somebody_else_is_rejected(): void
    {
        $candidate = $this->issueLicence(self::VECTOR_EMAIL, '2026-10-14', '2027-10-14');
        config(['licence.public_key' => null]);

        $this->expectException(LicenceException::class);
        $this->expectExceptionMessage('signature does not match');

        Licence::parse($candidate);
    }

    public function test_editing_the_payload_after_signing_breaks_the_signature(): void
    {
        config(['licence.public_key' => null]);
        [$payload, $signature] = explode('.', self::VECTOR_LICENCE);
        $tampered = json_decode(
            base64_decode(strtr($payload, '-_', '+/'), true),
            true
        );
        $tampered['expires_at'] = '2099-01-01';
        $tamperedPayload = rtrim(strtr(base64_encode(json_encode($tampered)), '+/', '-_'), '=');

        $this->expectException(LicenceException::class);

        Licence::parse($tamperedPayload.'.'.$signature);
    }

    public function test_an_unparsable_string_is_rejected_without_a_crash(): void
    {
        foreach (['', 'not-a-licence', 'a.b.c', '!!!!.$$$$', 'dGVzdA==.short'] as $candidate) {
            try {
                Licence::parse($candidate);
                $this->fail('Expected '.$candidate.' to be rejected.');
            } catch (LicenceException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_a_newer_payload_version_is_refused(): void
    {
        $candidate = $this->issueLicence('owner@example.test', '2026-10-14', '2027-10-14', ['v' => 2]);

        $this->assertSame(LicenceException::REASON_VERSION, $this->parseReason($candidate));
    }

    public function test_extra_or_reordered_fields_are_refused(): void
    {
        $key = $this->signingKey();
        $payload = '{"v":1,"id":"WYA-TEST-0001","extra":true}';
        $signature = sodium_crypto_sign_detached($payload, $key['secret']);

        $this->expectException(LicenceException::class);

        Licence::parse(Licence::encodeBase64Url($payload).'.'.Licence::encodeBase64Url($signature));
    }

    public function test_expiry_grace_and_lock_are_decided_from_the_expiry_date(): void
    {
        $licence = Licence::parse($this->issueLicence('owner@example.test', '2026-01-01', '2026-10-14'));
        $expires = CarbonImmutable::parse('2026-10-14 23:59:59', 'Asia/Yangon');

        $this->assertSame(Licence::STATE_VALID, $licence->statusAt($expires));
        $this->assertSame(Licence::STATE_GRACE, $licence->statusAt($expires->addDays(7)));
        $this->assertSame(Licence::STATE_GRACE, $licence->statusAt($expires->addSeconds(1)));
        $this->assertSame(Licence::STATE_EXPIRED, $licence->statusAt($expires->addDays(8)));
        $this->assertSame('2026-10-21', $licence->graceEndsAt()->toDateString());
    }

    public function test_days_remaining_counts_whole_days_to_the_expiry_date(): void
    {
        $licence = Licence::parse($this->issueLicence('owner@example.test', '2026-10-01', '2026-11-14'));

        $this->assertSame(31, $licence->daysRemaining(CarbonImmutable::parse('2026-10-14 08:00', 'Asia/Yangon')));
        $this->assertSame(0, $licence->daysRemaining(CarbonImmutable::parse('2026-11-14 08:00', 'Asia/Yangon')));
        $this->assertSame(0, $licence->daysRemaining(CarbonImmutable::parse('2026-12-01 08:00', 'Asia/Yangon')));
    }

    public function test_the_licence_is_bound_to_one_email_any_casing(): void
    {
        $licence = Licence::parse($this->issueLicence('Owner@Example.Test', '2026-01-01', '2027-01-01'));

        $this->assertSame('owner@example.test', $licence->email);
        $this->assertTrue($licence->isEmail("  OWNER@example.TEST \t"));
        $this->assertFalse($licence->isEmail('other@example.test'));
    }

    public function test_base64url_round_trips_binary(): void
    {
        $bytes = random_bytes(64);

        $this->assertSame($bytes, Licence::decodeBase64Url(Licence::encodeBase64Url($bytes)));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', Licence::encodeBase64Url($bytes));
        $this->assertNull(Licence::decodeBase64Url('with space'));
    }

    private function parseReason(string $candidate): string
    {
        try {
            Licence::parse($candidate);
        } catch (LicenceException $exception) {
            return $exception->reason();
        }

        $this->fail('Expected the licence to be rejected.');
    }
}
