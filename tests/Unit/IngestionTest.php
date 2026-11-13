<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicIngestion\Domain\Services\EventDeduplicator;
use App\Modules\SeismicIngestion\Domain\Contracts\SeismicSource;
use App\Modules\SeismicIngestion\Application\Commands\SyncEvents\SyncEventsHandler;
use App\Modules\SeismicCatalog\Application\Contracts\EventWriter;
use App\Modules\CitizenReports\Application\Contracts\ReportReconciler;
use Psr\Log\NullLogger;

$event = ['id' => 'SGC-one', 'latitud' => 4.6, 'longitud' => -74.1, 'fecha_hora' => '2026-11-12 10:00:00'];
$other = $event + ['fuente' => 'USGS'];
$other['id'] = 'USGS-one';
$dedupe = new EventDeduplicator(120, 75);
expectArchitecture($dedupe->unique([$other], [$event]) === [], 'Cross-source duplicate survived');
foreach ([['latitud' => 40], ['fecha_hora' => '2026-11-12 10:10:00'], ['fecha_hora' => 'invalid']] as $change) {
    $separate = array_replace($other, $change);
    expectArchitecture($dedupe->unique([$separate], [$event]) === [$separate], 'Distinct or invalid record was silently merged');
}
$differentMagnitude = array_replace($other, ['magnitud' => 7.0]);
expectArchitecture($dedupe->unique([$differentMagnitude], [array_replace($event, ['magnitud' => 3.0])]) === [$differentMagnitude], 'Incompatible magnitudes were merged');
$correlated = $dedupe->correlate([array_replace($other, ['fuente' => 'USGS'])], [array_replace($event, ['fuente' => 'SGC'])]);
$metadata = json_decode($correlated['reference'][0]['sgc_metadata'], true);
expectArchitecture(count($metadata['source_references']) === 2 && count($correlated['decisions']) === 1, 'Merged source provenance was lost');
expectArchitecture($dedupe->unique([$other], [$event, array_replace($event, ['id' => 'SGC-second'])]) === [$other], 'Ambiguous correlation silently discarded a source event');
$dateline = ['latitud' => 0, 'longitud' => 179.9, 'fecha_hora' => $event['fecha_hora']];
expectArchitecture($dedupe->unique([array_replace($dateline, ['longitud' => -179.9])], [$dateline]) === [], 'Dateline distance failed');
$source = static fn(string $name, array $events, bool $fail = false) => new class($name, $events, $fail) implements SeismicSource {
    public function __construct(private string $source, private array $events, private bool $fail) {}
    public function name(): string { return $this->source; }
    public function fetch(): array { if ($this->fail) throw new \App\Modules\SeismicIngestion\Domain\Contracts\SourceUnavailable($this->source, new RuntimeException('provider secret detail')); return $this->events; }
};
$writer = new class implements EventWriter {
    public array $rows = [];
    public int $calls = 0;
    public function save(array $events): int {
        $this->calls++; $count = 0;
        foreach ($events as $event) { if (!isset($this->rows[$event['id']])) $count++; $this->rows[$event['id']] = $event; }
        return $count;
    }
};
$reports = new class implements ReportReconciler {
    public bool $fail = false;
    public function reconcile(): void { if ($this->fail) throw new RuntimeException('matching failed'); }
};
$handler = new SyncEventsHandler([$source('SGC', [], true), $source('USGS', [$other])], $writer, $dedupe, true, $reports, new NullLogger());
$result = $handler->handle();
expectArchitecture($result['guardados'] === 1 && isset($result['errores']['SGC']), 'Partial source failure lost healthy batch');
expectArchitecture(!str_contains(json_encode($result), 'secret detail'), 'Provider error leaked');
expectArchitecture($handler->handle()['guardados'] === 0 && count($writer->rows) === 1, 'Replayed batch duplicated events');
$reports->fail = true;
expectArchitecture(isset($handler->handle()['errores']['reportes']) && count($writer->rows) === 1, 'Matching failure invalidated catalog');
$writer->calls = 0;
$failed = new SyncEventsHandler([$source('SGC', [], true), $source('USGS', [], true)], $writer, $dedupe, true, $reports, new NullLogger());
expectArchitecture($failed->handle()['guardados'] === 0 && $writer->calls === 0, 'Total provider failure wrote to catalog');
$reports->fail = false;
$multi = new SyncEventsHandler([
    $source('SGC', [array_replace($event, ['fuente' => 'SGC'])]),
    $source('USGS', [array_replace($other, ['fuente' => 'USGS'])]),
    $source('EMSC', [array_replace($other, ['id' => 'EMSC-one', 'fuente' => 'EMSC'])]),
    $source('GEONET', [array_replace($other, ['id' => 'GEONET-one', 'fuente' => 'GEONET'])]),
], $writer, $dedupe, true, $reports, new NullLogger());
$result = $multi->handle();
expectArchitecture(count($result['eventos']) === 1 && $result['eventos'][0]['fuente'] === 'SGC', 'Four-source correlation lost SGC preference');
$refs = json_decode($result['eventos'][0]['sgc_metadata'], true)['source_references'];
expectArchitecture(count($refs) === 4 && count($result['fuentes']) === 4, 'Four-source provenance/counts lost');
$noSgc = new SyncEventsHandler([$source('SGC', [], true), $source('USGS', [array_replace($other, ['fuente' => 'USGS'])]),
    $source('EMSC', [array_replace($other, ['id' => 'EMSC-one', 'fuente' => 'EMSC'])])], $writer, $dedupe, true, $reports, new NullLogger());
$result = $noSgc->handle();
$meta = json_decode($result['eventos'][0]['sgc_metadata'], true);
expectArchitecture(count($result['eventos']) === 1 && $meta['correlation']['preferred_source'] === 'USGS', 'SGC outage broke global correlation');
echo "Ingestion: partial failure, deduplication, replay and reconciliation checks passed.\n";
