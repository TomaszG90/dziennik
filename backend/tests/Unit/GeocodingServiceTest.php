<?php

namespace Tests\Unit;

use App\Exceptions\GeocodingProviderException;
use App\Services\GeocodingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_search_returns_parsed_results_from_photon_api(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'geometry' => [
                            'coordinates' => [16.3725, 48.2082],
                        ],
                        'properties' => [
                            'name' => 'Wien',
                            'countrycode' => 'AT',
                        ],
                    ],
                ],
            ]),
        ]);

        Cache::flush();

        $service = new GeocodingService;
        $results = $service->search('Wien');

        $this->assertCount(1, $results);
        $this->assertEquals('Wien', $results[0]['name']);
        $this->assertEquals(48.2082, $results[0]['latitude']);
        $this->assertEquals(16.3725, $results[0]['longitude']);
        $this->assertEquals('AT', $results[0]['country_code']);
    }

    public function test_search_returns_empty_array_when_provider_has_no_matches(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response(['features' => []]),
        ]);

        Cache::flush();

        $service = new GeocodingService;
        $results = $service->search('Wien');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_search_throws_when_provider_responds_with_error_status(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response(status: 500),
        ]);

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->search('Wien');
    }

    public function test_search_throws_on_invalid_provider_response(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'invalid' => 'response',
            ]),
        ]);

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->search('Wien');
    }

    public function test_search_filters_results_without_coordinates(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'geometry' => [
                            'coordinates' => [16.3725, 48.2082],
                        ],
                        'properties' => [
                            'name' => 'Wien',
                            'countrycode' => 'AT',
                        ],
                    ],
                    [
                        'geometry' => null,
                        'properties' => [
                            'name' => 'Invalid',
                        ],
                    ],
                ],
            ]),
        ]);

        Cache::flush();

        $service = new GeocodingService;
        $results = $service->search('Wien');

        $this->assertCount(1, $results);
        $this->assertEquals('Wien', $results[0]['name']);
    }

    public function test_search_caches_results(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [
                    [
                        'geometry' => [
                            'coordinates' => [16.3725, 48.2082],
                        ],
                        'properties' => [
                            'name' => 'Wien',
                            'countrycode' => 'AT',
                        ],
                    ],
                ],
            ]),
        ]);

        Cache::flush();

        $service = new GeocodingService;
        $service->search('Wien');

        Http::assertSentCount(1);

        $service->search('Wien');

        Http::assertSentCount(1);
    }

    public function test_search_sends_query_to_configured_photon_url(): void
    {
        config(['services.photon.url' => 'https://photon.example.test/api']);

        Http::fake([
            'https://photon.example.test/api*' => Http::response(['features' => []]),
        ]);

        Cache::flush();

        $service = new GeocodingService;
        $service->search('Wien');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://photon.example.test/api')
                && $request['q'] === 'Wien'
                && $request['limit'] === 10;
        });
    }

    public function test_search_throws_on_connection_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->search('Wien');
    }

    public function test_search_does_not_cache_provider_failures(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response(status: 503),
        ]);

        Cache::flush();

        $service = new GeocodingService;

        foreach ([1, 2] as $_) {
            try {
                $service->search('Wien');
                $this->fail('The provider failure should be reported to the caller.');
            } catch (GeocodingProviderException) {
                // Expected: the failure is reported, never cached.
            }
        }

        Http::assertSentCount(2);
    }

    public function test_reverse_country_code_returns_country_reported_by_photon(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => [
                    [
                        'properties' => ['countrycode' => 'AT'],
                    ],
                ],
            ]),
        ]);

        Cache::flush();

        $countryCode = (new GeocodingService)->reverseCountryCode(48.2082, 16.3725);

        $this->assertSame('AT', $countryCode);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://photon.komoot.io/reverse')
                && (float) $request['lat'] === 48.2082
                && (float) $request['lon'] === 16.3725
                && (int) $request['limit'] === 1;
        });
    }

    public function test_reverse_country_code_returns_null_when_provider_has_no_match(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response(['features' => []]),
        ]);

        Cache::flush();

        $countryCode = (new GeocodingService)->reverseCountryCode(48.2082, 16.3725);

        $this->assertNull($countryCode);
    }

    public function test_reverse_country_code_throws_when_provider_responds_with_error_status(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response(status: 500),
        ]);

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->reverseCountryCode(48.2082, 16.3725);
    }

    public function test_reverse_country_code_throws_on_invalid_provider_response(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'invalid' => 'response',
            ]),
        ]);

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->reverseCountryCode(48.2082, 16.3725);
    }

    public function test_reverse_country_code_throws_on_connection_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->reverseCountryCode(48.2082, 16.3725);
    }

    public function test_reverse_country_code_sends_coordinates_to_configured_photon_url(): void
    {
        config(['services.photon.reverse_url' => 'https://photon.example.test/reverse']);

        Http::fake([
            'https://photon.example.test/reverse*' => Http::response(['features' => []]),
        ]);

        Cache::flush();

        (new GeocodingService)->reverseCountryCode(52.52, 13.405);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://photon.example.test/reverse')
                && (float) $request['lat'] === 52.52
                && (float) $request['lon'] === 13.405;
        });
    }

    public function test_reverse_country_code_returns_null_when_feature_has_no_properties(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => [[]],
            ]),
        ]);

        Cache::flush();

        $this->assertNull((new GeocodingService)->reverseCountryCode(48.2082, 16.3725));
    }

    public function test_reverse_country_code_throws_when_feature_is_not_an_object(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => ['AT'],
            ]),
        ]);

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->reverseCountryCode(48.2082, 16.3725);
    }

    public function test_reverse_country_code_throws_when_properties_are_not_an_object(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => [
                    ['properties' => 'AT'],
                ],
            ]),
        ]);

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->reverseCountryCode(48.2082, 16.3725);
    }

    public function test_reverse_country_code_throws_when_country_code_is_not_a_string(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => [
                    ['properties' => ['countrycode' => ['AT']]],
                ],
            ]),
        ]);

        Cache::flush();

        $this->expectException(GeocodingProviderException::class);

        (new GeocodingService)->reverseCountryCode(48.2082, 16.3725);
    }

    public function test_reverse_country_code_caches_results_per_rounded_coordinate(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => [
                    [
                        'properties' => ['countrycode' => 'AT'],
                    ],
                ],
            ]),
        ]);

        Cache::flush();

        $service = new GeocodingService;

        $service->reverseCountryCode(48.2082, 16.3725);
        $service->reverseCountryCode(48.20821, 16.37251);
        $service->reverseCountryCode(52.5200, 13.4050);

        Http::assertSentCount(2);
    }

    public function test_reverse_country_code_caches_coordinates_without_a_country(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response(['features' => []]),
        ]);

        Cache::flush();

        $service = new GeocodingService;

        $this->assertNull($service->reverseCountryCode(0.0, 0.0));
        $this->assertNull($service->reverseCountryCode(0.0, 0.0));

        Http::assertSentCount(1);
    }

    public function test_reverse_country_code_does_not_cache_provider_failures(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response(status: 503),
        ]);

        Cache::flush();

        $service = new GeocodingService;

        foreach ([1, 2] as $_) {
            try {
                $service->reverseCountryCode(48.2082, 16.3725);
                $this->fail('The provider failure should be reported to the caller.');
            } catch (GeocodingProviderException) {
                // Expected: the failure is reported, never cached.
            }
        }

        Http::assertSentCount(2);
    }
}
