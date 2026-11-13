<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Infrastructure\Persistence;

use App\Models\SismoModel;
use App\Modules\SeismicCatalog\Application\Contracts\SeismicEventReader;
use App\Modules\SeismicCatalog\Application\DTO\SeismicEventDTO;
use App\Modules\SeismicCatalog\Application\Queries\ListEvents\ListEventsQuery;

final class CiSeismicEventRepository implements SeismicEventReader
{
    public function __construct(private readonly SismoModel $model) {}
    public function list(ListEventsQuery $q): array
    {
        return $this->records($this->model->obtenerRecientesFiltrados($q->hours, $q->limit, $q->minMagnitude,
            $q->source, $q->latitude, $q->longitude, $q->radiusKm, $q->order));
    }
    public function recent(int $hours): array { return $this->records($this->model->obtenerRecientes($hours)); }
    public function get(string $id): ?SeismicEventDTO
    {
        $row = $this->model->obtenerPorId($id);
        return $row === null ? null : new SeismicEventDTO($row);
    }
    public function history(array $filters, string $order, string $direction): array
    {
        return $this->records($this->model->obtenerHistorico($filters, $order, $direction));
    }
    private function records(array $rows): array { return array_map(static fn(array $r) => new SeismicEventDTO($r), $rows); }
}
