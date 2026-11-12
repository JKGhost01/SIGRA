<?php

use App\Libraries\QuakeSyncService;
use App\Libraries\RedNetworkService;
use App\Models\SismoModel;
use Config\Quakes;

/**
 * Sincronización unificada de fuentes sísmicas.
 *
 * Ruta: GET /sismos/sync
 *
 * Descarga SGC y USGS en una sola pasada, deduplica entre fuentes y
 * devuelve el listado serializado que consume el mapa. La respuesta se
 * cachea en disco para que el polling de muchos visitantes no golpee
 * las APIs públicas.
 */
$config    = config(Quakes::class);
$cacheFile = WRITEPATH . 'cache/quakes-sync.json';
$cacheData = is_file($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : [];
$cacheData = is_array($cacheData) ? $cacheData : [];
$lastSync  = (int) ($cacheData['timestamp'] ?? 0);

/**
 * Formato que consume el mapa. No incluye datos internos.
 */
$serializar = static function (array $evento): array {
    $metadata = json_decode((string) ($evento['sgc_metadata'] ?? ''), true);
    $metadata = is_array($metadata) ? $metadata : [];

    return [
        'id'          => $evento['id'],
        'lat'         => (float) $evento['latitud'],
        'lng'         => (float) $evento['longitud'],
        'magnitud'    => $evento['magnitud'],
        'profundidad' => $evento['profundidad'],
        'ubicacion'   => $evento['ubicacion'],
        'fecha_hora'  => $evento['fecha_hora'],
        'fuente'      => $evento['fuente'],
        'estado'      => $evento['estado'],
        'intensidadPercibida'    => $metadata['cdi'] ?? null,
        'intensidadInstrumental' => $metadata['mmi'] ?? null,
        'aceleracion'            => $metadata['maxPGA'] ?? null,
        'citizen_reports_count' => (int) ($evento['citizen_reports_count'] ?? 0),
        'sentido'                => $metadata['felt'] ?? null,
        'municipios'  => trim((string) $evento['ubicacion'] . ' ' . (string) ($metadata['closerTowns'] ?? '')),
        'url'         => $metadata['url'] ?? null,
    ];
};

if ($lastSync > 0 && (time() - $lastSync) < $config->cacheSeconds) {
    // Cache only external feed downloads; public rows/counts are always read through the model.
    $eventos = array_map($serializar, (new SismoModel())->obtenerRecientes(SismoModel::VENTANA_EVENTOS_HORAS));

    return service('response')->setJSON([
        'ok'        => true,
        'synced'    => false,
        'timestamp' => $lastSync,
        'message'   => lang('Sigra.apiUpToDate'),
        'events'    => count($eventos),
        'saved'     => (int) ($cacheData['saved'] ?? 0),
        'sources'   => $cacheData['fuentes'] ?? [],
        'errors'    => $cacheData['errores'] ?? [],
        'eventos'   => $eventos,
    ]);
}

try {
    $modelo = new SismoModel();

    $sourceIdsPrevios = [];
    $desdeVentana     = date('Y-m-d H:i:s', time() - (SismoModel::VENTANA_EVENTOS_HORAS * 3600));
    foreach ($modelo->select('id')->where('fecha_hora >=', $desdeVentana)->findAll() as $registro) {
        $sourceIdsPrevios[(string) $registro['id']] = true;
    }

    $resultado  = (new QuakeSyncService($config))->sync();
    $eventos    = $resultado['eventos'];
    $guardados  = $resultado['guardados'];
    $errores    = $resultado['errores'];
    $recientes  = $modelo->obtenerRecientes(SismoModel::VENTANA_EVENTOS_HORAS);
    $timestamp  = time();

    if ($eventos === [] && $errores !== []) {
        return service('response')
            ->setStatusCode(502)
            ->setJSON([
                'ok'      => false,
                'message' => lang('Sigra.apiSyncFailed'),
                'errors'  => $errores,
            ]);
    }

    // Las alertas de la red ciudadana solo se generan para eventos locales
    // del SGC: son los que tienen cobertura instrumental en Colombia.
    $limiteNovedad = time() - 900;
    $red = new RedNetworkService();
    foreach ($eventos as $evento) {
        if (($evento['fuente'] ?? '') !== 'SGC') {
            continue;
        }

        $sourceId    = (string) ($evento['id'] ?? '');
        $fechaEvento = strtotime((string) ($evento['fecha_hora'] ?? ''));
        if ($sourceId === '' || isset($sourceIdsPrevios[$sourceId]) || $fechaEvento === false || $fechaEvento < $limiteNovedad) {
            continue;
        }

        try {
            $red->crearAlertaSgc($evento);
        } catch (Throwable $exception) {
            log_message('error', 'No se pudo crear alerta SGC para {id}: {message}', [
                'id'      => $sourceId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    $payload = array_map($serializar, $recientes);

    file_put_contents($cacheFile, json_encode([
        'timestamp' => $timestamp,
        'events'    => count($eventos),
        'saved'     => $guardados,
        'fuentes'   => $resultado['fuentes'],
        'errores'   => $errores,
        'eventos'   => $payload,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), LOCK_EX);

    return service('response')->setJSON([
        'ok'       => true,
        'synced'   => true,
        'timestamp' => $timestamp,
        'events'   => count($eventos),
        'saved'    => $guardados,
        'sources'  => $resultado['fuentes'],
        'errors'   => $errores,
        'eventos'  => $payload,
    ]);
} catch (Throwable $exception) {
    log_message('error', 'Sincronización sísmica fallida: {message}', ['message' => $exception->getMessage()]);

    return service('response')
        ->setStatusCode(502)
        ->setJSON([
            'ok'      => false,
            'message' => lang('Sigra.apiSyncFailed'),
        ]);
}
