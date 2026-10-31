<?php

/**
 * Descarga controlada del PDF de un acta.
 * Pueden descargarla los gestores con derecho de lectura y las propias partes del acta
 * (autogestión desde la interfaz simplificada), aunque no tengan permisos sobre Documentos.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Actasentrega\Acta;

Session::checkLoginUser();

$acta = new Acta();
if (!$acta->getFromDB((int) ($_GET['id'] ?? 0)) || !$acta->puedeVerPropia()) {
    throw new AccessDeniedHttpException();
}
$acta->enviarPdf();
