<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Modules\SeismicCatalog\Infrastructure\Persistence\CiSeismicEventRepository;
use App\Modules\SeismicCatalog\Application\Queries\ListHistory\ListHistoryHandler;

$db = db_connect();
$db->transBegin();
try {
    $rows = [];
    for ($index = 0; $index < 525; $index++) {
        $rows[] = ['id' => sprintf('QP-%04d', $index), 'fecha_hora' => '2001-01-01 12:00:00',
            'latitud' => 4.6, 'longitud' => -74.1, 'magnitud' => 4.2, 'profundidad' => 10,
            'ubicacion' => 'QA pagination', 'fuente' => 'SGC', 'estado' => 'automatico', 'sgc_metadata' => '{}'];
    }
    $db->table('sismos')->insertBatch($rows);
    $db->table('sismos')->insert(array_replace($rows[0], ['id' => 'QP-alias', 'canonical_event_id' => 'QP-0000']));
    $history = new ListHistoryHandler(new CiSeismicEventRepository(new App\Models\SismoModel($db)));
    $filters = ['desde' => '2001-01-01', 'hasta' => '2001-01-01'];
    $first = $history->page($filters, 'fecha_hora', 'DESC');
    $second = $history->page($filters, 'fecha_hora', 'DESC', 2);
    $last = $history->page($filters, 'fecha_hora', 'DESC', 9999);
    $ids = static fn($result) => array_map(static fn($dto) => $dto->record['id'], $result->events);
    expectArchitecture($first->pagination->total === 525 && count($first->events) === 20, 'History still caps the full catalog or counts aliases');
    expectArchitecture($ids($first)[0] === 'QP-0000' && $ids($second)[0] === 'QP-0020', 'Equal timestamps do not have stable pagination');
    expectArchitecture(array_intersect($ids($first), $ids($second)) === [], 'History pages overlap');
    expectArchitecture($last->pagination->page === 27 && $last->pagination->from === 521 && count($last->events) === 5,
        'Cannot reach events beyond the previous 500-row limit');
    expectArchitecture($ids($last) === ['QP-0520', 'QP-0521', 'QP-0522', 'QP-0523', 'QP-0524'], 'Last-page events are missing');
    $larger = $history->page($filters, 'magnitud', 'ASC', 6, 100);
    expectArchitecture($larger->pagination->pages === 6 && count($larger->events) === 25, 'Changing page size lost results');
    $empty = $history->page(['desde' => '2001-01-02', 'hasta' => '2001-01-02'], 'fecha_hora', 'DESC', 10);
    expectArchitecture($empty->events === [] && $empty->pagination->page === 1 && $empty->pagination->from === 0 && $empty->pagination->to === 0,
        'Empty history has invalid navigation');
} finally {
    $db->transRollback();
}
echo "History pagination: full catalog, filters, stable ordering, page sizes, aliases, bounds and empty results passed with rollback.\n";
