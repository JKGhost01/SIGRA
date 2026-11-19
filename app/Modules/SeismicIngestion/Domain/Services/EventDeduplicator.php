<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Domain\Services;

use App\Shared\Domain\GeoDistance;
use App\Modules\SeismicCatalog\Domain\Services\SourceProvenance;

/** Correlate observations only when the match is unambiguous in both directions. */
final class EventDeduplicator
{
    public function __construct(private readonly int $seconds, private readonly float $distanceKm, private readonly float $magnitudeDelta = 1.0)
    {
        if ($seconds < 0 || !is_finite($distanceKm) || $distanceKm < 0 || !is_finite($magnitudeDelta) || $magnitudeDelta < 0) throw new \InvalidArgumentException('Umbrales de correlación inválidos.');
    }
    public function unique(array $candidates, array $reference): array { return $this->correlate($candidates, $reference)['unique']; }

    public function correlate(array $candidates, array $reference): array
    {
        $unique = []; $decisions = []; $possible = []; $contenders = [];
        $referenceRefs = []; $referenceKeys = [];
        foreach ($reference as $index => $event) {
            $referenceRefs[$index] = SourceProvenance::references($event);
            $referenceKeys[$index] = array_map(static fn(array $r): string => $r['source'] . ':' . $r['source_event_id'], $referenceRefs[$index]);
        }
        foreach ($candidates as $key => $candidate) {
            $matches = []; $identities = [];
            $candidateRefs = SourceProvenance::references($candidate);
            $otherKeys = array_map(static fn(array $r): string => $r['source'] . ':' . $r['source_event_id'], $candidateRefs);
            $time = strtotime((string) ($candidate['fecha_hora'] ?? ''));
            foreach ($reference as $index => $event) {
                $refs = $referenceRefs[$index];
                if (array_intersect($referenceKeys[$index], $otherKeys) !== []) {
                    $identities[$index] = ['delta_seconds' => 0, 'distance_km' => 0.0];
                    continue;
                }
                // Distinct observations from the same provider must remain distinct.
                if (isset($candidate['fuente'], $event['fuente'])
                    && array_intersect(array_column($refs, 'source'), array_column($candidateRefs, 'source')) !== []) continue;
                if (isset($candidate['magnitud'], $event['magnitud'])
                    && abs((float) $candidate['magnitud'] - (float) $event['magnitud']) > $this->magnitudeDelta) continue;
                $other = strtotime((string) ($event['fecha_hora'] ?? ''));
                if ($time === false || $other === false || abs($time - $other) > $this->seconds) continue;
                if (!isset($candidate['latitud'], $candidate['longitud'], $event['latitud'], $event['longitud'])) continue;
                $distance = GeoDistance::km((float) $candidate['latitud'], (float) $candidate['longitud'],
                    (float) $event['latitud'], (float) $event['longitud']);
                if ($distance <= $this->distanceKm) $matches[$index] = ['delta_seconds' => abs($time - $other), 'distance_km' => round($distance, 3)];
            }
            $possible[$key] = $identities !== [] ? $identities : $matches;
            foreach ($possible[$key] as $index => $_) $contenders[$index][] = $key;
        }
        foreach ($candidates as $key => $candidate) {
            $matches = $possible[$key];
            if (count($matches) !== 1) { $unique[] = $candidate; continue; }
            $index = array_key_first($matches);
            if (count($contenders[$index]) !== 1) { $unique[] = $candidate; continue; }
            $event = $reference[$index];
            $metadata = json_decode((string) ($event['sgc_metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $refs = [];
            foreach (array_merge(SourceProvenance::references($event), SourceProvenance::references($candidate)) as $ref) {
                $refKey = $ref['source'] . ':' . $ref['source_event_id'];
                $refs[$refKey] = array_replace($refs[$refKey] ?? [], $ref);
            }
            $metadata['source_references'] = array_values($refs);
            // Keep the original evidence stable on replay and partial refreshes.
            $metadata['correlation'] ??= $matches[$index] + ['algorithm' => 'space-time-magnitude-v3', 'preferred_source' => (string) ($event['fuente'] ?? 'unknown')];
            $reference[$index]['sgc_metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $decisions[] = ['preferred_id' => $event['id'] ?? null, 'correlated_id' => $candidate['id'] ?? null] + $matches[$index];
        }
        return ['reference' => $reference, 'unique' => $unique, 'decisions' => $decisions];
    }
}
