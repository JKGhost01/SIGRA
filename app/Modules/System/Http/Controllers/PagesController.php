<?php

declare(strict_types=1);

namespace App\Modules\System\Http\Controllers;

final class PagesController extends \App\Controllers\BaseController
{
    public function about(): string
    {
        $this->response->setHeader('Cache-Control', 'no-store, max-age=0');
        return view('App\\Modules\\System\\Presentation\\Views\\acerca-de\\index', [
            'mobile' => service('mobileRelease')->snapshot(),
            'sigra' => config(\Config\Sigra::class), 'providerHealth' => service('providerHealth')->snapshot(),
        ]);
    }
    public function mobile(): string
    {
        $this->response->setHeader('Cache-Control', 'no-store, max-age=0');
        return view('App\\Modules\\System\\Presentation\\Views\\app-movil\\index', [
            'mobile' => service('mobileRelease')->snapshot(),
        ]);
    }
    public function privacy(): string { return $this->legal('privacy'); }
    public function cookies(): string { return $this->legal('cookies'); }
    public function terms(): string { return $this->legal('terms'); }
    public function processing(): string { return $this->legal('processing'); }

    private function legal(string $kind): string
    {
        $locale = $this->request->getLocale();
        $documents = \App\Modules\System\Presentation\ViewModels\LegalContent::documents($locale);
        $paths = ['privacy' => 'politica-privacidad', 'processing' => 'tratamiento-datos', 'cookies' => 'politica-cookies', 'terms' => 'terminos-uso'];
        return view('App\\Modules\\System\\Presentation\\Views\\legal\\index', [
            'kind' => $kind, 'document' => $documents[$kind], 'documents' => $documents,
            'legal' => config(\Config\Legal::class), 'english' => $locale === 'en',
            'links' => array_map(static fn(string $path): string => site_url($path), $paths),
        ]);
    }
}
