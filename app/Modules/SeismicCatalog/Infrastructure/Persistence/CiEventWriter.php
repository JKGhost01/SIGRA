<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Infrastructure\Persistence;

final class CiEventWriter implements \App\Modules\SeismicCatalog\Application\Contracts\EventWriter
{
    public function __construct(private readonly \App\Models\SismoModel $model) {}
    public function save(array $events): int
    {
        if ($events === []) return 0;
        $db = db_connect();
        $lock = 'sigra-reports:' . substr(hash('sha256', $db->database), 0, 40);
        if ((int) $db->query('SELECT GET_LOCK(?, 15) AS acquired', [$lock])->getRowArray()['acquired'] !== 1) {
            throw new \RuntimeException('Cannot lock catalog refresh.');
        }
        try {
            if (!$db->transBegin()) throw new \RuntimeException('Cannot start catalog transaction.');
            $q = config(\Config\Quakes::class);
            $aliases = [];
            if ($q->dedupeCrossSource) {
                $consolidator = new \App\Modules\SeismicIngestion\Domain\Services\CatalogConsolidator(
                    new \App\Modules\SeismicIngestion\Domain\Services\EventDeduplicator($q->dedupeSeconds, $q->dedupeDistanceKm, $q->dedupeMagnitudeDelta));
                $result = $consolidator->consolidate($this->model->obtenerParaConsolidar($events, $q->dedupeSeconds), $events);
                $events = $result['events'];
                $aliases = $result['aliases'];
            }
            $saved = $this->model->guardarEventos($events);
            $this->model->vincularAlias($aliases);
            if (!$db->transStatus()) throw new \RuntimeException('Catalog transaction failed.');
            $db->transCommit();
            return $saved;
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
        finally { $db->query('SELECT RELEASE_LOCK(?)', [$lock]); }
    }
}
