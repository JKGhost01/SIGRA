<?php

/**
 * sismos/delete/procesor.php
 * -------------------------------------------------------------------
 * Ejecuta la eliminación llamando al Model.
 */

$modeloSismos = new \App\Models\SismoModel();
$modeloSismos->delete($datos['id']);

session()->setFlashdata('mensaje', lang('Sigra.formDeleted', [$datos['id']]));

return redirect()->to(site_url('sismos'));
