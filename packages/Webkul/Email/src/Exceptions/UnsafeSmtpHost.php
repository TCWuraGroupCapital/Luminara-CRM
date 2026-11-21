<?php

namespace Webkul\Email\Exceptions;

use RuntimeException;

/**
 * An outgoing mail server that points at a private network, or uses a port mail does not use.
 */
class UnsafeSmtpHost extends RuntimeException
{
    public static function host(string $host): self
    {
        return new self(trans('admin::app.configuration.index.email.smtp.unsafe-host', ['host' => $host]));
    }

    public static function unresolved(string $host): self
    {
        return new self(trans('admin::app.configuration.index.email.smtp.unresolved-host', ['host' => $host]));
    }

    /**
     * @param  list<int>  $allowed
     */
    public static function port(int $port, array $allowed): self
    {
        return new self(trans('admin::app.configuration.index.email.smtp.unsafe-port', [
            'port' => $port,
            'ports' => implode(', ', $allowed),
        ]));
    }
}
