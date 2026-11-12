<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSmsSubscriptions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'phone_encrypted' => ['type' => 'TEXT'],
            'phone_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'phone_last4' => ['type' => 'CHAR', 'constraint' => 4],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'verification_code_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'verification_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'consent_at' => ['type' => 'DATETIME', 'null' => true],
            'confirmed_at' => ['type' => 'DATETIME', 'null' => true],
            'last_sent_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('phone_hash', false, true, 'sms_phone_hash_unique');
        $this->forge->createTable('sms_subscriptions');
    }

    public function down()
    {
        $this->forge->dropTable('sms_subscriptions', true);
    }
}
