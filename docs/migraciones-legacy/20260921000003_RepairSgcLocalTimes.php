<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use DateTimeImmutable;
use DateTimeZone;

class RepairSgcLocalTimes extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('sismos')) {
            return;
        }

        $zonaLocal = new DateTimeZone(config('App')->appTimezone);
        $eventos = $this->db->table('sismos')
            ->where('fuente', 'SGC')
            ->get()
            ->getResultArray();

        foreach ($eventos as $evento) {
            $metadata = json_decode((string) ($evento['sgc_metadata'] ?? ''), true);
            $horaLocal = is_array($metadata) ? ($metadata['localTime'] ?? null) : null;
            if (!$horaLocal) {
                continue;
            }

            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d H:i', (string) $horaLocal, $zonaLocal);
            if (!$fecha) {
                continue;
            }

            $this->db->table('sismos')
                ->where('id', $evento['id'])
                ->update(['fecha_hora' => $fecha->format('Y-m-d H:i:s')]);
        }
    }

    public function down()
    {
        // The previous values were ambiguous UTC/local timestamps and cannot be restored safely.
    }
}