<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Domain\Services;

/** Keep previously observed provider references when a later batch is partial. */
final class SourceProvenance
{
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
                $references[$reference['source'] . ':' . $reference['source_event_id']] = $reference;
            }
        }
        if ($references !== []) {
            $new['source_references'] = array_values($references);
            $incoming['sgc_metadata'] = json_encode($new, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $incoming;
    }
}
