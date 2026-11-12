<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSgcMetadata extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('sismos')) {
            return;
        }

        $columnas = $this->db->query('SHOW COLUMNS FROM `sismos`')->getResultArray();
        foreach ($columnas as $columna) {
            if (($columna['Field'] ?? '') === 'sgc_metadata') {
                return;
            }
        }

        $this->forge->addColumn('sismos', [
            'sgc_metadata' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'estado',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sismos', 'sgc_metadata');
    }
}
