<?php

namespace Tests\Feature;

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    private array $spec = [];

    protected function setUp(): void
    {
        parent::setUp();

        $response = $this->withoutMiddleware(RestrictedDocsAccess::class)
            ->get('/docs/api.json');

        $this->spec = $response->json();
    }

    public function test_docs_ui_returns_200(): void
    {
        $response = $this->withoutMiddleware(RestrictedDocsAccess::class)
            ->get('/docs/api');

        $response->assertStatus(200);
    }

    public function test_docs_json_returns_valid_openapi_spec(): void
    {
        $response = $this->withoutMiddleware(RestrictedDocsAccess::class)
            ->get('/docs/api.json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'openapi',
            'info' => ['title', 'version', 'description'],
            'paths',
        ]);

        $this->assertStringStartsWith('3.', $response->json('openapi'));
    }

    public function test_all_resource_paths_are_documented(): void
    {
        $expectedPaths = [
            '/pmc/departments',
            '/pmc/departments/{id}',
            '/pmc/job-titles',
            '/pmc/job-titles/{id}',
            '/pmc/projects',
            '/pmc/projects/{id}',
            '/pmc/roles',
            '/pmc/roles/{id}',
            '/pmc/accounts',
            '/pmc/accounts/{id}',
        ];

        $paths = array_keys($this->spec['paths']);

        foreach ($expectedPaths as $expectedPath) {
            $this->assertContains($expectedPath, $paths, "Path {$expectedPath} is not documented.");
        }
    }

    #[DataProvider('resourceProvider')]
    public function test_resource_has_crud_operations(string $collectionPath, string $itemPath): void
    {
        $paths = $this->spec['paths'];

        $this->assertArrayHasKey('get', $paths[$collectionPath], "{$collectionPath} is missing GET (index).");
        $this->assertArrayHasKey('post', $paths[$collectionPath], "{$collectionPath} is missing POST (store).");
        $this->assertArrayHasKey('get', $paths[$itemPath], "{$itemPath} is missing GET (show).");
        $this->assertArrayHasKey('put', $paths[$itemPath], "{$itemPath} is missing PUT (update).");
        $this->assertArrayHasKey('delete', $paths[$itemPath], "{$itemPath} is missing DELETE (destroy).");
    }

    public static function resourceProvider(): array
    {
        return [
            'departments' => ['/pmc/departments', '/pmc/departments/{id}'],
            'job-titles' => ['/pmc/job-titles', '/pmc/job-titles/{id}'],
            'projects' => ['/pmc/projects', '/pmc/projects/{id}'],
            'roles' => ['/pmc/roles', '/pmc/roles/{id}'],
            'accounts' => ['/pmc/accounts', '/pmc/accounts/{id}'],
        ];
    }

    #[DataProvider('resourceProvider')]
    public function test_index_endpoint_has_paginated_response(string $collectionPath): void
    {
        $indexOp = $this->spec['paths'][$collectionPath]['get'];
        $responseSchema = $indexOp['responses']['200']['content']['application/json']['schema'] ?? [];

        $this->assertArrayHasKey('data', $responseSchema['properties']);
        $this->assertArrayHasKey('meta', $responseSchema['properties']);
        $this->assertArrayHasKey('links', $responseSchema['properties']);
    }

    #[DataProvider('tagProvider')]
    public function test_endpoints_have_correct_tags(string $path, string $expectedTag): void
    {
        $paths = $this->spec['paths'];

        foreach ($paths[$path] as $operation) {
            $this->assertContains($expectedTag, $operation['tags'] ?? [], "Endpoint {$path} is missing tag '{$expectedTag}'.");
        }
    }

    public static function tagProvider(): array
    {
        return [
            'departments' => ['/pmc/departments', 'Departments'],
            'job-titles' => ['/pmc/job-titles', 'Job Titles'],
            'projects' => ['/pmc/projects', 'Projects'],
            'roles' => ['/pmc/roles', 'Roles'],
            'accounts' => ['/pmc/accounts', 'Accounts'],
        ];
    }
}
