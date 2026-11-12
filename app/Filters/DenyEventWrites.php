<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Public visitors cannot edit/delete authoritative events. No admin login exists yet. */
class DenyEventWrites implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return service('response')->setStatusCode(403)->setBody(esc(lang('Sigra.reportOfficialOnly')));
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
