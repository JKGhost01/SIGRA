<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\RedApiException;
use App\Libraries\ApiInput;
use App\Libraries\RedNetworkService;
use Config\Red;

/**
 * API de la Red SIGRA de teléfonos.
 *
 * Todas las respuestas son JSON. La app Android usa estas rutas para
 * registrarse, enviar latidos, reportar ondas P y consultar alertas.
 */
class ApiRed extends BaseController
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
            'environment' => ENVIRONMENT,
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
        if (! $this->validarAppKey()) {
            return $this->noAutorizado('Clave de aplicación inválida o faltante.');
        }

        $payload = $this->payload();
        return $this->ejecutar(fn (RedNetworkService $red): array => $red->registrarDispositivo($payload));
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
        if (! $this->validarAppKey()) {
            return $this->noAutorizado('Clave de aplicación inválida o faltante.');
        }

        $device = $this->dispositivo();
        if ($device === null) {
            return $this->noAutorizado('Token de dispositivo inválido. Vuelve a registrar la app.');
        }

        return $this->ejecutar(fn (RedNetworkService $red): array => $red->latir($device, $this->payload()));
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
        if (! $this->validarAppKey()) {
            return $this->noAutorizado('Clave de aplicación inválida o faltante.');
        }

        $device = $this->dispositivo();
        if ($device === null) {
            return $this->noAutorizado('Token de dispositivo inválido. Vuelve a registrar la app.');
        }

        return $this->ejecutar(fn (RedNetworkService $red): array => $red->registrarDeteccion($device, $this->payload()));
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
        if (! $this->validarAppKey()) {
            return $this->noAutorizado('Clave de aplicación inválida o faltante.');
        }

        [$lat, $lng] = ApiInput::coordinates(
            $this->request->getGet('lat'), $this->request->getGet('lng'), true);

        $device = $this->dispositivo(false);

        return $this->ejecutar(fn (RedNetworkService $red): array => [
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
        if (! $this->validarAppKey()) {
            return $this->noAutorizado('Clave de aplicación inválida o faltante.');
        }

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

    protected function servicioRed(): RedNetworkService
    {
        return new RedNetworkService();
    }

    private function validarAppKey(): bool
    {
        $esperada = config(Red::class)->appKey;
        if ($esperada === '') {
            return ENVIRONMENT !== 'production';
        }

        $recibida = trim($this->request->getHeaderLine('X-SIGRA-KEY'));
        if ($recibida === '') {
            $authorization = trim($this->request->getHeaderLine('Authorization'));
            if (preg_match('/^Bearer[ \t]+([^ \t]+)$/i', $authorization, $matches)) {
                $recibida = $matches[1];
            }
        }

        return $recibida !== '' && hash_equals($esperada, $recibida);
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
    {
        $type = strtolower(trim(explode(';', $this->request->getHeaderLine('Content-Type'))[0]));
        if ($type === 'application/json' || str_ends_with($type, '+json')) {
            try {
                $object = json_decode($this->request->getBody(), false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new RedApiException('JSON inválido.', 400);
            }
            if (!$object instanceof \stdClass) {
                throw new RedApiException('El cuerpo JSON debe ser un objeto.', 422);
            }
            $payload = (array) $object;
        } elseif ($type === '' || $type === 'application/x-www-form-urlencoded' || $type === 'multipart/form-data') {
            $payload = $this->request->getPost();
        } else {
            throw new RedApiException('Content-Type no admitido.', 415);
        }
        return ApiInput::networkPayload(is_array($payload) ? $payload : []);
    }

    private function noAutorizado(string $mensaje)
    {
        return $this->response->setStatusCode(401)->setJSON([
            'status' => 'error',
            'message' => $mensaje,
        ]);
    }
}
