<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
final class CreateArchitectureAudit extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'batch_id' => ['type' => 'CHAR', 'constraint' => 32],
            'started_at_utc' => ['type' => 'DATETIME'],
            'finished_at_utc' => ['type' => 'DATETIME'],
            'duration_ms' => ['type' => 'INT', 'unsigned' => true],
            'saved_count' => ['type' => 'INT', 'unsigned' => true],
            'summary' => ['type' => 'TEXT'],
        ]);
        $this->forge->addKey('batch_id', true);
        $this->forge->addKey('started_at_utc');
        $this->forge->createTable('sigra_ingestion_batches', true, ['ENGINE' => 'InnoDB']);
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'candidate_id' => ['type' => 'VARCHAR', 'constraint' => 64],
            'state' => ['type' => 'VARCHAR', 'constraint' => 24],
            'algorithm_version' => ['type' => 'VARCHAR', 'constraint' => 32],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 160],
            'evidence' => ['type' => 'TEXT'],
            'recorded_at_utc' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['candidate_id', 'recorded_at_utc']);
        $this->forge->addKey('recorded_at_utc');
        $this->forge->createTable('sigra_alert_decisions', true, ['ENGINE' => 'InnoDB']);
    }
    public function down(): void
    {
        $this->forge->dropTable('sigra_alert_decisions', true);
        $this->forge->dropTable('sigra_ingestion_batches', true);
    }
}
