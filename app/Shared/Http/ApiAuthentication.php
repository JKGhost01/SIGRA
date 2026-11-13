<?php

declare(strict_types=1);

namespace App\Shared\Http;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class ApiAuthentication implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $network = ($arguments[0] ?? '') === 'network';
        $expected = $network ? config(\Config\Red::class)->appKey : config(\Config\Sigra::class)->apiToken;
        $received = $network ? trim($request->getHeaderLine('X-SIGRA-KEY')) : '';
        if ($received === '' && preg_match('/^Bearer[ \t]+([^ \t]+)$/i', trim($request->getHeaderLine('Authorization')), $m)) $received = $m[1];
        $allowed = $expected === '' ? ENVIRONMENT !== 'production' : ($received !== '' && hash_equals($expected, $received));
        if ($allowed) return null;
        $sigra = config(\Config\Sigra::class);
        $response = service('response')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setHeader('X-SIGRA-Version', $sigra->version)->setHeader('X-API-Version', $sigra->apiVersion);
        return ErrorResponder::respond($response, 401,
            $network ? 'Clave de aplicación inválida o faltante.' : lang('Sigra.apiInvalidToken'),
            $response->getHeaderLine('X-Request-ID') ?: bin2hex(random_bytes(16)), str_contains($request->getUri()->getPath(), '/api/v1/'));
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
