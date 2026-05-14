<?php

namespace App\Common\OpenApi;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;

class CustomAuthenticationExceptionExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(AuthenticationException::class);
    }

    public function toResponse(Type $type): Response
    {
        $responseBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty('success', (new OpenApiTypes\BooleanType)->example(false))
            ->addProperty('message', (new OpenApiTypes\StringType)->setDescription('Error overview.')->example('Unauthenticated.'))
            ->addProperty('error_code', (new OpenApiTypes\StringType)->setDescription('Machine-readable error code.')->example('UNAUTHENTICATED'))
            ->setRequired(['success', 'message', 'error_code']);

        return Response::make(401)
            ->setDescription('Unauthenticated')
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
