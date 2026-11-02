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
}
