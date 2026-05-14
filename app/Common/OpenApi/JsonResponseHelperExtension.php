<?php

namespace App\Common\OpenApi;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Post-processes Scramble's inferred responses to ensure the paginated
 * response format matches our JsonResponseHelper structure.
 *
 * Fixes:
 * - Paginated `data` field: converts from `object` to `array` type
 * - Paginated `meta` and `links`: ensures correct structure
 */
class JsonResponseHelperExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        foreach ($operation->responses as $response) {
            if (! $response instanceof Response) {
                continue;
            }

            $this->addSuccessField($response);
            $this->fixPaginatedDataType($response);
        }
    }

    /**
     * Add `success: true` to all 2xx responses (injected by BaseResource::with()).
     */
    private function addSuccessField(Response $response): void
    {
        $code = (int) $response->code;

        if ($code < 200 || $code >= 300) {
            return;
        }

        if (! isset($response->content['application/json'])) {
            return;
        }

        $schema = $response->content['application/json'];

        if (! $schema instanceof Schema || ! $schema->type instanceof OpenApiObjectType) {
            return;
        }

        $rootType = $schema->type;

        if (! $rootType->hasProperty('success')) {
            $rootType->addProperty('success', (new BooleanType)->example(true));
            $rootType->setRequired([...($rootType->required ?? []), 'success']);
        }
    }

    /**
     * Fix paginated responses where `data` is inferred as `object` instead of `array`.
     */
    private function fixPaginatedDataType(Response $response): void
    {
        if (! isset($response->content['application/json'])) {
            return;
        }

        $schema = $response->content['application/json'];

        if (! $schema instanceof Schema || ! $schema->type instanceof OpenApiObjectType) {
            return;
        }

        $rootType = $schema->type;

        if (! $rootType->hasProperty('meta') || ! $rootType->hasProperty('data')) {
            return;
        }

        $dataType = $rootType->getProperty('data');

        if (! $dataType instanceof OpenApiObjectType || ! $dataType->additionalProperties) {
            return;
        }

        $itemType = $dataType->additionalProperties;

        $arrayType = (new OpenApiArrayType)->setItems($itemType);
        $rootType->addProperty('data', $arrayType);

        $this->ensureMetaStructure($rootType);
        $this->ensureLinksStructure($rootType);
    }

    /**
     * Ensure meta has the correct structure with proper types.
     */
    private function ensureMetaStructure(OpenApiObjectType $rootType): void
    {
        if (! $rootType->hasProperty('meta')) {
            return;
        }

        $metaType = $rootType->getProperty('meta');

        if (! $metaType instanceof OpenApiObjectType) {
            return;
        }

        $expectedFields = ['current_page', 'last_page', 'per_page', 'total'];

        foreach ($expectedFields as $field) {
            if (! $metaType->hasProperty($field)) {
                $metaType->addProperty($field, new IntegerType);
            }
        }

        foreach (['from', 'to'] as $field) {
            if (! $metaType->hasProperty($field)) {
                $metaType->addProperty($field, (new IntegerType)->nullable(true));
            }
        }
    }

    /**
     * Ensure links has the correct structure with nullable string types.
     */
    private function ensureLinksStructure(OpenApiObjectType $rootType): void
    {
        if (! $rootType->hasProperty('links')) {
            return;
        }

        $linksType = $rootType->getProperty('links');

        if (! $linksType instanceof OpenApiObjectType) {
            return;
        }

        foreach (['first', 'last', 'prev', 'next'] as $field) {
            if (! $linksType->hasProperty($field)) {
                $linksType->addProperty($field, (new StringType)->nullable(true));
            }
        }
    }
}
