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
        return view('App\Modules\SeismicCatalog\Presentation\Views\sismos\list\index', [
            'vm' => $vm, 'locale' => $this->request->getLocale(), 'message' => session()->getFlashdata('mensaje'),
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
            $filtros[$key] = is_string($value) ? trim($value) : '';
        }
        $events = service('listHistory')->handle($filtros, $orden, $direccion);
        return view('App\Modules\SeismicCatalog\Presentation\Views\sismos\historico\index', [
            'ordenes' => $ordenes, 'orden' => $orden, 'direccion' => $direccion,
            'filtros' => $filtros, 'eventos' => array_map(static fn($dto) => $dto->record, $events),
        ]);
    }
    public function globales() { return redirect()->to(site_url('sismos'), 301); }
    public function denied() { return $this->response->setStatusCode(403)->setBody(esc(lang('Sigra.denyAccessTitle'))); }
}
