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
    public function privacy(): string { return view('App\\Modules\\System\\Presentation\\Views\\politica-privacidad\\index', ['sigra' => config(\Config\Sigra::class)]); }
    public function cookies(): string { return view('App\\Modules\\System\\Presentation\\Views\\politica-cookies\\index', ['sigra' => config(\Config\Sigra::class)]); }
    public function terms(): string { return view('App\\Modules\\System\\Presentation\\Views\\terminos-uso\\index', ['sigra' => config(\Config\Sigra::class)]); }
}
