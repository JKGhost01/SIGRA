<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Http\Controllers\Web;

class SensorNetworkController extends \App\Controllers\BaseController
{
    public function index(): string
    {
        return view('App\Modules\SensorNetwork\Presentation\Views\red\index', [
            'redConfig' => config(\Config\Red::class), 'locale' => $this->request->getLocale(),
        ]);
    }
}
