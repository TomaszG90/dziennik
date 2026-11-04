<?php

namespace App\Exceptions;

use RuntimeException;

class GeocodingProviderException extends RuntimeException
{
    /**
     * Create an exception for an unusable geocoding provider response.
     */
    public static function unavailable(): self
    {
        return new self('The geocoding provider is unavailable.');
    }

    /**
     * Create an exception for an exhausted reverse lookup budget.
     */
    public static function rateLimited(): self
    {
        return new self('The reverse geocoding lookup budget is exhausted.');
    }
}
