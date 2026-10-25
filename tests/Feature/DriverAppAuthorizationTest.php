<?php

namespace Tests\Feature;

use Tests\TestCase;

class DriverAppAuthorizationTest extends TestCase
{
    public function test_driver_app_endpoints_require_authentication(): void
    {
        $requests = [
            ['GET', '/api/v1/driver/me'],
            ['GET', '/api/v1/driver/vehicles?driver_profile_id=1'],
            ['GET', '/api/v1/driver/tasks'],
            ['GET', '/api/v1/driver/tasks/summary'],
            ['GET', '/api/v1/driver/tasks/1'],
            ['GET', '/api/v1/driver/tasks/1/events'],
            ['POST', '/api/v1/driver/tasks/1/events'],
            ['GET', '/api/v1/driver/shifts/current'],
            ['POST', '/api/v1/driver/shifts/start'],
            ['POST', '/api/v1/driver/shifts/1/end'],
        ];

        foreach ($requests as [$method, $uri]) {
            $response = $this->json($method, $uri);

            $response->assertUnauthorized(
                sprintf('%s %s must reject unauthenticated requests.', $method, $uri),
            );
        }
    }
}
