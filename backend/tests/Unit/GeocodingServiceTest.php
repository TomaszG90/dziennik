<?php

namespace Tests\Unit;

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

    public function test_search_returns_empty_array_on_failed_request(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response(status: 500),
        ]);

        Cache::flush();

        $service = new GeocodingService;
        $results = $service->search('Wien');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_search_returns_empty_array_on_invalid_response(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'invalid' => 'response',
            ]),
        ]);

        Cache::flush();

        $service = new GeocodingService;
        $results = $service->search('Wien');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
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

        $service = new GeocodingService();
        $service->search('Wien');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://photon.example.test/api')
                && $request['q'] === 'Wien'
                && $request['limit'] === 10;
        });
    }

    public function test_search_returns_empty_array_on_connection_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        Cache::flush();

        $service = new GeocodingService();
        $results = $service->search('Wien');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_search_does_not_cache_provider_failures(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response(status: 503),
        ]);

        Cache::flush();

        $service = new GeocodingService();
        $service->search('Wien');
        $service->search('Wien');

        Http::assertSentCount(2);
    }
}
