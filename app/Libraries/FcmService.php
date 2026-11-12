<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Red;
use Config\Services;
use Throwable;

/**
 * Envío de notificaciones push con Firebase Cloud Messaging HTTP v1.
 *
 * No requiere el SDK de Firebase en PHP: firma un JWT con la cuenta de
 * servicio y obtiene un token OAuth2. Si FCM no está configurado, el
 * sistema sigue funcionando con el polling de la app Android.
 */
class FcmService
{
    private Red $config;
    private ?array $credenciales = null;

    public function __construct(?Red $config = null)
    {
        $this->config = $config ?? config(Red::class);
    }

    public function estaConfigurado(): bool
    {
        return $this->config->fcmEnabled && $this->credenciales() !== null;
    }

    /**
     * @param array<string, string|int|float> $datos
     */
    public function enviar(string $token, string $titulo, string $cuerpo, array $datos = []): bool
    {
        if ($token === '' || ! $this->estaConfigurado()) {
            return false;
        }

        try {
            $credenciales = $this->credenciales();
            $accessToken = $this->accessToken($credenciales);
            if ($accessToken === null) {
                return false;
            }

            $datosString = [];
            foreach ($datos as $clave => $valor) {
                $datosString[(string) $clave] = (string) $valor;
            }

            $mensaje = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $titulo,
                        'body' => $cuerpo,
                    ],
                    'data' => $datosString,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'sigra_alertas',
                            'sound' => 'default',
                            'default_vibrate_timings' => false,
                            'vibrate_timings' => ['0s', '0.35s', '0.2s', '0.35s', '0.2s', '0.8s'],
                            'visibility' => 'public',
                            'notification_priority' => 'PRIORITY_MAX',
                            'category' => 'ALARM',
                        ],
                    ],
                ],
            ];

            $proyecto = $this->proyecto($credenciales);
            $cliente = Services::curlrequest([
                'timeout' => $this->config->fcmTimeout,
                'http_errors' => false,
                'verify' => true,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
            ]);

            $respuesta = $cliente->post(
                'https://fcm.googleapis.com/v1/projects/' . rawurlencode($proyecto) . '/messages:send',
                ['body' => json_encode($mensaje, JSON_THROW_ON_ERROR)],
            );

            if ($respuesta->getStatusCode() >= 200 && $respuesta->getStatusCode() < 300) {
                return true;
            }

            log_message('warning', 'FCM rechazó el push: HTTP {code}', ['code' => $respuesta->getStatusCode()]);

            return false;
        } catch (Throwable $exception) {
            log_message('error', 'FCM no pudo enviar el push: {message}', ['message' => $exception->getMessage()]);

            return false;
        }
    }

    private function credenciales(): ?array
    {
        if ($this->credenciales !== null) {
            return $this->credenciales;
        }

        $ruta = $this->config->fcmCredentials;
        if ($ruta === '' || ! is_file($ruta)) {
            return null;
        }

        $contenido = json_decode((string) file_get_contents($ruta), true);
        if (! is_array($contenido) || empty($contenido['client_email']) || empty($contenido['private_key'])) {
            return null;
        }

        return $this->credenciales = $contenido;
    }

    private function proyecto(array $credenciales): string
    {
        if ($this->config->fcmProjectId !== '') {
            return $this->config->fcmProjectId;
        }

        return (string) ($credenciales['project_id'] ?? '');
    }

    private function accessToken(array $credenciales): ?string
    {
        $cache = cache();
        $clave = 'sigra_fcm_access_token';
        $cacheado = $cache->get($clave);
        if (is_string($cacheado) && $cacheado !== '') {
            return $cacheado;
        }

        $ahora = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $credenciales['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $ahora,
            'exp' => $ahora + 3600,
        ], JSON_THROW_ON_ERROR));

        $firma = '';
        if (! openssl_sign($header . '.' . $claims, $firma, $credenciales['private_key'], OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $jwt = $header . '.' . $claims . '.' . $this->base64Url($firma);

        $cliente = Services::curlrequest([
            'timeout' => $this->config->fcmTimeout,
            'http_errors' => false,
            'verify' => true,
        ]);
        $respuesta = $cliente->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);

        if ($respuesta->getStatusCode() !== 200) {
            return null;
        }

        $datos = json_decode($respuesta->getBody(), true);
        $token = is_array($datos) ? (string) ($datos['access_token'] ?? '') : '';
        if ($token === '') {
            return null;
        }

        $cache->save($clave, $token, 3000);

        return $token;
    }

    private function base64Url(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
