<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Http\Controllers\Api\V1;

use App\Controllers\BaseController;

use App\Libraries\RedApiException;
use App\Shared\Http\ApiInput;
use App\Modules\SensorNetwork\Application\SensorNetworkService;
use Config\Red;

/**
 * API de la Red SIGRA de teléfonos.
 *
 * Todas las respuestas son JSON. La app Android usa estas rutas para
 * registrarse, enviar latidos, reportar ondas P y consultar alertas.
 */
class SensorNetworkController extends BaseController
{
    use \App\Libraries\ApiResponses;

    /**
     * GET /api/red/config
     */
    public function config()
    {
        return $this->responderApi(fn () => $this->accionConfig());
    }

    private function accionConfig()
    {
        return $this->response->setJSON($this->servicioRed()->configPublica());
    }

    /**
     * GET /api/red/red
     * Estadísticas públicas para el panel web y la app.
     */
    public function red()
    {
        return $this->responderApi(fn () => $this->accionRed());
    }

    private function accionRed()
    {
        [$latitud, $longitud] = ApiInput::coordinates(
            $this->request->getGet('lat'), $this->request->getGet('lng'));

        return $this->response->setJSON(
            $this->servicioRed()->estadisticasPublicas($latitud, $longitud),
        );
    }

    /**
     * GET /api/red/health
     */
    public function health()
    {
        return $this->responderApi(fn () => $this->accionHealth());
    }

    private function accionHealth()
    {
        $sigra = config(\Config\Sigra::class);

        return $this->response->setJSON([
            'status' => 'ok',
            'service' => 'sigra-red',
            'version' => $sigra->version,
            'build' => $sigra->build,
            'api_version' => $sigra->apiVersion,
            'time' => date('c'),
        ]);
    }

    /**
     * POST /api/red/registro
     */
    public function registro()
    {
        return $this->responderApi(fn () => $this->accionRegistro());
    }

    private function accionRegistro()
    {
        $payload = $this->payload();
        return $this->ejecutar(fn (SensorNetworkService $red): array => $red->registrarDispositivo($payload, trim($this->request->getHeaderLine('X-Device-Token'))));
    }

    /**
     * POST /api/red/estado
     */
    public function estado()
    {
        return $this->responderApi(fn () => $this->accionEstado());
    }

    private function accionEstado()
    {
        $device = $this->dispositivo();
        if ($device === null) {
            return $this->noAutorizado('Token de dispositivo inválido. Vuelve a registrar la app.');
        }

        return $this->ejecutar(fn (SensorNetworkService $red): array => $red->latir($device, $this->payload()));
    }

    /**
     * POST /api/red/deteccion
     */
    public function deteccion()
    {
        return $this->responderApi(fn () => $this->accionDeteccion());
    }

    private function accionDeteccion()
    {
        $device = $this->dispositivo();
        if ($device === null) {
            return $this->noAutorizado('Token de dispositivo inválido. Vuelve a registrar la app.');
        }

        return $this->ejecutar(fn (SensorNetworkService $red): array => $red->registrarDeteccion($device, $this->payload()));
    }

    /**
     * GET /api/red/alertas?lat=&lng=
     */
    public function alertas()
    {
        return $this->responderApi(fn () => $this->accionAlertas());
    }

    private function accionAlertas()
    {
        [$lat, $lng] = ApiInput::coordinates(
            $this->request->getGet('lat'), $this->request->getGet('lng'), true);

        $device = $this->dispositivo(false);

        return $this->ejecutar(fn (SensorNetworkService $red): array => [
            'status' => 'ok',
            'server_time_ms' => (int) round(microtime(true) * 1000),
            'alerts' => $red->alertasCercanas($device, (float) $lat, (float) $lng),
        ]);
    }

    /**
     * POST /api/red/desactivar
     */
    public function desactivar()
    {
        return $this->responderApi(fn () => $this->accionDesactivar());
    }

    private function accionDesactivar()
    {
        $device = $this->dispositivo();
        if ($device === null) {
            return $this->noAutorizado('Token de dispositivo inválido.');
        }

        $this->servicioRed()->desactivarDispositivo($device);

        return $this->response->setJSON([
            'status' => 'ok',
            'message' => 'El teléfono dejó de participar en la red.',
        ]);
    }

    /**
     * @param callable(RedNetworkService): array<string, mixed> $accion
     */
    private function ejecutar(callable $accion)
    {
        return $this->response->setJSON($accion($this->servicioRed()));
    }

    protected function servicioRed(): SensorNetworkService
    {
        return service('sensorNetwork');
    }

    private function dispositivo(bool $obligatorio = true): ?array
    {
        $token = trim($this->request->getHeaderLine('X-Device-Token'));
        if ($token === '') {
            return null;
        }

        $device = $this->servicioRed()->autenticar($token);
        if ($device === null && !$obligatorio) {
            throw new RedApiException('Token de dispositivo inválido.', 401);
        }
        return $device;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    { return \App\Modules\SensorNetwork\Http\Requests\NetworkPayloadRequest::parse($this->request); }

    private function noAutorizado(string $mensaje)
    {
        return $this->apiFailure(401, $mensaje);
    }
}
