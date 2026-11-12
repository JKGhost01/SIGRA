<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Libraries\SgcClient;
use App\Models\SismoModel;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use Throwable;

/**
 * Carga los eventos del feed oficial del SGC.
 *
 * Es idempotente: si un evento ya existe y no cambió, no se escribe.
 * Se puede ejecutar con:
 *   php spark db:seed SismosSeeder
 */
class SismosSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $eventos = (new SgcClient())->obtenerEventos();
        } catch (Throwable $exception) {
            CLI::write('No se pudo leer el feed del SGC: ' . $exception->getMessage(), 'yellow');

            return;
        }

        $guardados = (new SismoModel())->guardarEventosSgc($eventos);

        CLI::write(sprintf(
            'SGC: %d eventos leídos, %d nuevos o modificados.',
            count($eventos),
            $guardados,
        ), 'green');
    }
}
