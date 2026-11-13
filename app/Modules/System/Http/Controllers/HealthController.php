<?php

declare(strict_types=1);

namespace App\Modules\System\Http\Controllers;

final class HealthController extends \App\Controllers\BaseController
{
    use \App\Shared\Http\ApiResponses;
    public function index()
    {
        return $this->responderApi(function () {
            $available = service('healthCheck')->handle();
            $config = config(\Config\Sigra::class);
            return $this->response->setStatusCode($available ? 200 : 503)->setJSON([
                'status' => $available ? 'ok' : 'degraded', 'service' => 'sigra',
                'version' => $config->version, 'release' => $config->releaseName, 'build' => $config->build,
                'api_version' => $config->apiVersion, 'database' => $available ? 'ok' : 'error', 'time' => date('c'),
            ]);
        });
    }
}
