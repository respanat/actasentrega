<?php

/**
 * «Mis actas»: autogestión del usuario. Lista las actas en las que participa y permite descargarlas.
 * Disponible en la interfaz simplificada (menú Plugins) y en la estándar.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Actasentrega\Acta;

Session::checkLoginUser();

$titulo = __('Mis actas', 'actasentrega');
if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpHeader($titulo);
} else {
    Html::header($titulo, '', 'tools', Acta::class);
}

TemplateRenderer::getInstance()->display('@actasentrega/mis_actas.html.twig', [
    'actas'    => Acta::actasDeUsuario((int) Session::getLoginUserID()),
    'tipos'    => Acta::getTipos(),
    'estados'  => Acta::getEstados(),
    'pdf_base' => $CFG_GLPI['root_doc'] . '/plugins/actasentrega/front/descargar.php?id=',
    'verificar_base' => $CFG_GLPI['root_doc'] . '/plugins/actasentrega/front/verificar.php',
]);

if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpFooter();
} else {
    Html::footer();
}
