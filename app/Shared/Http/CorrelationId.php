<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\{RequestInterface, ResponseInterface};
final class CorrelationId implements FilterInterface
{
    private string $id = '';
    public function before(RequestInterface $request, $arguments = null)
    {
        $this->id = bin2hex(random_bytes(16));
        service('response')->setHeader('X-Request-ID', $this->id);
        return null;
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    { if ($this->id !== '') $response->setHeader('X-Request-ID', $this->id); }
}
