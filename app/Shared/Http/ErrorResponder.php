<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\HTTP\ResponseInterface;
final class ErrorResponder
{
    public static function respond(ResponseInterface $response, int $status, string $message, string $requestId, bool $versioned): ResponseInterface
    {
        $payload = ['status' => 'error', 'message' => $message];
        if ($versioned) {
            $code = match ($status) {
                400 => 'INVALID_REQUEST', 401 => 'AUTHENTICATION_REQUIRED', 403 => 'PERMISSION_DENIED',
                404 => 'RESOURCE_NOT_FOUND', 409 => 'CONFLICT', 415 => 'UNSUPPORTED_MEDIA_TYPE',
                422 => 'VALIDATION_FAILED', 429 => 'RATE_LIMIT_EXCEEDED', 503 => 'SERVICE_UNAVAILABLE', default => 'INTERNAL_ERROR',
            };
            $payload = ['status' => 'error', 'error' => ['code' => $code, 'message' => $message], 'request_id' => $requestId];
        }
        return $response->setHeader('X-Request-ID', $requestId)->setStatusCode($status)->setJSON($payload);
    }
}
