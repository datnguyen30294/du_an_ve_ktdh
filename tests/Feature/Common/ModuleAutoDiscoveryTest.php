<?php

namespace Tests\Feature\Common;

use App\Modules\Skeleton\Providers\SkeletonServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ModuleAutoDiscoveryTest extends TestCase
{
    #[Test]
    public function test_skeleton_service_provider_is_registered(): void
    {
        $loadedProviders = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(SkeletonServiceProvider::class, $loadedProviders);
    }

    #[Test]
    public function test_api_health_route_is_accessible(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['success', 'message', 'timestamp']);
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function test_skeleton_module_route_is_accessible(): void
    {
        $response = $this->getJson('/api/v1/skeleton/health');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'success' => true,
            'module' => 'Skeleton',
        ]);
    }
}
