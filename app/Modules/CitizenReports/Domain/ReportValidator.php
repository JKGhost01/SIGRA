<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Domain;

use DateTimeImmutable;
use DateTimeZone;
final class ReportValidator
{
    public function __construct(private readonly ReportPolicy $policy, private readonly DateTimeZone $inputTimezone) {}
    public function validate(array $input, int $now): array
    {
        foreach (['latitude', 'longitude', 'felt_intensity', 'place', 'reported_at_local'] as $key) {
            if (!isset($input[$key]) || !is_string($input[$key]) || str_contains($input[$key], "\0") || trim($input[$key]) === '') {
                throw new ReportRejected('Sigra.reportInvalid');
            }
        }
        $latitude = $this->number($input['latitude'], -90, 90);
        $longitude = $this->number($input['longitude'], -180, 180);
        $intensity = $this->number($input['felt_intensity'], 0, 10);
        $place = trim($input['place']);
        if (!mb_check_encoding($place, 'UTF-8') || mb_strlen($place) > 150 || preg_match('/[\x00-\x1F\x7F<>]/u', $place)) {
            throw new ReportRejected('Sigra.reportInvalid');
        }
        $value = trim($input['reported_at_local']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value)) throw new ReportRejected('Sigra.reportTimeInvalid');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $this->inputTimezone);
        if (!$date || $date->format('Y-m-d\TH:i') !== $value || $date->getTimestamp() > $now + 120
            || $date->getTimestamp() < $now - $this->policy->maxAgeHours * 3600) throw new ReportRejected('Sigra.reportTimeInvalid');
        // Existing schema stores Colombia local wall time; UTC conversion needs an expand/contract migration.
        return ['reported_at' => $date->format('Y-m-d H:i:s'), 'latitude' => $latitude,
            'longitude' => $longitude, 'felt_intensity' => $intensity, 'place' => $place];
    }
    private function number(string $value, float $min, float $max): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < $min || (float) $value > $max) throw new ReportRejected('Sigra.reportInvalid');
        return (float) $value;
    }
}
