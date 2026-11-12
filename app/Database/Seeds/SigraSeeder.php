<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder principal de SIGRA.
 *
 * Deja la base lista para operar: eventos SGC recientes y red ciudadana
 * vacía (los teléfonos se registran solos desde la app).
 */
class SigraSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('SismosSeeder');
    }
}
