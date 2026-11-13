<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Legacy USGS ingestion stored GeoJSON [longitude, latitude] in reversed columns. */
final class RepairUsgsCoordinates extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('sismos')) return;
        $after = '';
        do {
            $rows = $this->db->table('sismos')->select('id, latitud, longitud, sgc_metadata')
                ->where('fuente', 'USGS')->where('id >', $after)->orderBy('id')->limit(200)->get()->getResultArray();
            foreach ($rows as $row) {
                $after = $row['id'];
                $metadata = json_decode((string) ($row['sgc_metadata'] ?? ''), true);
                $metadata = is_array($metadata) ? $metadata : [];
                if (($metadata['coordinate_order'] ?? '') === 'lat-lon-v2') continue;
                // Only the known legacy contract is swapped; invalid rows need source review.
                if (abs((float) $row['longitud']) > 90 || abs((float) $row['latitud']) > 180) continue;
                $metadata['coordinate_order'] = 'lat-lon-v2';
                $metadata['coordinate_repair'] = ['from' => 'legacy-usgs-lon-lat',
                    'previous_latitude' => $row['latitud'], 'previous_longitude' => $row['longitud'],
                    'previous_metadata' => $row['sgc_metadata'], 'at_utc' => gmdate('c')];
                $ok = $this->db->table('sismos')->where('id', $row['id'])->where('fuente', 'USGS')
                    ->where('latitud', $row['latitud'])->where('longitud', $row['longitud'])
                    ->where('sgc_metadata', $row['sgc_metadata'])->update([
                        'latitud' => $row['longitud'], 'longitud' => $row['latitud'],
                        'sgc_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]);
                if (!$ok) throw new \RuntimeException('No se pudieron reparar las coordenadas USGS.');
            }
        } while (count($rows) === 200);
    }

    public function down(): void
    {
        // Scientific corrections persist when code is rolled back. Original values are audited.
    }
}
