<?php

namespace Webkul\Sso\Support;

use RuntimeException;

/**
 * A sign-in that cannot go ahead. The code picks the message shown to the person (in their
 * language); the detail goes to the security audit log only.
 */
class SsoException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail !== '' ? $detail : $reason);
    }
}
