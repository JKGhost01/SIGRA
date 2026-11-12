<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Panel público de la Red SIGRA de teléfonos.
 */
class Red extends BaseController
{
    /**
     * GET /red
     */
    public function index()
    {
        return require APPPATH . 'Views/red/index.php';
    }
}
