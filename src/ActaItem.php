<?php

/**
 * Activo incluido en un acta: copia de sus datos en el momento de la entrega.
 * Aporta la pestaña «Actas» (historial de movimientos) en la ficha de cada activo.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Actasentrega;

use CommonDBTM;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Session;

class ActaItem extends CommonDBTM
{
    public static $rightname = 'plugin_actasentrega_acta';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_actasentrega_actas_items';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Activo del acta', 'Activos del acta', $nb, 'actasentrega');
    }

    public function prepareInputForAdd($input)
    {
        return false; // se insertan solo dentro de Acta::generar()
    }

    public function prepareInputForUpdate($input)
    {
        Session::addMessageAfterRedirect(__('Acta generada: su contenido no puede modificarse.', 'actasentrega'), false, ERROR);
        return false;
    }

    public function pre_deleteItem()
    {
        return false;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof CommonDBTM || $item->isNewItem() || !Acta::canView()) {
            return '';
        }
        $nb = 0;
        if ($_SESSION['glpishow_count_on_tabs']) {
            $nb = countElementsInTable(self::getTable(), ['itemtype' => $item::class, 'items_id' => $item->getID()]);
        }
        return self::createTabEntry(__('Actas', 'actasentrega'), $nb, $item::class, Acta::getIcon());
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        /** @var \DBmysql $DB */
        global $DB;

        $filas = iterator_to_array($DB->request([
            'SELECT'    => [
                'a.id', 'a.numero', 'a.tipo', 'a.estado', 'a.fecha_generacion',
                'a.users_id_entrega', 'a.users_id_recibe', 'i.estado_fisico',
            ],
            'FROM'      => self::getTable() . ' AS i',
            'INNER JOIN' => [
                Acta::getTable() . ' AS a' => ['ON' => ['i' => 'plugin_actasentrega_actas_id', 'a' => 'id']],
            ],
            'WHERE'     => ['i.itemtype' => $item::class, 'i.items_id' => $item->getID()],
            'ORDER'     => 'a.id DESC',
        ]), false);

        foreach ($filas as &$f) {
            $f['entrega'] = Acta::nombreUsuario((int) $f['users_id_entrega']);
            $f['recibe']  = Acta::nombreUsuario((int) $f['users_id_recibe']);
            $f['url']     = Acta::getFormURLWithID((int) $f['id']);
        }
        unset($f);

        TemplateRenderer::getInstance()->display('@actasentrega/item_tab.html.twig', [
            'filas'   => $filas,
            'tipos'   => Acta::getTipos(),
            'estados' => Acta::getEstados(),
        ]);
        return true;
    }
}
