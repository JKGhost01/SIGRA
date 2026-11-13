<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Http\Controllers\Web;

use App\Modules\SeismicCatalog\Presentation\ViewModels\ListEventsViewModel;
final class CatalogPollingController extends \App\Controllers\BaseController
{
    public function index()
    {
        $refresh = new \App\Modules\SeismicIngestion\Application\DTO\CatalogRefreshResult();
        try {
            $refresh = service('catalogRefresh')->handle();
        } catch (\Throwable $error) {
            log_message('error', 'Catalog refresh unavailable: {message}', ['message' => $error->getMessage()]);
            $refresh = new \App\Modules\SeismicIngestion\Application\DTO\CatalogRefreshResult(
                errors: ['sync' => 'No se pudo actualizar; se conservan los eventos almacenados.']);
        }
        try {
            $vm = ListEventsViewModel::fromResult(service('listEvents')->recent());
            $events = array_map(static fn(array $event) => $event + ['estado' => 'automatico'], $vm->initialEvents);
            return $this->response->setHeader('Cache-Control', 'no-store, max-age=0')->setJSON([
                'ok' => true, 'synced' => $refresh->synced, 'timestamp' => $refresh->timestamp, 'message' => lang('Sigra.apiUpToDate'),
                'last_success_at' => $refresh->lastSuccessAt,
                'events' => count($events), 'saved' => $refresh->saved, 'sources' => $refresh->sources,
                'errors' => $refresh->errors, 'eventos' => $events,
            ]);
        } catch (\Throwable $error) {
            log_message('error', 'Catalog polling failed: {message}', ['message' => $error->getMessage()]);
            return $this->response->setStatusCode(502)->setJSON(['ok' => false, 'message' => lang('Sigra.apiSyncFailed')]);
        }
    }
}
