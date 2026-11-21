<?php

namespace Webkul\Email\Exceptions;

use RuntimeException;

/**
 * A hosted business tried to send email before connecting its own outgoing account.
 */
class BusinessMailNotConfigured extends RuntimeException
{
    public static function make(): self
    {
        return new self(trans('admin::app.configuration.index.email.smtp.not-configured'));
    }
}
