<?php

/**
 * Instalación y desinstalación del plugin (guía oficial de Teclib').
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Actasentrega\Acta;

function plugin_actasentrega_install(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();
    $migration = new Migration(PLUGIN_ACTASENTREGA_VERSION);

    // Actas: cabecera. El contenido canónico es exactamente lo que se firmó con la huella.
    $table = 'glpi_plugin_actasentrega_actas';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `numero` varchar(20) NOT NULL,
            `anio` smallint NOT NULL,
            `consecutivo` int NOT NULL,
            `tipo` varchar(20) NOT NULL,
            `estado` varchar(20) NOT NULL DEFAULT 'generada',
            `users_id_entrega` int {$sign} NOT NULL DEFAULT '0',
            `users_id_recibe` int {$sign} NOT NULL DEFAULT '0',
            `users_id_tecnico` int {$sign} NOT NULL DEFAULT '0',
            `fecha_generacion` timestamp NULL DEFAULT NULL,
            `observaciones` text,
            `contenido_canonico` longtext,
            `huella_sha256` char(64) DEFAULT NULL,
            `huella_pdf` char(64) DEFAULT NULL,
            `documents_id` int {$sign} NOT NULL DEFAULT '0',
            `motivo_anulacion` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `numero` (`numero`),
            UNIQUE KEY `tipo_anio_consecutivo` (`tipo`, `anio`, `consecutivo`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id_entrega` (`users_id_entrega`),
            KEY `users_id_recibe` (`users_id_recibe`),
            KEY `users_id_tecnico` (`users_id_tecnico`),
            KEY `documents_id` (`documents_id`),
            KEY `estado` (`estado`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // Ítems: copia (snapshot) de los datos del activo en el momento del acta.
    $table = 'glpi_plugin_actasentrega_actas_items';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_actasentrega_actas_id` int {$sign} NOT NULL DEFAULT '0',
            `itemtype` varchar(100) NOT NULL,
            `items_id` int {$sign} NOT NULL DEFAULT '0',
            `nombre` varchar(255) NOT NULL DEFAULT '',
            `serial` varchar(255) NOT NULL DEFAULT '',
            `otherserial` varchar(255) NOT NULL DEFAULT '',
            `fabricante` varchar(255) NOT NULL DEFAULT '',
            `modelo` varchar(255) NOT NULL DEFAULT '',
            `estado_fisico` varchar(100) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_actasentrega_actas_id`, `itemtype`, `items_id`),
            KEY `item` (`itemtype`, `items_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // Eventos: bitácora de generación, descarga, verificación y anulación.
    $table = 'glpi_plugin_actasentrega_eventos';
    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `plugin_actasentrega_actas_id` int {$sign} NOT NULL DEFAULT '0',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `accion` varchar(30) NOT NULL,
            `detalle` varchar(255) DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_actasentrega_actas_id` (`plugin_actasentrega_actas_id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // Derecho del plugin; se concede completo a los perfiles que administran la configuración.
    $migration->addRight(Acta::$rightname, ALLSTANDARDRIGHT, ['config' => UPDATE]);

    $migration->executeMigration();
    return true;
}

function plugin_actasentrega_uninstall(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    // Nota: los PDF generados quedan en Documentos de GLPI; no se borran al desinstalar.
    foreach (
        [
            'glpi_plugin_actasentrega_actas',
            'glpi_plugin_actasentrega_actas_items',
            'glpi_plugin_actasentrega_eventos',
        ] as $table
    ) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    ProfileRight::deleteProfileRights([Acta::$rightname]);
    return true;
}
