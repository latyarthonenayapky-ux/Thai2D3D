<?php

namespace App\Services\Licence;

use RuntimeException;

/**
 * Thrown for every rejected licence string so callers can turn it into a field
 * error instead of letting a parse failure bubble up as a 500.
 *
 * The English message is for vendor tooling and logs; the reason code picks the
 * translated customer-facing string (app.licence.error_<code>).
 */
class LicenceException extends RuntimeException
{
    public const REASON_UNSUPPORTED = 'unsupported';

    public const REASON_FORMAT = 'format';

    public const REASON_PAYLOAD = 'payload';

    public const REASON_SIGNATURE = 'signature';

    public const REASON_VERSION = 'version';

    public const REASON_FIELDS = 'fields';

    public const REASON_ORDER = 'order';

    public const REASON_KEY = 'key';

    public function __construct(string $message, private readonly string $reason = self::REASON_FORMAT)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function messageKey(): string
    {
        return 'app.licence.error_'.$this->reason;
    }
}
