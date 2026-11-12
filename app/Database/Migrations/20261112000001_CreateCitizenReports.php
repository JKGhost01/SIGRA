<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCitizenReports extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('citizen_reports_count', 'sismos')) {
            $this->forge->addColumn('sismos', [
                'citizen_reports_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            ]);
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'reported_at' => ['type' => 'DATETIME'],
            'latitude' => ['type' => 'DECIMAL', 'constraint' => '9,6'],
            'longitude' => ['type' => 'DECIMAL', 'constraint' => '9,6'],
            'felt_intensity' => ['type' => 'DECIMAL', 'constraint' => '3,1'],
            'place' => ['type' => 'VARCHAR', 'constraint' => 150],
            'reporter_key' => ['type' => 'CHAR', 'constraint' => 64],
            'network_key' => ['type' => 'CHAR', 'constraint' => 64],
            'event_id' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pending'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'reported_at']);
        $this->forge->addKey('event_id');
        $this->forge->addKey('created_at');
        $this->forge->addKey(['reporter_key', 'created_at']);
        $this->forge->addKey(['network_key', 'created_at']);
        $options = ['ENGINE' => 'InnoDB'];
        $this->forge->createTable('citizen_reports', true, $options);

        // Legacy SGI/reporte rows are retained for audit, but public queries exclude them.
    }

    public function down(): void
    {
        $this->forge->dropTable('citizen_reports', true);
        $this->forge->dropColumn('sismos', 'citizen_reports_count');
    }
}
