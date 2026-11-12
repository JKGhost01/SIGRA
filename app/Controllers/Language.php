<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Cambio de idioma de la interfaz.
 *
 * Ruta: GET /language/{locale}?to=...
 * Guarda la preferencia en una cookie y vuelve a la página de origen.
 */
class Language extends BaseController
{
    private const SUPPORTED = ['es', 'en'];

    public function change(string $locale)
    {
        $locale = in_array($locale, self::SUPPORTED, true) ? $locale : 'es';

        $to = trim((string) ($this->request->getGet('to') ?? ''));
        if ($to === ''
            || str_contains($to, '://')
            || str_starts_with($to, '//')
            || ! preg_match('#^[A-Za-z0-9\-_./?=&%+]*$#', $to)
        ) {
            $to = 'sismos';
        }

        $to = ltrim($to, '/');
        $to = preg_replace('#^index\.php/?#i', '', $to);
        if ($to === '') {
            $to = 'sismos';
        }

        return redirect()
            ->to(site_url($to))
            ->setCookie('sigra_locale', $locale, 60 * 60 * 24 * 365, '', '/', '', null, true, 'Lax');
    }
}
