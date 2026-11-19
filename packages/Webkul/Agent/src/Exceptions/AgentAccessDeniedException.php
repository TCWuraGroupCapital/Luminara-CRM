<?php

namespace Webkul\Agent\Exceptions;

use RuntimeException;

final class AgentAccessDeniedException extends RuntimeException
{
    public function __construct(public readonly string $requiredPermission)
    {
        parent::__construct("Your role lacks the [{$requiredPermission}] permission.");
    }
}
