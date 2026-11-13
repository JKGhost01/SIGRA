<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Libraries\RedApiException;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/** Shared response boundary for both API controllers, including authentication. */
trait ApiResponses
{
    protected function responderApi(callable $action): ResponseInterface
    {
        $requestId = $this->response->getHeaderLine('X-Request-ID') ?: bin2hex(random_bytes(16));
        $this->response->setHeader('X-Request-ID', $requestId);
        $sigra = config(\Config\Sigra::class);
        $this->response->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setHeader('X-SIGRA-Version', $sigra->version)
            ->setHeader('X-API-Version', $sigra->apiVersion);

        try {
            return $action();
        } catch (\App\Shared\Application\OperationRejected $error) {
            return $this->apiFailure($error->statusCode, $error->getMessage(), $requestId);
        } catch (Throwable $error) {
            log_message('error', 'SIGRA API [{request_id}]: {message}', ['request_id' => $requestId, 'message' => $error->getMessage()]);
            return $this->apiFailure(500, 'Error interno de la API SIGRA.', $requestId);
        }
    }
    protected function apiFailure(int $status, string $message, ?string $requestId = null): ResponseInterface
    {
        return ErrorResponder::respond($this->response, $status, $message, $requestId ?? ($this->response->getHeaderLine('X-Request-ID') ?: bin2hex(random_bytes(16))),
            str_contains($this->request->getUri()->getPath(), '/api/v1/'));
    }
}
