<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Asegura la tabla base de eventos sísmicos en instalaciones nuevas.
 * En bases existentes no hace nada.
 */
class EnsureSismosTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('sismos')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 20],
            'source_id' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'fecha_hora' => ['type' => 'DATETIME'],
            'latitud' => ['type' => 'DECIMAL', 'constraint' => '10,6'],
            'longitud' => ['type' => 'DECIMAL', 'constraint' => '10,6'],
            'magnitud' => ['type' => 'DECIMAL', 'constraint' => '3,1'],
            'profundidad' => ['type' => 'DECIMAL', 'constraint' => '6,2'],
            'ubicacion' => ['type' => 'VARCHAR', 'constraint' => 150],
            'fuente' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SGC'],
            'estado' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'automatic'],
            'sgc_metadata' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('source_id', 'sismos_source_id_unique');
        $this->forge->createTable('sismos');
    }

    public function down(): void
    {
        // No se elimina la tabla de eventos por seguridad.
    }
}
