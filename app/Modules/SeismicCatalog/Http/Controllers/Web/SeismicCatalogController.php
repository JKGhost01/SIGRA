<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Http\Controllers\Web;

use App\Controllers\BaseController;
use App\Modules\SeismicCatalog\Presentation\ViewModels\ListEventsViewModel;
use App\Shared\Http\ApiInput;
final class SeismicCatalogController extends BaseController
{
    public function index(): string
    {
        $vm = ListEventsViewModel::fromResult(service('listEvents')->recent());
        $mapEngine = config(\Config\SeismicMap::class)->engine;
        $requestedEngine = $this->request->getGet('map');
        if (in_array($requestedEngine, ['cesium', 'leaflet'], true)) $mapEngine = $requestedEngine;
        return view('App\Modules\SeismicCatalog\Presentation\Views\sismos\list\index', [
            'vm' => $vm, 'locale' => $this->request->getLocale(), 'message' => session()->getFlashdata('mensaje'),
            'mapEngine' => $mapEngine,
        ]);
    }
    public function detail(string $id)
    {
        $event = service('getEvent')->handle($id);
        if ($event === null) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        return view('App\Modules\SeismicCatalog\Presentation\Views\sismos\detail\index', ['datos' => $event->record]);
    }
    public function history(): string
    {
        $ordenes = ['fecha_hora' => lang('Sigra.colDate'), 'magnitud' => lang('Sigra.colMagnitude'),
            'profundidad' => lang('Sigra.colDepth'), 'ubicacion' => lang('Sigra.colLocation'), 'fuente' => lang('Sigra.colSource')];
        $order = $this->request->getGet('orden');
        $orden = is_string($order) && isset($ordenes[$order]) ? $order : 'fecha_hora';
        $direction = $this->request->getGet('direccion');
        $direccion = is_string($direction) && in_array(strtoupper($direction), ['ASC', 'DESC'], true) ? strtoupper($direction) : 'DESC';
        $filtros = [];
        foreach (['desde', 'hasta'] as $key) {
            $value = $this->request->getGet($key);
            $value = is_string($value) ? trim($value) : '';
            $parts = explode('-', $value);
            $filtros[$key] = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
                && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]) ? $value : '';
        }
        $pageValue = $this->request->getGet('page');
        $page = is_string($pageValue) ? filter_var($pageValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $sizeValue = $this->request->getGet('per_page');
        $perPage = is_string($sizeValue) && in_array($sizeValue, ['20', '50', '100'], true) ? (int) $sizeValue : 20;
        $result = service('listHistory')->page($filtros, $orden, $direccion, $page ?: 1, $perPage);
        $pagination = $result->pagination;
        $links = [];
        foreach (array_unique([1, max(1, $pagination->page - 1), $pagination->page, min($pagination->pages, $pagination->page + 1), $pagination->pages]) as $number) {
            $links[$number] = site_url('sismos/historico') . '?' . http_build_query($filtros + [
                'orden' => $orden, 'direccion' => $direccion, 'per_page' => $perPage, 'page' => $number]);
        }
        return view('App\Modules\SeismicCatalog\Presentation\Views\sismos\historico\index', [
            'ordenes' => $ordenes, 'orden' => $orden, 'direccion' => $direccion,
            'filtros' => $filtros, 'eventos' => array_map(static fn($dto) => $dto->record, $result->events),
            'pagination' => $pagination, 'pageLinks' => $links,
        ]);
    }
    public function globales() { return redirect()->to(site_url('sismos'), 301); }
    public function denied() { return $this->response->setStatusCode(403)->setBody(esc(lang('Sigra.denyAccessTitle'))); }
}
