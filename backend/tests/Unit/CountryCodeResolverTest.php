<?php

namespace Tests\Unit;

use App\Services\CountryCodeResolver;
use App\Services\GeocodingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CountryCodeResolverTest extends TestCase
{
    private function resolver(): CountryCodeResolver
    {
        return new CountryCodeResolver(new GeocodingService);
    }

    private function fakeProviderCountry(string $countryCode): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => [
                    [
                        'properties' => ['countrycode' => $countryCode],
                    ],
                ],
            ]),
        ]);
    }

    public function test_provided_country_code_is_used_without_calling_the_provider(): void
    {
        Http::fake();

        $countryCode = $this->resolver()->resolve('AT', 48.2082, 16.3725);

        $this->assertSame('AT', $countryCode);

        Http::assertNothingSent();
    }

    public function test_provided_country_code_is_normalized_to_upper_case(): void
    {
        Http::fake();

        $countryCode = $this->resolver()->resolve(' at ', 48.2082, 16.3725);

        $this->assertSame('AT', $countryCode);

        Http::assertNothingSent();
    }

    public function test_provider_country_is_used_when_no_country_code_was_provided(): void
    {
        $this->fakeProviderCountry('AT');
        Cache::flush();

        $countryCode = $this->resolver()->resolve(null, 48.2082, 16.3725);

        $this->assertSame('AT', $countryCode);
    }

    public function test_provider_country_is_normalized_to_upper_case(): void
    {
        $this->fakeProviderCountry('at');
        Cache::flush();

        $countryCode = $this->resolver()->resolve(null, 48.2082, 16.3725);

        $this->assertSame('AT', $countryCode);
    }

    public function test_invalid_provided_country_code_falls_back_to_the_provider(): void
    {
        $this->fakeProviderCountry('AT');
        Cache::flush();

        $countryCode = $this->resolver()->resolve('AUSTRIA', 48.2082, 16.3725);

        $this->assertSame('AT', $countryCode);
        Http::assertSentCount(1);
    }

    public function test_empty_provided_country_code_falls_back_to_the_provider(): void
    {
        $this->fakeProviderCountry('PL');
        Cache::flush();

        $countryCode = $this->resolver()->resolve('', 51.1079, 17.0385);

        $this->assertSame('PL', $countryCode);
        Http::assertSentCount(1);
    }

    public function test_unknown_country_returns_null(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response(['features' => []]),
        ]);

        Cache::flush();

        $countryCode = $this->resolver()->resolve(null, 0.0, 0.0);

        $this->assertNull($countryCode);
    }

    public function test_provider_failure_returns_null_and_is_logged(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response(status: 500),
        ]);

        Cache::flush();
        Log::spy();

        $countryCode = $this->resolver()->resolve(null, 48.2082, 16.3725);

        $this->assertNull($countryCode);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_connection_error_returns_null(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        Cache::flush();

        $countryCode = $this->resolver()->resolve(null, 48.2082, 16.3725);

        $this->assertNull($countryCode);
    }

    public function test_provider_is_queried_once_for_repeated_coordinates(): void
    {
        $this->fakeProviderCountry('AT');
        Cache::flush();

        $resolver = $this->resolver();

        $resolver->resolve(null, 48.2082, 16.3725);
        $resolver->resolve(null, 48.2082, 16.3725);

        Http::assertSentCount(1);
    }
}
