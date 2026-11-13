<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Modules\SeismicCatalog\Infrastructure\Persistence\CiSeismicEventRepository;
use App\Modules\SeismicCatalog\Infrastructure\Persistence\CiReportEvidenceCatalog;
use App\Modules\SeismicCatalog\Infrastructure\Persistence\CiEventWriter;
use App\Modules\SeismicCatalog\Application\Queries\ListEvents\ListEventsHandler;
use App\Modules\SeismicCatalog\Application\Queries\GetEvent\GetEventHandler;
use App\Modules\SeismicCatalog\Application\Queries\ListHistory\ListHistoryHandler;
use App\Modules\CitizenReports\Domain\{ReportPolicy, ReportValidator, CitizenReportMatcher};
use App\Modules\CitizenReports\Infrastructure\Persistence\CiCitizenReportRepository;
use App\Modules\CitizenReports\Application\Commands\ReconcileReports\ReconcileReportsHandler;
use App\Modules\CitizenReports\Application\Commands\SubmitReport\SubmitCitizenReportHandler;
use App\Modules\SeismicIngestion\Application\Commands\SyncEvents\SyncEventsHandler;
use App\Modules\SeismicIngestion\Domain\Services\EventDeduplicator;
use App\Modules\SeismicIngestion\Infrastructure\Sources\{SgcSourceAdapter, UsgsSourceAdapter};

/** Composition root. Only infrastructure factories resolve framework globals. */
class Services extends BaseService
{
    public static function sigraClock(bool $getShared = true): \App\Shared\Domain\Clock
    {
        if ($getShared) return static::getSharedInstance('sigraClock');
        return new \App\Shared\Infrastructure\SystemClock();
    }
    public static function catalogReader(bool $getShared = true): CiSeismicEventRepository
    {
        if ($getShared) return static::getSharedInstance('catalogReader');
        return new CiSeismicEventRepository(new \App\Models\SismoModel());
    }
    public static function listEvents(bool $getShared = true): ListEventsHandler
    {
        if ($getShared) return static::getSharedInstance('listEvents');
        return new ListEventsHandler(static::catalogReader());
    }
    public static function getEvent(bool $getShared = true): GetEventHandler
    {
        if ($getShared) return static::getSharedInstance('getEvent');
        return new GetEventHandler(static::catalogReader());
    }
    public static function listHistory(bool $getShared = true): ListHistoryHandler
    {
        if ($getShared) return static::getSharedInstance('listHistory');
        return new ListHistoryHandler(static::catalogReader());
    }
    public static function reportPolicy(?CitizenReports $config = null): ReportPolicy
    {
        $c = $config ?? config(CitizenReports::class);
        return new ReportPolicy($c->maxAgeHours, $c->pendingHours, $c->retentionDays,
            $c->matchSeconds, $c->matchKm, $c->minParticipants, $c->hourlyLimit, $c->dailyLimit, $c->hashKey);
    }
    public static function reportValidator(bool $getShared = true, ?CitizenReports $config = null): ReportValidator
    {
        if ($getShared && $config === null) return static::getSharedInstance('reportValidator');
        return new ReportValidator(static::reportPolicy($config), new \DateTimeZone(date_default_timezone_get()));
    }
    public static function reconcileCitizenReports(bool $getShared = true, ?\App\Models\CitizenReportStore $store = null, ?CitizenReports $config = null): ReconcileReportsHandler
    {
        if ($getShared && $store === null && $config === null) return static::getSharedInstance('reconcileCitizenReports');
        $db = $store?->db ?? db_connect();
        $policy = static::reportPolicy($config);
        return new ReconcileReportsHandler($store ?? new CiCitizenReportRepository($db), $store ?? new CiReportEvidenceCatalog($db),
            new CitizenReportMatcher($policy), $policy, static::sigraClock(), new \DateTimeZone(date_default_timezone_get()));
    }
    public static function submitCitizenReport(bool $getShared = true, ?\App\Models\CitizenReportStore $store = null, ?CitizenReports $config = null): SubmitCitizenReportHandler
    {
        if ($getShared && $store === null && $config === null) return static::getSharedInstance('submitCitizenReport');
        return new SubmitCitizenReportHandler($store ?? new CiCitizenReportRepository(db_connect()),
            static::reportValidator(false, $config), static::reportPolicy($config), static::sigraClock(),
            static::reconcileCitizenReports(false, $store, $config), service('logger'), new \DateTimeZone(date_default_timezone_get()));
    }
    public static function syncEvents(bool $getShared = true, ?Quakes $quakes = null, ?Sgc $sgc = null): SyncEventsHandler
    {
        if ($getShared && $quakes === null && $sgc === null) return static::getSharedInstance('syncEvents');
        $q = $quakes ?? config(Quakes::class); $sources = [];
        if ($q->sgcEnabled) $sources[] = new SgcSourceAdapter(new \App\Libraries\SgcClient($sgc ?? config(Sgc::class)));
        if ($q->globalEnabled) $sources[] = new UsgsSourceAdapter(new \App\Libraries\UsgsClient($q));
        return new SyncEventsHandler($sources, new CiEventWriter(new \App\Models\SismoModel()),
            new EventDeduplicator($q->dedupeSeconds, $q->dedupeDistanceKm, $q->dedupeMagnitudeDelta), $q->dedupeCrossSource,
            static::reconcileCitizenReports(), service('logger'),
            new \App\Modules\Alerting\Application\OfficialEventNotifier(static::alerting(), static::sigraClock(), service('logger')),
            new \App\Modules\SeismicIngestion\Infrastructure\Persistence\CiIngestionAuditRepository(db_connect()), static::sigraClock());
    }
    public static function catalogRefresh(bool $getShared = true): \App\Modules\SeismicIngestion\Application\Commands\RefreshCatalog\RefreshCatalogHandler
    {
        if ($getShared) return static::getSharedInstance('catalogRefresh');
        return new \App\Modules\SeismicIngestion\Application\Commands\RefreshCatalog\RefreshCatalogHandler(
            new \App\Modules\SeismicIngestion\Infrastructure\Scheduling\FileRefreshCoordinator(
                WRITEPATH . 'cache', config(Quakes::class)->cacheSeconds, static::sigraClock(), service('logger')),
            static::syncEvents());
    }
    public static function healthCheck(bool $getShared = true): \App\Modules\System\Application\Queries\HealthHandler
    {
        if ($getShared) return static::getSharedInstance('healthCheck');
        return new \App\Modules\System\Application\Queries\HealthHandler(new \App\Modules\System\Infrastructure\DatabaseAvailabilityProbe());
    }
    public static function networkSettings(?Red $config = null): \App\Modules\SensorNetwork\Application\NetworkSettings
    {
        $c = $config ?? config(Red::class);
        return new \App\Modules\SensorNetwork\Application\NetworkSettings(
            alertingEnabled: $c->alertingEnabled,
            minDevices: $c->minDevices,
            clusterWindowSeconds: $c->clusterWindowSeconds,
            clusterRadiusKm: $c->clusterRadiusKm,
            minDetectionConfidence: $c->minDetectionConfidence,
            minAlertMagnitude: $c->minAlertMagnitude,
            minAlertConfidence: $c->minAlertConfidence,
            maxDetectionsPerMinute: $c->maxDetectionsPerMinute,
            minSecondsBetweenDetections: $c->minSecondsBetweenDetections,
            maxDetectionAgeSeconds: $c->maxDetectionAgeSeconds,
            futureToleranceSeconds: $c->futureToleranceSeconds,
            pWaveVelocityKmS: $c->pWaveVelocityKmS,
            sWaveVelocityKmS: $c->sWaveVelocityKmS,
            defaultDepthKm: $c->defaultDepthKm,
            alertRadiusBaseKm: $c->alertRadiusBaseKm,
            alertRadiusPerMagnitudeKm: $c->alertRadiusPerMagnitudeKm,
            alertMaxRadiusKm: $c->alertMaxRadiusKm,
            alertTtlMinutes: $c->alertTtlMinutes,
            deviceOnlineMinutes: $c->deviceOnlineMinutes,
            publicDevicePrecision: $c->publicDevicePrecision,
            fcmEnabled: $c->fcmEnabled,
            apkUrl: $c->apkUrl,
            statsCacheSeconds: $c->statsCacheSeconds,
            expireThrottleSeconds: $c->expireThrottleSeconds,
        );
    }
    public static function sensorNetwork(bool $getShared = true, ?Red $config = null): \App\Modules\SensorNetwork\Application\SensorNetworkService
    {
        if ($getShared && $config === null) return static::getSharedInstance('sensorNetwork');
        $settings = static::networkSettings($config);
        $devices = new \App\Modules\SensorNetwork\Infrastructure\Persistence\CiDeviceRepository(new \App\Models\RedDeviceModel());
        $detections = new \App\Modules\SensorNetwork\Infrastructure\Persistence\CiDetectionRepository(new \App\Models\RedDetectionModel());
        $cache = new \App\Shared\Infrastructure\CiCacheStore();
        $alerts = static::alerting(false, $config, $devices, $detections, $cache);
        return new \App\Modules\SensorNetwork\Application\SensorNetworkService($settings, $devices, $detections, $alerts,
            new \App\Modules\SensorNetwork\Infrastructure\Persistence\CiNetworkStatistics($settings, $devices, $cache,
                new \App\Modules\Alerting\Infrastructure\Persistence\CiAlertStatisticsReader(db_connect())),
            static::sigraClock(), new \DateTimeZone(date_default_timezone_get()), config(Sigra::class)->version, config(Sigra::class)->apiVersion,
            new \App\Modules\SensorNetwork\Application\Commands\RegisterDetection\RegisterDetectionHandler($settings, $devices, $detections, $alerts, static::sigraClock(), new \DateTimeZone(date_default_timezone_get())));
    }
    public static function alerting(bool $getShared = true, ?Red $config = null,
        ?\App\Modules\SensorNetwork\Application\Contracts\DeviceRepository $devices = null,
        ?\App\Modules\SensorNetwork\Application\Contracts\DetectionRepository $detections = null,
        ?\App\Shared\Application\CacheStore $cache = null): \App\Modules\Alerting\Application\AlertingService
    {
        if ($getShared && $config === null && $devices === null && $detections === null && $cache === null) return static::getSharedInstance('alerting');
        $c = $config ?? config(Red::class); $settings = static::networkSettings($c);
        return new \App\Modules\Alerting\Application\AlertingService($settings,
            new \App\Modules\Alerting\Domain\PwaveFusion($settings->pWaveVelocityKmS, $settings->sWaveVelocityKmS, $settings->defaultDepthKm),
            $devices ?? new \App\Modules\SensorNetwork\Infrastructure\Persistence\CiDeviceRepository(new \App\Models\RedDeviceModel()),
            $detections ?? new \App\Modules\SensorNetwork\Infrastructure\Persistence\CiDetectionRepository(new \App\Models\RedDetectionModel()),
            new \App\Modules\Alerting\Infrastructure\Persistence\CiAlertRepository(new \App\Models\RedAlertModel()),
            new \App\Modules\Alerting\Infrastructure\Persistence\CiDeliveryRepository(new \App\Models\RedDeliveryModel()),
            new \App\Modules\Alerting\Application\Commands\DeliverAlert\AlertDeliveryHandler($settings,
                new \App\Modules\Alerting\Domain\PwaveFusion($settings->pWaveVelocityKmS, $settings->sWaveVelocityKmS, $settings->defaultDepthKm),
                $devices ?? new \App\Modules\SensorNetwork\Infrastructure\Persistence\CiDeviceRepository(new \App\Models\RedDeviceModel()),
                new \App\Modules\Alerting\Infrastructure\Persistence\CiDeliveryRepository(new \App\Models\RedDeliveryModel()),
                new \App\Modules\Alerting\Infrastructure\FcmPublisher(new \App\Libraries\FcmService($c)),
                static::sigraClock(), new \DateTimeZone(date_default_timezone_get())),
            static::sigraClock(), new \DateTimeZone(date_default_timezone_get()),
            new \App\Modules\Alerting\Infrastructure\Persistence\CiAlertDecisionLog(db_connect()),
            new \App\Shared\Infrastructure\CiTransactionManager(db_connect()));
    }
}
