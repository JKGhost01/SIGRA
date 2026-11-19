<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Domain\Services;

/** Keep previously observed provider references when a later batch is partial. */
final class SourceProvenance
{
    public static function references(array $event): array
    {
        $metadata = json_decode((string) ($event['sgc_metadata'] ?? ''), true);
        $own = ['source' => (string) ($event['fuente'] ?? 'unknown'),
            'source_event_id' => (string) ($metadata['source_id'] ?? $event['id'] ?? '')];
        if (isset($event['id'])) $own['event_id'] = (string) $event['id'];
        if (is_string($metadata['url'] ?? null)) $own['url'] = $metadata['url'];
        $references = [];
        foreach (array_merge([$own], is_array($metadata['source_references'] ?? null) ? $metadata['source_references'] : []) as $reference) {
            if (!is_array($reference) || !is_string($reference['source'] ?? null)
                || !is_string($reference['source_event_id'] ?? null)
                || $reference['source'] === '' || $reference['source_event_id'] === '') continue;
            $key = $reference['source'] . ':' . $reference['source_event_id'];
            $references[$key] = array_replace($references[$key] ?? [], $reference);
        }
        $ownKey = $own['source'] . ':' . $own['source_event_id'];
        if (isset($references[$ownKey])) $references[$ownKey] = array_replace($references[$ownKey], $own);
        return array_values($references);
    }

    public static function sources(array $event): array
    {
        return array_values(array_unique(array_column(self::references($event), 'source')));
    }

    public static function retain(array $previous, array $incoming): array
    {
        if (($previous['fuente'] ?? null) !== ($incoming['fuente'] ?? null)) return $incoming;
        $old = json_decode((string) ($previous['sgc_metadata'] ?? ''), true);
        $new = json_decode((string) ($incoming['sgc_metadata'] ?? ''), true);
        if (!is_array($old) || !is_array($new) || empty($old['source_references'])
            || !is_array($old['source_references'])) return $incoming;
        if (($old['source_id'] ?? $previous['id'] ?? null) !== ($new['source_id'] ?? $incoming['id'] ?? null)) return $incoming;

        $references = [];
        foreach ([$old['source_references'], $new['source_references'] ?? []] as $list) {
            if (!is_array($list)) continue;
            foreach ($list as $reference) {
                if (!is_array($reference) || !is_string($reference['source'] ?? null)
                    || !is_string($reference['source_event_id'] ?? null)
                    || $reference['source'] === '' || $reference['source_event_id'] === '') continue;
                $key = $reference['source'] . ':' . $reference['source_event_id'];
                $references[$key] = array_replace($references[$key] ?? [], $reference);
            }
        }
        if ($references !== []) {
            $new['source_references'] = array_values($references);
            if (!isset($new['correlation']) && isset($old['correlation'])) $new['correlation'] = $old['correlation'];
            $incoming['sgc_metadata'] = json_encode($new, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $incoming;
    }
}
