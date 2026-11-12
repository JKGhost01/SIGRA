<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Aplica el idioma elegido por el usuario (cookie sigra_locale).
 *
 * Si no hay cookie, CodeIgniter negocia el locale con Accept-Language
 * usando Config\App::$supportedLocales.
 */
class LocaleFilter implements FilterInterface
{
    private const SUPPORTED = ['es', 'en'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $locale = (string) ($request->getCookie('sigra_locale') ?? '');
        if (! in_array($locale, self::SUPPORTED, true)) {
            return;
        }

        $request->setLocale($locale);
        service('language')->setLocale($locale);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action required.
    }
}
