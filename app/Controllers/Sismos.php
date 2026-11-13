<?php

declare(strict_types=1);

namespace App\Controllers;

/** Legacy PHP entry points; routes target the modular controllers directly. */
class Sismos extends BaseController
{
    private function delegate(string $class, string $method, array $args = [])
    {
        $controller = new $class();
        $controller->initController($this->request, $this->response, $this->logger);
        return $controller->$method(...$args);
    }
    public function list() { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Web\SeismicCatalogController::class, 'index'); }
    public function historico() { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Web\SeismicCatalogController::class, 'history'); }
    public function globales() { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Web\SeismicCatalogController::class, 'globales'); }
    public function create() { return $this->delegate(\App\Modules\CitizenReports\Http\Controllers\CitizenReportController::class, 'index'); }
    public function sync() { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Web\CatalogPollingController::class, 'index'); }
    public function apiHealth() { return $this->delegate(\App\Modules\System\Http\Controllers\HealthController::class, 'index'); }
    public function apiRecent() { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Api\V1\SeismicEventsController::class, 'recent'); }
    public function apiUltimo() { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Api\V1\SeismicEventsController::class, 'latest'); }
    public function apiAlertas() { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Api\V1\SeismicEventsController::class, 'alerts'); }
    public function acerca() { return $this->delegate(\App\Modules\System\Http\Controllers\PagesController::class, 'about'); }
    public function privacidad() { return $this->delegate(\App\Modules\System\Http\Controllers\PagesController::class, 'privacy'); }
    public function cookies() { return $this->delegate(\App\Modules\System\Http\Controllers\PagesController::class, 'cookies'); }
    public function terminos() { return $this->delegate(\App\Modules\System\Http\Controllers\PagesController::class, 'terms'); }
    public function detalle(string $id = '') { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Web\SeismicCatalogController::class, 'detail', [$id]); }
    public function edit(string $id = '') { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Web\SeismicCatalogController::class, 'denied', [$id]); }
    public function delete(string $id = '') { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Web\SeismicCatalogController::class, 'denied', [$id]); }
    public function apiDetalle(string $id = '') { return $this->delegate(\App\Modules\SeismicCatalog\Http\Controllers\Api\V1\SeismicEventsController::class, 'detail', [$id]); }
}
