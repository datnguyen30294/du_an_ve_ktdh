<?php

namespace App\Common\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JsonResponseHelper
{
    /**
     * Return an error response.
     *
     * @param  array<string, mixed>|null  $errors
     */
    public static function error(
        string $message = 'Error',
        int $statusCode = Response::HTTP_BAD_REQUEST,
        ?string $errorCode = null,
        ?array $errors = null,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}
