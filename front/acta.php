<?php

/**
 * Listado de actas (Herramientas > Actas de entrega).
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Actasentrega\Acta;

Session::checkRight(Acta::$rightname, READ);

Html::header(Acta::getTypeName(Session::getPluralNumber()), '', 'tools', Acta::class);
Search::show(Acta::class);
Html::footer();
