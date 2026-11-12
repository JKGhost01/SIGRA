<?php

namespace App\Controllers;

use App\Libraries\ApiInput;
use App\Libraries\RedApiException;

/**
 * Sismos - Controlador
 * -------------------------------------------------------------------
 * REGLA ARQUITECTÓNICA: este controlador es EXCLUSIVAMENTE un router.
 *
 * NO contiene:
 *   - consultas a base de datos
 *   - validaciones
 *   - reglas de negocio
 *   - cálculos
 *   - manejo de permisos
 *   - manipulación directa de modelos
 *
 * Nota técnica: se usa `require` (en vez del helper view()) para
 * invocar el index.php de cada operación. Esto es lo que permite que
 * un `return redirect()->to(...)` hecho dentro de procesor.php se
 * propague correctamente hacia arriba (index.php -> validator.php ->
 * procesor.php) hasta llegar aquí y ser devuelto como respuesta real
 * de CodeIgniter. Con view() ese `return` se perdería, porque view()
 * solo captura el HTML generado, no el valor devuelto por el script.
 *
 * Cada método sigue siendo, en esencia, un simple enrutador: decide
 * qué archivo de vista ejecutar y nada más.
 */
class Sismos extends BaseController
{
    use \App\Libraries\ApiResponses;

    /**
     * Listado de sismos.
     * Ruta: GET /sismos
     */
    public function list()
    {
        return require APPPATH . 'Views/sismos/list/index.php';
    }

    /**
     * API segura para la app Android.
     * Ruta: GET /api/sismos/recent
     */
    public function apiRecent()
    {
        return $this->responderApi(fn () => $this->accionApiRecent());
    }

    private function accionApiRecent()
    {
        if (! $this->validarApiToken()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => lang('Sigra.apiInvalidToken'),
                ]);
        }

        $horas = $this->enteroQuery('hours', \App\Models\SismoModel::VENTANA_EVENTOS_HORAS, 1, 24 * 14);
        $limite = $this->enteroQuery('limit', 300, 1, 1000);
        $minMagnitud = $this->flotanteQuery('min_magnitude', null);
        $fuente = ApiInput::text($this->request->getGet('fuente') ?? '', 'fuente');
        [$origenLat, $origenLng] = ApiInput::coordinates(
            $this->request->getGet('lat'), $this->request->getGet('lng'));
        $radioKm = $this->flotanteQuery('radius_km', null);
        $orden = ApiInput::text($this->request->getGet('order') ?? 'fecha', 'order');
        if (!in_array($orden, ['fecha', 'distancia', 'magnitud'], true)) {
            throw new RedApiException('order debe ser fecha, distancia o magnitud.');
        }
        if (($radioKm !== null || $orden === 'distancia') && $origenLat === null) {
            throw new RedApiException('El radio y el orden por distancia requieren lat y lng.');
        }
        if ($radioKm !== null) ApiInput::number($radioKm, 'radius_km', 0, 20000);
        if ($minMagnitud !== null) ApiInput::number($minMagnitud, 'min_magnitude', -2, 10);

        $model = $this->modeloSismos();
        $eventos = $model->obtenerRecientesFiltrados($horas, $limite, $minMagnitud, $fuente, $origenLat, $origenLng, $radioKm, $orden);

        return $this->response->setJSON([
            'status' => 'ok',
            'count' => count($eventos),
            'hours' => $horas,
            'origin' => $origenLat !== null ? ['lat' => $origenLat, 'lng' => $origenLng, 'radius_km' => $radioKm] : null,
            'generated_at' => date('c'),
            'events' => array_map(fn (array $evento) => $this->serializarEventoApi($evento), $eventos),
        ]);
    }

    /**
     * Detalle JSON de un evento para la app Android.
     * Ruta: GET /api/sismos/detalle/{id}
     */
    public function apiDetalle(string $id)
    {
        return $this->responderApi(fn () => $this->accionApiDetalle($id));
    }

    private function accionApiDetalle(string $id)
    {
        if (! $this->validarApiToken()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => lang('Sigra.apiInvalidToken'),
                ]);
        }

        ApiInput::coordinates($this->request->getGet('lat'), $this->request->getGet('lng'));
        $evento = $this->modeloSismos()->obtenerPorId($id);
        if ($evento === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => lang('Sigra.apiEventNotFound'),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'event' => $this->serializarEventoApi($evento),
            'generated_at' => date('c'),
        ]);
    }

    /**
     * Salud del servicio y de la base de datos.
     * Ruta: GET /api/health
     */
    public function apiHealth()
    {
        return $this->responderApi(fn () => $this->accionApiHealth());
    }

    private function accionApiHealth()
    {
        $baseDatos = 'ok';
        try {
            db_connect()->query('SELECT 1');
        } catch (\Throwable) {
            $baseDatos = 'error';
        }

        $sigra = config(\Config\Sigra::class);

        return $this->response
            ->setStatusCode($baseDatos === 'ok' ? 200 : 503)
            ->setJSON([
                'status' => $baseDatos === 'ok' ? 'ok' : 'degraded',
                'service' => 'sigra',
                'version' => $sigra->version,
                'release' => $sigra->releaseName,
                'build' => $sigra->build,
                'api_version' => $sigra->apiVersion,
                'database' => $baseDatos,
                'environment' => ENVIRONMENT,
                'time' => date('c'),
            ]);
    }

    /**
     * Recurso con el último evento sísmico.
     * Ruta: GET /api/sismos/ultimo
     */
    public function apiUltimo()
    {
        return $this->responderApi(fn () => $this->accionApiUltimo());
    }

    private function accionApiUltimo()
    {
        if (! $this->validarApiToken()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => lang('Sigra.apiInvalidToken'),
                ]);
        }

        ApiInput::coordinates($this->request->getGet('lat'), $this->request->getGet('lng'));
        $model = $this->modeloSismos();
        $eventos = $model->obtenerRecientes(\App\Models\SismoModel::VENTANA_EVENTOS_HORAS);
        $ultimo = $eventos[0] ?? null;

        return $this->response->setJSON([
            'status' => 'ok',
            'event' => $ultimo ? $this->serializarEventoApi($ultimo) : null,
            'generated_at' => date('c'),
        ]);
    }

    /**
     * Estado de alerta y resumén del último evento.
     * Ruta: GET /api/sismos/alertas
     */
    public function apiAlertas()
    {
        return $this->responderApi(fn () => $this->accionApiAlertas());
    }

    private function accionApiAlertas()
    {
        if (! $this->validarApiToken()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => lang('Sigra.apiInvalidToken'),
                ]);
        }

        ApiInput::coordinates($this->request->getGet('lat'), $this->request->getGet('lng'));
        $model = $this->modeloSismos();
        $eventos = $model->obtenerRecientes(\App\Models\SismoModel::VENTANA_EVENTOS_HORAS);
        $ultimo = $eventos[0] ?? null;
        $alerta = $ultimo ? $this->alertaParaMagnitud((float) ($ultimo['magnitud'] ?? 0)) : 'sin_datos';

        return $this->response->setJSON([
            'status' => 'ok',
            'alerta' => $alerta,
            'ultimo' => $ultimo ? $this->serializarEventoApi($ultimo) : null,
            'generated_at' => date('c'),
        ]);
    }

    /**
     * Sincronización interna con el feed del SGC.
     * Ruta: GET /sismos/sync
     */
    public function sync()
    {
        return require APPPATH . 'Views/sismos/sync/index.php';
    }

    /**
     * Histórico de eventos sísmicos.
     * Ruta: GET /sismos/historico
     */
    public function historico()
    {
        return require APPPATH . 'Views/sismos/historico/index.php';
    }

    /**
     * Compatibilidad con la antigua vista de sismos globales.
     * Desde 1.0.3 el mapa principal ya unifica SGC y USGS.
     * Ruta: GET /sismos/globales
     */
    public function globales()
    {
        return redirect()->to(site_url('sismos'), 301);
    }

    /**
     * Información del proyecto y políticas legales.
     * Ruta: GET /acerca-de
     */
    public function acerca()
    {
        return require APPPATH . 'Views/acerca-de/index.php';
    }

    /**
     * Política de privacidad.
     * Ruta: GET /politica-privacidad
     */
    public function privacidad()
    {
        return require APPPATH . 'Views/politica-privacidad/index.php';
    }

    /**
     * Política de cookies.
     * Ruta: GET /politica-cookies
     */
    public function cookies()
    {
        return require APPPATH . 'Views/politica-cookies/index.php';
    }

    /**
     * Términos de uso.
     * Ruta: GET /terminos-uso
     */
    public function terminos()
    {
        return require APPPATH . 'Views/terminos-uso/index.php';
    }

    /**
     * Registrar un sismo nuevo.
     * Ruta: GET|POST /sismos/create
     */
    public function create()
    {
        return require APPPATH . 'Views/sismos/create/index.php';
    }

    /**
     * Editar un sismo existente.
     * Ruta: GET|POST /sismos/edit/{id}
     */
    public function edit($id = null)
    {
        return require APPPATH . 'Views/sismos/edit/index.php';
    }

    /**
     * Eliminar un sismo existente.
     * Ruta: GET|POST /sismos/delete/{id}
     */
    public function delete($id = null)
    {
        return require APPPATH . 'Views/sismos/delete/index.php';
    }

    /**
     * Detalle de un sismo (ficha + mapa centrado en el epicentro).
     * Ruta: GET /sismos/detalle/{id}
     */
    public function detalle($id = null)
    {
        return require APPPATH . 'Views/sismos/detail/index.php';
    }

    /**
     * Verifica el token por cabecera Authorization.
     */
    protected function modeloSismos(): \App\Models\SismoModel
    {
        return new \App\Models\SismoModel();
    }

    private function validarApiToken(): bool
    {
        $token = trim((string) (env('APP_API_TOKEN') ?: env('app.apiToken') ?: env('app_apiToken') ?: ''));
        $header = $this->request->getHeaderLine('Authorization');

        if ($token === '') {
            return ENVIRONMENT !== 'production';
        }

        if (!preg_match('/^Bearer[ \t]+([^ \t]+)$/i', trim($header), $matches)) return false;

        return hash_equals($token, $matches[1]);
    }

    /**
     * Serializa el evento para la app Android.
     *
     * @param array<string, mixed> $evento
     * @return array<string, mixed>
     */
    private function serializarEventoApi(array $evento): array
    {
        $latitud = (float) ($evento['latitud'] ?? 0);
        $longitud = (float) ($evento['longitud'] ?? 0);
        $magnitud = (float) ($evento['magnitud'] ?? 0);
        $distancia = $this->distanciaKmDesdeOrigen($latitud, $longitud);
        $metadata = json_decode((string) ($evento['sgc_metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $fecha = (string) ($evento['fecha_hora'] ?? '');
        $fuente = (string) ($evento['fuente'] ?? 'SGC');

        return [
            'id' => (string) ($evento['id'] ?? ''),
            'citizen_reports_count' => (int) ($evento['citizen_reports_count'] ?? 0),
            'source_id' => (string) ($metadata['source_id'] ?? $evento['id'] ?? ''),
            'ubicacion' => (string) ($evento['ubicacion'] ?? 'Sin ubicación'),
            'latitud' => $latitud,
            'longitud' => $longitud,
            'magnitud' => $magnitud,
            'profundidad' => (float) ($evento['profundidad'] ?? 0),
            'fecha' => $fecha,
            'fecha_iso' => $fecha !== '' ? date('c', strtotime($fecha)) : null,
            'distancia_km' => $distancia !== null ? round($distancia, 1) : null,
            'alerta' => $this->alertaParaMagnitud($magnitud),
            'nivel' => $this->nivelNumerico($magnitud),
            'estado' => (string) ($evento['estado'] ?? 'automatico'),
            'fuente' => $fuente,
            'cdi' => $this->numeroMetadata($metadata, 'cdi'),
            'mmi' => $this->numeroMetadata($metadata, 'mmi'),
            'pga' => $this->numeroMetadata($metadata, 'maxPGA'),
            'mag_type' => $metadata['magType'] ?? null,
            'agency' => $metadata['agency'] ?? $fuente,
            'felt' => isset($metadata['felt']) ? (int) $metadata['felt'] : null,
            'tsunami' => isset($metadata['tsunami']) ? (int) $metadata['tsunami'] : null,
            'significance' => isset($metadata['sig']) ? (int) $metadata['sig'] : null,
            'closer_towns' => $metadata['closerTowns'] ?? null,
            'official_url' => $metadata['url'] ?? null,
            'detalle_url' => site_url('sismos/detalle/' . rawurlencode((string) ($evento['id'] ?? ''))),
            'mapa_sgc_url' => $fuente === 'SGC' && ! empty($evento['id'])
                ? 'https://archive.sgc.gov.co/events/' . rawurlencode((string) $evento['id']) . '/map.png'
                : null,
        ];
    }

    private function numeroMetadata(array $metadata, string $clave): ?float
    {
        if (! isset($metadata[$clave]) || $metadata[$clave] === '' || $metadata[$clave] === null) {
            return null;
        }

        return is_numeric($metadata[$clave]) ? (float) $metadata[$clave] : null;
    }

    private function distanciaKmDesdeOrigen(float $latitud, float $longitud): ?float
    {
        $origenLat = $this->flotanteQuery('lat', null);
        $origenLng = $this->flotanteQuery('lng', null);

        if ($origenLat === null || $origenLng === null) {
            return null;
        }

        return \App\Models\SismoModel::distanciaKm($origenLat, $origenLng, $latitud, $longitud);
    }

    private function alertaParaMagnitud(float $magnitud): string
    {
        if ($magnitud >= 6.0) {
            return 'roja';
        }
        if ($magnitud >= 5.0) {
            return 'naranja';
        }
        if ($magnitud >= 4.0) {
            return 'amarilla';
        }
        if ($magnitud >= 3.0) {
            return 'verde';
        }

        return 'informativa';
    }

    private function nivelNumerico(float $magnitud): int
    {
        if ($magnitud >= 6.0) {
            return 4;
        }
        if ($magnitud >= 5.0) {
            return 3;
        }
        if ($magnitud >= 4.0) {
            return 2;
        }
        if ($magnitud >= 3.0) {
            return 1;
        }

        return 0;
    }

    private function enteroQuery(string $clave, int $porDefecto, int $minimo, int $maximo): int
    {
        $valor = $this->request->getGet($clave);
        return $valor === null ? $porDefecto : ApiInput::integer($valor, $clave, $minimo, $maximo);
    }

    private function flotanteQuery(string $clave, ?float $porDefecto): ?float
    {
        $valor = $this->request->getGet($clave);
        return $valor === null ? $porDefecto : ApiInput::number($valor, $clave, -PHP_FLOAT_MAX, PHP_FLOAT_MAX);
    }
}
