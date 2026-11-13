<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Http\Requests;

use App\Shared\Http\ApiInput;
use App\Libraries\RedApiException;
final class NetworkPayloadRequest
{
    public static function parse(\CodeIgniter\HTTP\IncomingRequest $request): array
    {
        if (strlen($request->getBody()) > 16384) throw new RedApiException('Payload demasiado grande.', 413);
        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($type === 'application/json' || str_ends_with($type, '+json')) {
            try {
                $object = json_decode($request->getBody(), false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new RedApiException('JSON inválido.', 400);
            }
            if (!$object instanceof \stdClass) {
                throw new RedApiException('El cuerpo JSON debe ser un objeto.', 422);
            }
            $payload = (array) $object;
        } elseif ($type === '' || $type === 'application/x-www-form-urlencoded' || $type === 'multipart/form-data') {
            $payload = $request->getPost();
        } else {
            throw new RedApiException('Content-Type no admitido.', 415);
        }
        return ApiInput::networkPayload(is_array($payload) ? $payload : []);
    }

}
