<?php

/**
 * Pestaña de derechos del plugin en la ficha del perfil.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Actasentrega;

use CommonGLPI;
use Html;
use Profile as GlpiProfile;
use Session;

class Profile extends CommonGLPI
{
    public static $rightname = 'profile';

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile && $item->getField('interface') === 'central') {
            return self::createTabEntry(Acta::getTypeName(2), 0, $item::class, Acta::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof GlpiProfile) {
            return false;
        }
        $profile = new GlpiProfile();
        $profile->getFromDB($item->getID());
        $canedit = Session::haveRightsOr('profile', [CREATE, UPDATE, PURGE]);

        echo "<div class='spaced'>";
        if ($canedit) {
            echo "<form method='post' action='" . htmlspecialchars($profile->getFormURL()) . "'>";
        }
        $profile->displayRightsChoiceMatrix(
            [[
                'itemtype' => Acta::class,
                'label'    => Acta::getTypeName(2),
                'field'    => Acta::$rightname,
            ]],
            ['canedit' => $canedit, 'title' => Acta::getTypeName(2)]
        );
        if ($canedit) {
            echo "<div class='text-center'>";
            echo Html::hidden('id', ['value' => $item->getID()]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo '</div>';
            Html::closeForm();
        }
        echo '</div>';
        return true;
    }
}
