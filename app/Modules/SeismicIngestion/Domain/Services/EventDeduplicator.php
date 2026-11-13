<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Domain\Services;

use App\Shared\Domain\GeoDistance;

/** SGC wins an unambiguous correlation; all provider references remain auditable. */
final class EventDeduplicator
{
    public function __construct(private readonly int $seconds, private readonly float $distanceKm, private readonly float $magnitudeDelta = 1.0)
    {
        if ($seconds < 0 || !is_finite($distanceKm) || $distanceKm < 0 || !is_finite($magnitudeDelta) || $magnitudeDelta < 0) throw new \InvalidArgumentException('Umbrales de correlación inválidos.');
    }
    public function unique(array $candidates, array $reference): array { return $this->correlate($candidates, $reference)['unique']; }
    public function correlate(array $candidates, array $reference): array
    {
        $unique = []; $decisions = [];
        foreach ($candidates as $candidate) {
            $matches = []; $time = strtotime((string) ($candidate['fecha_hora'] ?? ''));
            foreach ($reference as $index => $event) {
                if (isset($candidate['magnitud'], $event['magnitud'])
                    && abs((float) $candidate['magnitud'] - (float) $event['magnitud']) > $this->magnitudeDelta) continue;
                $other = strtotime((string) ($event['fecha_hora'] ?? ''));
                if ($time === false || $other === false || abs($time - $other) > $this->seconds) continue;
                if (!isset($candidate['latitud'], $candidate['longitud'], $event['latitud'], $event['longitud'])) continue;
                $distance = GeoDistance::km((float) $candidate['latitud'], (float) $candidate['longitud'],
                    (float) $event['latitud'], (float) $event['longitud']);
                if ($distance <= $this->distanceKm) $matches[$index] = ['delta_seconds' => abs($time - $other), 'distance_km' => round($distance, 3)];
            }
            // Two close official events cannot be resolved by the old space/time heuristic.
            if (count($matches) !== 1) { $unique[] = $candidate; continue; }
            $index = array_key_first($matches);
            $event = $reference[$index];
            $metadata = json_decode((string) ($event['sgc_metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $refs = [];
            foreach (array_merge($metadata['source_references'] ?? [], [$this->sourceReference($event), $this->sourceReference($candidate)]) as $ref) {
                $refs[$ref['source'] . ':' . $ref['source_event_id']] = $ref;
            }
            $metadata['source_references'] = array_values($refs);
            $metadata['correlation'] = $matches[$index] + ['algorithm' => 'space-time-magnitude-v2', 'preferred_source' => 'SGC'];
            $reference[$index]['sgc_metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $decisions[] = ['preferred_id' => $event['id'] ?? null, 'correlated_id' => $candidate['id'] ?? null] + $matches[$index];
        }
        return ['reference' => $reference, 'unique' => $unique, 'decisions' => $decisions];
    }
    private function sourceReference(array $event): array
    {
        $metadata = json_decode((string) ($event['sgc_metadata'] ?? ''), true);
        return ['source' => (string) ($event['fuente'] ?? 'unknown'),
            'source_event_id' => (string) ($metadata['source_id'] ?? $event['id'] ?? '')];
    }
}
