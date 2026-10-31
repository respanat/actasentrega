<?php

/**
 * Actas de entrega — plugin para GLPI 11
 *
 * Genera actas de entrega y devolución de activos a partir del inventario de GLPI,
 * con validación de consistencia, huella SHA-256 verificable e inmutabilidad.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Http\Firewall;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Actasentrega\Acta;
use GlpiPlugin\Actasentrega\ActaItem;
use GlpiPlugin\Actasentrega\Profile as ActaProfile;

define('PLUGIN_ACTASENTREGA_VERSION', '0.1.0');
define('PLUGIN_ACTASENTREGA_MIN_GLPI', '11.0.0');
define('PLUGIN_ACTASENTREGA_MAX_GLPI', '11.0.99');

/**
 * Información del plugin y requisitos (guía oficial de Teclib').
 */
function plugin_version_actasentrega(): array
{
    return [
        'name'         => 'Actas de entrega',
        'version'      => PLUGIN_ACTASENTREGA_VERSION,
        'author'       => 'Ramiro Alfonso España Tejada',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/respanat/actasentrega',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ACTASENTREGA_MIN_GLPI,
                'max' => PLUGIN_ACTASENTREGA_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.2',
            ],
        ],
    ];
}

/**
 * Registro de clases, pestañas, menú y rutas públicas.
 */
function plugin_init_actasentrega(): void
{
    /** @var array $PLUGIN_HOOKS */
    /** @var array $CFG_GLPI */
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['actasentrega'] = true;

    // Pestaña de derechos en la ficha del perfil
    Plugin::registerClass(ActaProfile::class, ['addtabon' => ['Profile']]);

    if (!Plugin::isPluginActive('actasentrega')) {
        return;
    }

    // Pestaña «Actas» en la ficha del usuario
    // (document_types: el PDF del acta se vincula también al acta en Documentos)
    Plugin::registerClass(Acta::class, ['addtabon' => ['User'], 'document_types' => true]);

    // Pestaña «Actas» (historial) en la ficha de cada activo asignable
    Plugin::registerClass(ActaItem::class, ['addtabon' => $CFG_GLPI['assignable_types'] ?? ['Computer']]);

    // Listado de actas en Herramientas
    if (Acta::canView()) {
        $PLUGIN_HOOKS[Hooks::MENU_TOADD]['actasentrega'] = ['tools' => Acta::class];
    }

    // Autogestión: «Mis actas» en el menú de la interfaz simplificada
    $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY]['actasentrega']      = '/front/mis_actas.php';
    $PLUGIN_HOOKS[Hooks::HELPDESK_MENU_ENTRY_ICON]['actasentrega'] = 'ti ti-file-certificate';

    // La página de verificación del QR es pública: no exige sesión.
    // Solo muestra datos mínimos (Ley 1581 de 2012: minimización).
    Firewall::addPluginStrategyForLegacyScripts(
        'actasentrega',
        '#^/front/verificar\.php$#',
        Firewall::STRATEGY_NO_CHECK
    );
}

function plugin_actasentrega_check_prerequisites(): bool
{
    return true;
}

function plugin_actasentrega_check_config($verbose = false): bool
{
    return true;
}
