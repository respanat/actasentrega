<?php

/**
 * Exporta a CSV el listado del panel con los filtros aplicados.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Actasentrega\Acta;
use GlpiPlugin\Actasentrega\Panel;

Session::checkRight(Acta::$rightname, READ);
Panel::exportarCsv($_GET);
