<?php

/**
 * Generar, consultar, firmar y anular actas.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Actasentrega\Acta;

Session::checkRight(Acta::$rightname, READ);

$acta = new Acta();

if (isset($_POST['generar'])) {
    Session::checkRight(Acta::$rightname, CREATE);
    $id = $acta->generar($_POST);
    Html::redirect($id ? Acta::getFormURLWithID($id) : Html::getBackUrl());
}

if (isset($_POST['firmar'])) {
    $acta->check((int) $_POST['id'], UPDATE);
    $acta->update(['id' => (int) $_POST['id'], 'estado' => Acta::ESTADO_FIRMADA]);
    Html::back();
}

if (isset($_POST['anular'])) {
    $acta->check((int) $_POST['id'], UPDATE);
    $acta->update([
        'id'               => (int) $_POST['id'],
        'estado'           => Acta::ESTADO_ANULADA,
        'motivo_anulacion' => (string) ($_POST['motivo_anulacion'] ?? ''),
    ]);
    Html::back();
}

if (isset($_POST['update']) || isset($_POST['add']) || isset($_POST['delete']) || isset($_POST['purge'])) {
    // Edición genérica deshabilitada: el contenido de un acta es inmutable.
    Session::addMessageAfterRedirect(__('Operación no permitida sobre un acta.', 'actasentrega'), false, ERROR);
    Html::back();
}

Html::header(Acta::getTypeName(1), '', 'tools', Acta::class);
$acta->display(['id' => (int) ($_GET['id'] ?? 0)]);
Html::footer();
