<?php

/**
 * Panel de gestión de actas (Herramientas › Actas de entrega).
 * Con ?avanzada=1 muestra el buscador estándar de GLPI.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Actasentrega\Acta;
use GlpiPlugin\Actasentrega\Panel;

Session::checkRight(Acta::$rightname, READ);

Html::header(Acta::getTypeName(Session::getPluralNumber()), '', 'tools', Acta::class);
if (!empty($_GET['avanzada'])) {
    echo "<div class='mb-2'><a href='" . htmlspecialchars(Acta::getSearchURL()) . "'><i class='ti ti-arrow-left'></i> "
        . __('Volver al panel', 'actasentrega') . "</a></div>";
    Search::show(Acta::class);
} else {
    Panel::mostrar($_GET);
}
Html::footer();
