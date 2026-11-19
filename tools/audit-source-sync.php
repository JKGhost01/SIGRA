<?php
declare(strict_types=1);
$root = dirname(__DIR__) . '/';
define('FCPATH', $root . 'public/');
define('ENVIRONMENT', 'development');
require $root . 'app/Config/Paths.php';
require $root . 'system/Boot.php';
CodeIgniter\Boot::bootConsole(new Config\Paths());
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, 'No se pudo completar la auditoría de solo lectura: ' . $error::class . PHP_EOL);
    exit(1);
});
date_default_timezone_set(config(Config\App::class)->appTimezone);
/** Solo lee respuestas ya guardadas; no hace peticiones ni levanta pausas. */
$transport = new class implements App\Modules\SeismicIngestion\Infrastructure\Sources\FeedTransport {
    public function json(string $url, int $timeoutSeconds, array $headers): array {
        foreach (glob(WRITEPATH . 'cache/providers/*.feed.json') as $path) {
            $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (parse_url($data['url'], PHP_URL_HOST) === parse_url($url, PHP_URL_HOST)
                && parse_url($data['url'], PHP_URL_PATH) === parse_url($url, PHP_URL_PATH)) return $data['body'];
        }
        throw new RuntimeException('No hay una respuesta guardada para el feed configurado; espera la próxima sincronización permitida.');
    }
};
$db = db_connect();
$db->initialize();
$rows = $db->table('sismos')->select('id, fuente, fecha_hora, latitud, longitud, magnitud, profundidad, ubicacion, estado, sgc_metadata, updated_at')
    ->where('fecha_hora >=', date('Y-m-d H:i:s', time() - 6*86400))->get()->getResultArray();
$indexed = [];
$counts = [];
foreach ($rows as $row) { $indexed[$row['id']] = $row; $counts[$row['fuente']] = ($counts[$row['fuente']] ?? 0) + 1; }
$config = config(Config\Quakes::class);
$clients = [
    'SGC' => new App\Modules\SeismicIngestion\Infrastructure\Sources\SgcClient(config(Config\Sgc::class), $transport),
    'USGS' => new App\Modules\SeismicIngestion\Infrastructure\Sources\UsgsClient($config, $transport),
];
foreach (['EMSC','GEONET','GEOFON','INGV'] as $name) $clients[$name] = new App\Modules\SeismicIngestion\Infrastructure\Sources\AdditionalGeoJsonSource($name, $config, $transport);
$report = ['read_only' => true, 'comparison' => 'Última respuesta guardada frente al catálogo; las referencias correlacionadas son históricas, no nuevas confirmaciones.', 'now_local' => date('c'), 'health' => service('providerHealth')->snapshot(), 'db_six_days' => $counts, 'sources' => []];
foreach ($clients as $name => $client) {
    try {
        $events = method_exists($client, 'obtenerEventos') ? $client->obtenerEventos() : $client->fetch();
        $missing = []; $different = []; $correlated = 0; $same = 0;
        foreach ($events as $event) {
            if (!isset($indexed[$event['id']])) {
                $sourceId = json_decode($event['sgc_metadata'],true)['source_id'] ?? $event['id'];
                $found = false;
                foreach ($rows as $row) foreach (json_decode($row['sgc_metadata'], true)['source_references'] ?? [] as $ref) {
                    if ($ref['source'] === $name && $ref['source_event_id'] === $sourceId) { $found = true; break 2; }
                }
                if ($found) $correlated++;
                else $missing[] = ['id'=>$event['id'], 'date'=>$event['fecha_hora'], 'magnitude'=>$event['magnitud'], 'place'=>$event['ubicacion']];
                continue;
            }
            $old = $indexed[$event['id']];
            $changes = [];
            foreach (['fecha_hora','ubicacion','fuente','estado'] as $field) if ((string)$old[$field] !== (string)$event[$field]) $changes[$field] = [$old[$field],$event[$field]];
            foreach (['latitud','longitud','magnitud','profundidad'] as $field) if (abs((float)$old[$field]-(float)$event[$field]) > 0.000001) $changes[$field] = [$old[$field],$event[$field]];
            $oldMetadata = json_decode($old['sgc_metadata'], true) ?: [];
            $newMetadata = json_decode($event['sgc_metadata'], true) ?: [];
            foreach ($newMetadata as $field => $value) {
                if (in_array($field, ['source_references', 'correlation'], true)) continue;
                if (!array_key_exists($field, $oldMetadata) || $oldMetadata[$field] !== $value) {
                    $changes['metadata.' . $field] = [$oldMetadata[$field] ?? null, $value];
                }
            }
            if ($changes) $different[] = ['id'=>$event['id'], 'fields'=>$changes]; else $same++;
        }
        $dates = array_column($events,'fecha_hora'); rsort($dates);
        $report['sources'][$name] = ['normalized'=>count($events), 'same'=>$same, 'correlated'=>$correlated,
            'missing_count'=>count($missing),'missing_sample'=>array_slice($missing,0,8),'different_count'=>count($different),
            'different_sample'=>array_slice($different,0,5),'latest_event'=>$dates[0]??null];
    } catch (Throwable $error) { $report['sources'][$name] = ['error'=>$error->getMessage()]; }
}
$display = (new App\Models\SismoModel())->obtenerRecientes();
$report['map_counts'] = array_count_values(array_column($display,'fuente'));
$report['map_sgc_latest'] = array_slice(array_values(array_filter($display,fn($row)=>$row['fuente']==='SGC')),0,3);
foreach ($report['map_sgc_latest'] as &$row) unset($row['sgc_metadata']);
$report['last_batches'] = $db->table('sigra_ingestion_batches')->select('started_at_utc, saved_count, summary')->orderBy('started_at_utc','DESC')->limit(2)->get()->getResultArray();
foreach ($report['last_batches'] as &$batch) {
    $summary = json_decode($batch['summary'],true); unset($summary['correlations']); $batch['summary']=$summary;
}
echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
