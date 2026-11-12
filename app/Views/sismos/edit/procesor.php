<?php

/**
 * sismos/edit/procesor.php
 * -------------------------------------------------------------------
 * Solo entra cuando validator.php determinó que los datos son válidos.
 * Llama al Model para actualizar. El ID no cambia.
 */

$modeloSismos = new \App\Models\SismoModel();

$modeloSismos->update($datos['id'], [
    'fecha_hora'  => date('Y-m-d H:i:s', strtotime($datos['fecha_hora'])),
    'latitud'     => $datos['latitud'],
    'longitud'    => $datos['longitud'],
    'magnitud'    => $datos['magnitud'],
    'profundidad' => $datos['profundidad'],
    'ubicacion'   => $datos['ubicacion'],
    'fuente'      => $datos['fuente'],
    'estado'      => $datos['estado'],
]);

session()->setFlashdata('mensaje', lang('Sigra.formUpdated', [$datos['id']]));

return redirect()->to(site_url('sismos'));
