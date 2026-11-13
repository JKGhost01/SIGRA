<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Http\Requests;

final class SubmitCitizenReportRequest
{
    public static function input(\CodeIgniter\HTTP\IncomingRequest $request): array
    {
        $input = [];
        foreach (['latitud', 'longitud', 'magnitud', 'ubicacion', 'fecha_hora'] as $field) {
            $value = $request->getPost($field);
            $input[$field] = is_string($value) ? $value : '';
        }
        return $input;
    }
    public static function canonical(array $legacy): array
    {
        $map = ['latitude' => 'latitud', 'longitude' => 'longitud', 'felt_intensity' => 'magnitud',
            'place' => 'ubicacion', 'reported_at_local' => 'fecha_hora'];
        $input = [];
        foreach ($map as $canonical => $field) $input[$canonical] = $legacy[$field] ?? null;
        return $input;
    }
}
