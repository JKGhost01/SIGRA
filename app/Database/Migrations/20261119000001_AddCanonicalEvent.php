<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCanonicalEvent extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sismos', [
            'canonical_event_id' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
        ]);
        $this->forge->addKey('canonical_event_id', false, false, 'sismos_canonical_idx');
        $this->forge->processIndexes('sismos');
    }

    public function down(): void
    {
        $this->forge->dropColumn('sismos', 'canonical_event_id');
    }
}
