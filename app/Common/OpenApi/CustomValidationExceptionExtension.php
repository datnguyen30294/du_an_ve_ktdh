<?php

namespace App\Common\OpenApi;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomValidationExceptionExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ValidationException::class);
    }

    public function toResponse(Type $type): Response
    {
        $responseBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty('success', (new OpenApiTypes\BooleanType)->example(false))
            ->addProperty('message', (new OpenApiTypes\StringType)->setDescription('Error overview.')->example('Validation failed.'))
            ->addProperty(
                'errors',
                (new OpenApiTypes\ObjectType)
                    ->setDescription('A detailed description of each field that failed validation.')
                    ->additionalProperties((new OpenApiTypes\ArrayType)->setItems(new OpenApiTypes\StringType))
            )
            ->setRequired(['success', 'message', 'errors']);

        return Response::make(422)
            ->setDescription('Validation error')
            ->setContent(
                'application/json',
                Schema::fromType($responseBodyType),
            );
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('responses', Str::start($type->name, '\\'), $this->components);
    }
}
