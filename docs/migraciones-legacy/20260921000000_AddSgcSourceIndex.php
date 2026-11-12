<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSgcSourceIndex extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('sismos')) {
            return;
        }

        $indices = $this->db->query('SHOW INDEX FROM `sismos`')->getResultArray();
        foreach ($indices as $indice) {
            if (($indice['Key_name'] ?? '') === 'sismos_source_id_unique') {
                return;
            }
        }

        $this->forge->addKey('source_id', false, true, 'sismos_source_id_unique');
        $this->forge->processIndexes('sismos');
    }

    public function down()
    {
        $this->forge->dropKey('sismos', 'sismos_source_id_unique');
    }
}
