<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Elimina la tabla de suscripciones SMS.
 *
 * La funcionalidad de SMS fue retirada. Las alertas ahora se entregan
 * por notificaciones del navegador y por la app Android.
 */
class DropSmsSubscriptions extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('sms_subscriptions')) {
            $this->forge->dropTable('sms_subscriptions', true);
        }
    }

    public function down(): void
    {
        // No se recrea: la función fue retirada del producto.
    }
}
