<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/** Shared response boundary for both API controllers, including authentication. */
trait ApiResponses
{
    protected function responderApi(callable $action): ResponseInterface
    {
        $sigra = config(\Config\Sigra::class);
        $this->response->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setHeader('X-SIGRA-Version', $sigra->version)
            ->setHeader('X-API-Version', $sigra->apiVersion);

        try {
            return $action();
        } catch (RedApiException $error) {
            return $this->response->setStatusCode($error->statusCode)->setJSON([
                'status' => 'error', 'message' => $error->getMessage(),
            ]);
        } catch (Throwable $error) {
            log_message('error', 'SIGRA API: {message}', ['message' => $error->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error', 'message' => 'Error interno de la API SIGRA.',
            ]);
        }
    }
}
