<?php

namespace Webkul\Tma\Exceptions;

use RuntimeException;

final class TmaAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
