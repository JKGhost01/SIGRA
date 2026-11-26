<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddReportConsent extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('consent_version', 'citizen_reports')) {
            $this->forge->addColumn('citizen_reports', ['consent_version' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true]]);
        }
        if (!$this->db->fieldExists('consent_at', 'citizen_reports')) {
            $this->forge->addColumn('citizen_reports', ['consent_at' => ['type' => 'DATETIME', 'null' => true]]);
        }
    }

    public function down(): void
    {
        // Authorization evidence is intentionally retained during a code rollback.
    }
}
