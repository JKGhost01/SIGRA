<?php

namespace App\Filters;

use App\Libraries\ReportFormGuard;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CitizenReportGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        service('response')->setHeader('Cache-Control', 'no-store, max-age=0');
        if (strtoupper($request->getMethod()) !== 'POST') return null;
        if ((int) $request->getHeaderLine('Content-Length') > 8192 || strlen($request->getBody()) > 8192) {
            return service('response')->setStatusCode(413)->setBody(esc(lang('Sigra.reportRejected')));
        }
        if (!ReportFormGuard::verify($request->getPost() ?? [])) {
            return service('response')->setStatusCode(403)->setBody(
                esc(lang('Sigra.reportRejected')) . ' <a href="' . esc(site_url('sismos/create'), 'attr') . '">'
                . esc(lang('Sigra.navReport')) . '</a>');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
