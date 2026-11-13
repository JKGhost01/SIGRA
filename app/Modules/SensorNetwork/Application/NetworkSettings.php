<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Application;

final readonly class NetworkSettings
{
    public function __construct(
        public bool $alertingEnabled = true,
        public int $minDevices = 3,
        public int $clusterWindowSeconds = 25,
        public float $clusterRadiusKm = 80.0,
        public float $minDetectionConfidence = 0.35,
        public float $minAlertMagnitude = 3.0,
        public float $minAlertConfidence = 0.45,
        public int $maxDetectionsPerMinute = 30,
        public int $minSecondsBetweenDetections = 2,
        public int $maxDetectionAgeSeconds = 120,
        public int $futureToleranceSeconds = 30,
        public float $pWaveVelocityKmS = 6.0,
        public float $sWaveVelocityKmS = 3.5,
        public float $defaultDepthKm = 10.0,
        public float $alertRadiusBaseKm = 80.0,
        public float $alertRadiusPerMagnitudeKm = 55.0,
        public float $alertMaxRadiusKm = 500.0,
        public int $alertTtlMinutes = 10,
        public int $deviceOnlineMinutes = 10,
        public int $publicDevicePrecision = 2,
        public bool $fcmEnabled = false,
        public string $apkUrl = '',
        public int $statsCacheSeconds = 10,
        public int $expireThrottleSeconds = 60,
    ) {}
}
