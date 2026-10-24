<?php

namespace Tests\Feature;

use Tests\TestCase;

class PlatformHealthTest extends TestCase
{
    public function test_api_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('api_version', 'v1');
    }

    public function test_api_about_endpoint_lists_initial_domains(): void
    {
        $this->getJson('/api/v1/about')
            ->assertOk()
            ->assertJsonPath('status', 'foundation')
            ->assertJsonFragment(['identity', 'organizations', 'catalog']);
    }
}
