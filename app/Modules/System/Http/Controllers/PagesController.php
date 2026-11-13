<?php

declare(strict_types=1);

namespace App\Modules\System\Http\Controllers;

final class PagesController extends \App\Controllers\BaseController
{
    public function about(): string { return view('App\\Modules\\System\\Presentation\\Views\\acerca-de\\index', ['sigra' => config(\Config\Sigra::class)]); }
    public function privacy(): string { return view('App\\Modules\\System\\Presentation\\Views\\politica-privacidad\\index', ['sigra' => config(\Config\Sigra::class)]); }
    public function cookies(): string { return view('App\\Modules\\System\\Presentation\\Views\\politica-cookies\\index', ['sigra' => config(\Config\Sigra::class)]); }
    public function terms(): string { return view('App\\Modules\\System\\Presentation\\Views\\terminos-uso\\index', ['sigra' => config(\Config\Sigra::class)]); }
}
