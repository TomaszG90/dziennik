<?php

namespace Tests\Unit;

use App\Exceptions\RoutingProviderException;
use App\Services\RoutingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class RoutingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.routing.url' => 'https://api.openrouteservice.org/v2/directions/driving-car',
            'services.routing.api_key' => 'test-api-key',
            'services.routing.timeout' => 10,
        ]);
    }

    private function validResponse(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'summary' => [
                            'distance' => 12500.5,
                            'duration' => 900,
                        ],
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [16.9252, 52.4064],
                            [17.0385, 52.4083],
                            [17.1234, 52.4100],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_calculate_car_route_returns_distance_and_geojson_geometry(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(
                $this->validResponse()
            ),
        ]);

        $result = (new RoutingService)->calculateCarRoute(
            52.4064,
            16.9252,
            52.4100,
            17.1234
        );

        $this->assertSame(12500.5, $result['distance']);
        $this->assertSame('LineString', $result['geometry']['type']);
        $this->assertCount(3, $result['geometry']['coordinates']);
        $this->assertSame(
            [16.9252, 52.4064],
            $result['geometry']['coordinates'][0]
        );
    }

    public function test_calculate_car_route_sends_coordinates_in_longitude_latitude_order(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(
                $this->validResponse()
            ),
        ]);

        (new RoutingService)->calculateCarRoute(
            52.4064,
            16.9252,
            52.4100,
            17.1234
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openrouteservice.org/v2/directions/driving-car/geojson'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'test-api-key')
                && $request['coordinates'] === [
                    [16.9252, 52.4064],
                    [17.1234, 52.4100],
                ];
        });
    }

    public function test_calculate_car_route_throws_when_api_key_is_missing(): void
    {
        config(['services.routing.api_key' => null]);

        Http::fake();

        $this->expectException(RoutingProviderException::class);

        (new RoutingService)->calculateCarRoute(
            52.4064,
            16.9252,
            52.4100,
            17.1234
        );
    }

    public function test_calculate_car_route_throws_when_provider_returns_server_error(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(
                ['error' => 'Internal server error'],
                500
            ),
        ]);

        $this->expectException(RoutingProviderException::class);

        (new RoutingService)->calculateCarRoute(
            52.4064,
            16.9252,
            52.4100,
            17.1234
        );
    }

    public function test_calculate_car_route_throws_when_provider_rate_limit_is_exceeded(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response(
                ['error' => 'Rate limit exceeded'],
                429
            ),
        ]);

        $this->expectException(RoutingProviderException::class);

        try {
            (new RoutingService)->calculateCarRoute(
                52.4064,
                16.9252,
                52.4100,
                17.1234
            );
        } catch (RoutingProviderException $exception) {
            $this->assertSame(
                'The routing provider rate limit has been exceeded.',
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    public function test_calculate_car_route_throws_on_connection_error(): void
    {
        Http::fake(
            fn () => throw new ConnectionException('Operation timed out')
        );

        $this->expectException(RoutingProviderException::class);

        (new RoutingService)->calculateCarRoute(
            52.4064,
            16.9252,
            52.4100,
            17.1234
        );
    }

    public function test_calculate_car_route_throws_on_invalid_provider_response(): void
    {
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'invalid' => 'response',
            ]),
        ]);

        $this->expectException(RoutingProviderException::class);

        (new RoutingService)->calculateCarRoute(
            52.4064,
            16.9252,
            52.4100,
            17.1234
        );
    }

    public function test_calculate_car_route_throws_when_geometry_is_invalid(): void
    {
        $response = $this->validResponse();
        $response['features'][0]['geometry'] = [
            'type' => 'Point',
            'coordinates' => [16.9252, 52.4064],
        ];

        Http::fake([
            'api.openrouteservice.org/*' => Http::response($response),
        ]);

        $this->expectException(RoutingProviderException::class);

        (new RoutingService)->calculateCarRoute(
            52.4064,
            16.9252,
            52.4100,
            17.1234
        );
    }

    public function test_calculate_car_route_throws_when_distance_is_invalid(): void
    {
        $response = $this->validResponse();
        $response['features'][0]['properties']['summary']['distance'] = -100;

        Http::fake([
            'api.openrouteservice.org/*' => Http::response($response),
        ]);

        $this->expectException(RoutingProviderException::class);

        (new RoutingService)->calculateCarRoute(
            52.4064,
            16.9252,
            52.4100,
            17.1234
        );
    }

    public function test_calculate_car_route_throws_when_coordinates_are_invalid(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        (new RoutingService)->calculateCarRoute(
            91.0,
            16.9252,
            52.4100,
            17.1234
        );
    }

    public function test_calculate_car_route_does_not_send_request_when_coordinates_are_invalid(): void
    {
        Http::fake();

        try {
            (new RoutingService)->calculateCarRoute(
                52.4064,
                181.0,
                52.4100,
                17.1234
            );
        } catch (InvalidArgumentException) {
            // Expected.
        }

        Http::assertNothingSent();
    }
}
