<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Http\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Shared\Http\ApiInput;
use App\Modules\SeismicCatalog\Http\Requests\ListEventsRequest;
use App\Modules\SeismicCatalog\Http\Resources\SeismicEventResource;
use App\Modules\Alerting\Domain\MagnitudeDisplay;

class SeismicEventsController extends BaseController
{
    use \App\Shared\Http\ApiResponses;
    public function recent()
    {
        return $this->responderApi(function () {
            $query = (new ListEventsRequest())->parse($this->request);
            $events = service('listEvents')->handle($query);
            $resource = new SeismicEventResource();
            return $this->response->setJSON([
                'status' => 'ok', 'count' => count($events), 'hours' => $query->hours,
                'origin' => $query->latitude !== null ? ['lat' => $query->latitude, 'lng' => $query->longitude, 'radius_km' => $query->radiusKm] : null,
                'generated_at' => date('c'),
                'events' => array_map(fn($event) => $resource->serialize($event, $query->latitude, $query->longitude), $events),
            ]);
        });
    }
    public function detail(string $id)
    {
        return $this->responderApi(function () use ($id) {
            [$lat, $lng] = $this->origin();
            $event = service('getEvent')->handle($id);
            if ($event === null) return $this->apiFailure(404, lang('Sigra.apiEventNotFound'));
            return $this->response->setJSON(['status' => 'ok',
                'event' => (new SeismicEventResource())->serialize($event, $lat, $lng), 'generated_at' => date('c')]);
        });
    }
    public function latest() { return $this->lastResponse(false); }
    public function alerts() { return $this->lastResponse(true); }
    private function lastResponse(bool $includeDisplayLevel)
    {
        return $this->responderApi(function () use ($includeDisplayLevel) {
            [$lat, $lng] = $this->origin();
            $last = service('listEvents')->recent()[0] ?? null;
            $event = $last === null ? null : (new SeismicEventResource())->serialize($last, $lat, $lng);
            $payload = ['status' => 'ok', 'generated_at' => date('c')];
            if ($includeDisplayLevel) {
                $payload['alerta'] = $event['alerta'] ?? 'sin_datos';
                $payload['ultimo'] = $event;
            } else $payload['event'] = $event;
            return $this->response->setJSON($payload);
        });
    }
    private function origin(): array
    { return ApiInput::coordinates($this->request->getGet('lat'), $this->request->getGet('lng')); }
}
