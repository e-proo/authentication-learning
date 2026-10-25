<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_health_endpoint_returns_api_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('api_version', 'v1')
            ->assertJsonStructure(['status', 'service', 'api_version']);
    }

    public function test_about_endpoint_lists_the_initial_domain_modules(): void
    {
        $response = $this->getJson('/api/v1/about');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'order-to-delivery-foundation')
            ->assertJsonPath('modules.0', 'identity')
            ->assertJsonPath('modules.1', 'organizations')
            ->assertJsonPath('modules.7', 'shipments')
            ->assertJsonStructure(['name', 'modules', 'status']);
    }

    public function test_current_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
