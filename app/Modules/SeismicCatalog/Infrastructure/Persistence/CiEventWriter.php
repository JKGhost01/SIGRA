<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Infrastructure\Persistence;

final class CiEventWriter implements \App\Modules\SeismicCatalog\Application\Contracts\EventWriter
{
    public function __construct(private readonly \App\Models\SismoModel $model) {}
    public function save(array $events): int
    {
        $db = db_connect();
        if (!$db->transBegin()) throw new \RuntimeException('Cannot start catalog transaction.');
        try {
            $saved = $this->model->guardarEventos($events);
            if (!$db->transStatus()) throw new \RuntimeException('Catalog transaction failed.');
            $db->transCommit();
            return $saved;
        } catch (\Throwable $error) { $db->transRollback(); throw $error; }
    }
}
