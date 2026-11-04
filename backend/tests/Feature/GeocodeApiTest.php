<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GeocodeApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_geocode_endpoint_returns_results(): void
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

        $response = $this->getJson('/api/v1/geocode?q=Wien');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'name',
                        'latitude',
                        'longitude',
                        'country_code',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'name' => 'Wien',
                'latitude' => 48.2082,
                'longitude' => 16.3725,
                'country_code' => 'AT',
            ]);
    }

    public function test_geocode_endpoint_returns_null_for_country_code_not_assigned_in_iso(): void
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
                            'countrycode' => 'ZZ',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/v1/geocode?q=Wien')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Wien')
            ->assertJsonPath('data.0.country_code', null);
    }

    public function test_geocode_endpoint_returns_null_for_cached_country_code_not_assigned_in_iso(): void
    {
        Http::fake();

        Cache::put('geocode:'.md5('Wien'), [
            [
                'name' => 'Wien',
                'latitude' => 48.2082,
                'longitude' => 16.3725,
                'country_code' => 'ZZ',
            ],
        ], now()->addHours(24));

        $this->getJson('/api/v1/geocode?q=Wien')
            ->assertOk()
            ->assertJsonPath('data.0.country_code', null);

        Http::assertNothingSent();
    }

    public function test_geocode_endpoint_requires_query_parameter(): void
    {
        $response = $this->getJson('/api/v1/geocode');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_geocode_endpoint_validates_min_length(): void
    {
        $response = $this->getJson('/api/v1/geocode?q=W');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_geocode_endpoint_validates_max_length(): void
    {
        $response = $this->getJson('/api/v1/geocode?q='.str_repeat('a', 256));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_geocode_endpoint_returns_empty_array_on_no_results(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response([
                'features' => [],
            ]),
        ]);

        $response = $this->getJson('/api/v1/geocode?q=nonexistent');

        $response->assertStatus(200)
            ->assertExactJson(['data' => []]);
    }

    public function test_geocode_endpoint_returns_bad_gateway_when_provider_fails(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response(status: 503),
        ]);

        $response = $this->getJson('/api/v1/geocode?q=Wien');

        $response->assertStatus(502)
            ->assertExactJson(['message' => 'Usługa geokodowania jest chwilowo niedostępna.']);
    }

    public function test_geocode_endpoint_returns_bad_gateway_on_connection_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        $response = $this->getJson('/api/v1/geocode?q=Wien');

        $response->assertStatus(502)
            ->assertExactJson(['message' => 'Usługa geokodowania jest chwilowo niedostępna.']);
    }

    public function test_geocode_endpoint_is_rate_limited(): void
    {
        Http::fake([
            'https://photon.komoot.io/api*' => Http::response(['features' => []]),
        ]);

        $configured = (RateLimiter::limiter('geocode'))(Request::create('/api/v1/geocode', 'GET'));
        $this->assertSame(60, $configured->maxAttempts);

        $testKey = 'geocode-test-'.uniqid();
        RateLimiter::for('geocode', fn () => Limit::perMinute(2)->by($testKey));

        $this->getJson('/api/v1/geocode?q=Wien')->assertOk();
        $this->getJson('/api/v1/geocode?q=Wien')->assertOk();
        $this->getJson('/api/v1/geocode?q=Wien')
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }
}
