<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

use App\Modules\SeismicIngestion\Domain\Contracts\{SeismicSource, SourceUnavailable};
use App\Modules\SeismicIngestion\Domain\Services\ProviderEventValidator;
use Config\Quakes;
use DateTimeImmutable;
use DateTimeZone;

/** Provider-specific GeoJSON mapping; geometry alone is not a depth measurement. */
final class AdditionalGeoJsonSource implements SeismicSource
{
    public function __construct(private readonly string $source, private readonly Quakes $config,
        private readonly ?FeedTransport $transport = null)
    {
        if (!in_array($source, ['EMSC', 'GEONET', 'GEOFON', 'INGV'], true)) throw new \InvalidArgumentException('Fuente no admitida.');
    }

    public function name(): string { return $this->source; }

    public function fetch(): array
    {
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $start = $now->modify('-' . $this->config->windowDays . ' days');
            $query = ['format' => $this->source === 'INGV' ? 'geojson' : 'json',
                'starttime' => $start->format('Y-m-d\TH:i:s'), 'endtime' => $now->format('Y-m-d\TH:i:s'),
                'minmagnitude' => $this->config->usgsMinMagnitude, 'limit' => $this->config->usgsLimit, 'orderby' => 'time'];
            $url = match ($this->source) {
                'EMSC' => $this->config->emscEndpoint . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
                'INGV' => $this->config->ingvEndpoint . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
                'GEOFON' => $this->config->geofonEndpoint,
                'GEONET' => $this->config->geonetEndpoint . '?MMI=-1',
            };
            $body = ($this->transport ?? new CiFeedTransport())->json($url, 10, [
                'Accept' => $this->source === 'GEONET' ? 'application/vnd.geo+json;version=2' : 'application/json',
                'User-Agent' => 'SIGRA/' . config(\Config\Sigra::class)->version,
            ]);
            if (!isset($body['features']) || !is_array($body['features'])) throw new \RuntimeException('GeoJSON inválido.');
            $events = [];
            foreach ($body['features'] as $feature) {
                $event = $this->normalize($feature, $start, $now);
                if ($event !== null) $events[] = $event;
            }
            return $events;
        } catch (\Throwable $error) {
            throw new SourceUnavailable($this->source, $error);
        }
    }

    private function normalize(mixed $feature, DateTimeImmutable $start, DateTimeImmutable $now): ?array
    {
        if (!is_array($feature)) return null;
        $p = $feature['properties'] ?? null;
        $c = $feature['geometry']['coordinates'] ?? null;
        if (!is_array($p) || !is_array($c) || !isset($c[0], $c[1])) return null;
        $emsc = $this->source === 'EMSC';
        $geonet = $this->source === 'GEONET';
        if (($emsc && ($p['evtype'] ?? '') !== 'ke') || ($geonet && ($p['quality'] ?? '') === 'deleted')
            || ($this->source === 'INGV' && ($p['type'] ?? '') !== 'earthquake')) return null;
        $rawId = match ($this->source) {
            'GEONET' => $p['publicID'] ?? null,
            'INGV' => $p['eventId'] ?? null,
            default => $feature['id'] ?? null,
        };
        if ($this->source === 'INGV' && is_int($rawId) && $rawId > 0) $rawId = (string) $rawId;
        $id = ProviderEventValidator::sourceId($rawId);
        $mag = $geonet ? ($p['magnitude'] ?? null) : ($p['mag'] ?? null);
        // EMSC geometry is elevation; GEOFON/INGV geometry contains positive depth in km.
        $depth = in_array($this->source, ['GEOFON', 'INGV'], true) ? ($c[2] ?? null) : ($p['depth'] ?? null);
        $place = match ($this->source) {
            'EMSC' => $p['flynn_region'] ?? '', 'GEONET' => $p['locality'] ?? '', default => $p['place'] ?? '',
        };
        $time = $p['time'] ?? null;
        // These two services publish UTC timestamps without a timezone suffix.
        if (in_array($this->source, ['GEOFON', 'INGV'], true) && is_string($time) && !str_ends_with($time, 'Z')) $time .= 'Z';
        if ($id === null || !is_string($place) || !is_string($time)
            || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/D', $time)
            || !ProviderEventValidator::scientificFields($c[1], $c[0], $mag, $depth)) return null;
        try {
            $date = new DateTimeImmutable($time);
            if (DateTimeImmutable::getLastErrors() !== false || $date < $start || $date > $now) return null;
        } catch (\Exception) { return null; }
        if ((float) $mag < $this->config->usgsMinMagnitude) return null;
        $url = match ($this->source) {
            'EMSC' => 'https://www.seismicportal.eu/eventdetails.html?unid=' . rawurlencode($id),
            'GEONET' => 'https://www.geonet.org.nz/earthquake/' . rawurlencode($id),
            'GEOFON' => 'https://geofon.gfz.de/eqinfo/event.php?id=' . rawurlencode($id),
            'INGV' => 'https://terremoti.ingv.it/event/' . rawurlencode($id),
        };
        $metadata = ['source_id' => $id, 'agency' => $this->source, 'depth_known' => $depth !== null, 'url' => $url];
        if (!$geonet) {
            $magType = $emsc ? ($p['magtype'] ?? null) : ($p['magType'] ?? null);
            $metadata['magType'] = is_string($magType) ? $magType : null;
            $metadata['updated'] = is_string($p['lastupdate'] ?? null) ? $p['lastupdate'] : null;
        } elseif (ProviderEventValidator::measurement($p['mmi'] ?? null, 0, 12)) {
            $metadata['mmi'] = (float) $p['mmi'];
        }
        $key = $this->source . '-' . $id;
        return ['id' => strlen($key) <= 24 ? $key : $this->source . '-' . substr(sha1($id), 0, 23 - strlen($this->source)),
            'fecha_hora' => $date->setTimezone(new DateTimeZone(config(\Config\App::class)->appTimezone))->format('Y-m-d H:i:s'),
            'latitud' => round((float) $c[1], 6), 'longitud' => round((float) $c[0], 6),
            'magnitud' => round((float) $mag, 1), 'profundidad' => $depth === null ? 0.0 : round((float) $depth, 2),
            'ubicacion' => $place, 'fuente' => $this->source,
            'estado' => $geonet && is_string($p['quality'] ?? null) ? $p['quality']
                : ($this->source === 'GEOFON' && is_string($p['status'] ?? null) ? substr($p['status'], 0, 20) : 'automatic'),
            'sgc_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
    }
}
