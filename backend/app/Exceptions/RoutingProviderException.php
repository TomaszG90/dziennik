<?php

namespace App\Exceptions;

use RuntimeException;

class RoutingProviderException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('The routing provider is unavailable.');
    }

    public static function rateLimited(): self
    {
        return new self('The routing provider rate limit has been exceeded.');
    }
}
