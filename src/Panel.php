<?php

/**
 * Panel de gestión de actas: indicadores, pendientes de firma por antigüedad y listado filtrable.
 * Protocols Manager solo lista actas dentro de la ficha de cada usuario y no tiene estados;
 * este panel integra la gestión de todas las actas en una sola vista.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Actasentrega;

use Glpi\Application\View\TemplateRenderer;
use User;

final class Panel
{
    public const POR_PAGINA = 20;

    public const PERIODOS = [
        'mes'       => 'Este mes',
        'trimestre' => 'Últimos 3 meses',
        'anio'      => 'Este año',
        'todo'      => 'Todo',
    ];

    /** Filtros saneados a partir de $_GET. */
    public static function filtros(array $get): array
    {
        $estado  = (string) ($get['estado'] ?? '');
        $tipo    = (string) ($get['tipo'] ?? '');
        $periodo = (string) ($get['periodo'] ?? 'todo');
        return [
            'q'       => mb_substr(trim((string) ($get['q'] ?? '')), 0, 80),
            'estado'  => array_key_exists($estado, Acta::getEstados()) ? $estado : '',
            'tipo'    => array_key_exists($tipo, Acta::getTipos()) ? $tipo : '',
            'periodo' => array_key_exists($periodo, self::PERIODOS) ? $periodo : 'todo',
            'pagina'  => max(1, (int) ($get['pagina'] ?? 1)),
        ];
    }

    /** Criterios WHERE (iterador de GLPI) para los filtros dados, con restricción de entidades. */
    public static function criterios(array $f): array
    {
        $t = Acta::getTable();
        $where = getEntitiesRestrictCriteria($t, '', '', false);

        if ($f['estado'] !== '') {
            $where["$t.estado"] = $f['estado'];
        }
        if ($f['tipo'] !== '') {
            $where["$t.tipo"] = $f['tipo'];
        }
        $desde = match ($f['periodo']) {
            'mes'       => date('Y-m-01 00:00:00'),
            'trimestre' => date('Y-m-d 00:00:00', strtotime('-3 months')),
            'anio'      => date('Y-01-01 00:00:00'),
            default     => null,
        };
        if ($desde !== null) {
            $where["$t.fecha_generacion"] = ['>=', $desde];
        }
        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';
            $where[] = ['OR' => [
                "$t.numero"     => ['LIKE', $like],
                'recibe.name'   => ['LIKE', $like],
                'recibe.realname'  => ['LIKE', $like],
                'recibe.firstname' => ['LIKE', $like],
                'entrega.name'  => ['LIKE', $like],
                'entrega.realname' => ['LIKE', $like],
                "$t.id" => new \Glpi\DBAL\QuerySubQuery([
                    'SELECT' => 'plugin_actasentrega_actas_id',
                    'FROM'   => ActaItem::getTable(),
                    'WHERE'  => ['OR' => [
                        'nombre'      => ['LIKE', $like],
                        'serial'      => ['LIKE', $like],
                        'otherserial' => ['LIKE', $like],
                    ]],
                ]),
            ]];
        }
        return $where;
    }

    private static function joins(): array
    {
        $t = Acta::getTable();
        return [
            'glpi_users AS recibe'  => ['ON' => ['recibe' => 'id', $t => 'users_id_recibe']],
            'glpi_users AS entrega' => ['ON' => ['entrega' => 'id', $t => 'users_id_entrega']],
        ];
    }

    /** Filas del listado (con nombres de activos). $limite = null para exportar todo. */
    public static function filas(array $f, ?int $limite = self::POR_PAGINA): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $t = Acta::getTable();
        $consulta = [
            'SELECT'    => ["$t.*"],
            'FROM'      => $t,
            'LEFT JOIN' => self::joins(),
            'WHERE'     => self::criterios($f),
            'ORDER'     => "$t.id DESC",
        ];
        if ($limite !== null) {
            $consulta['LIMIT']  = $limite;
            $consulta['START']  = ($f['pagina'] - 1) * $limite;
        }

        $filas = iterator_to_array($DB->request($consulta), false);
        $ids = array_column($filas, 'id');
        $activos = [];
        if ($ids !== []) {
            foreach ($DB->request(['FROM' => ActaItem::getTable(), 'WHERE' => ['plugin_actasentrega_actas_id' => $ids]]) as $i) {
                $activos[$i['plugin_actasentrega_actas_id']][] = $i;
            }
        }
        foreach ($filas as &$fila) {
            $items = $activos[$fila['id']] ?? [];
            $fila['activos'] = $items;
            $fila['activos_texto'] = implode(', ', array_map(fn ($i) => $i['nombre'], $items));
            $fila['recibe']  = Acta::nombreUsuario((int) $fila['users_id_recibe']);
            $fila['entrega'] = Acta::nombreUsuario((int) $fila['users_id_entrega']);
            $fila['tecnico'] = Acta::nombreUsuario((int) $fila['users_id_tecnico']);
            $fila['dias']    = self::diasDesde((string) $fila['fecha_generacion']);
        }
        unset($fila);
        return $filas;
    }

    public static function total(array $f): int
    {
        /** @var \DBmysql $DB */
        global $DB;
        $t = Acta::getTable();
        $r = $DB->request([
            'COUNT'     => 'c',
            'FROM'      => $t,
            'LEFT JOIN' => self::joins(),
            'WHERE'     => self::criterios($f),
        ])->current();
        return (int) ($r['c'] ?? 0);
    }

    /** Indicadores del encabezado. */
    public static function indicadores(): array
    {
        /** @var \DBmysql $DB */
        /** @var array $CFG_GLPI */
        global $DB, $CFG_GLPI;

        $t = Acta::getTable();
        $ent = getEntitiesRestrictCriteria($t, '', '', false);
        $contar = fn (array $w) => (int) ($DB->request(['COUNT' => 'c', 'FROM' => $t, 'WHERE' => $ent + $w])->current()['c'] ?? 0);
        $inicioMes = date('Y-m-01 00:00:00');

        $ind = [
            'total'           => $contar([]),
            'total_mes'       => $contar(['fecha_generacion' => ['>=', $inicioMes]]),
            'pendientes'      => $contar(['estado' => Acta::ESTADO_GENERADA]),
            'firmadas_mes'    => $contar(['estado' => Acta::ESTADO_FIRMADA, 'date_mod' => ['>=', $inicioMes]]),
            'anuladas'        => $contar(['estado' => Acta::ESTADO_ANULADA]),
            'por_tipo'        => [],
            'antiguedad'      => ['0-3' => 0, '4-7' => 0, '8+' => 0],
            'pendientes_lista' => [],
        ];
        foreach (array_keys(Acta::getTipos()) as $tipo) {
            $ind['por_tipo'][$tipo] = $contar(['tipo' => $tipo, 'estado' => ['<>', Acta::ESTADO_ANULADA]]);
        }

        // Pendientes de firma: antigüedad y las más atrasadas
        $pend = iterator_to_array($DB->request([
            'FROM'  => $t,
            'WHERE' => $ent + ['estado' => Acta::ESTADO_GENERADA],
            'ORDER' => 'fecha_generacion ASC',
        ]), false);
        foreach ($pend as $p) {
            $d = self::diasDesde((string) $p['fecha_generacion']);
            $ind['antiguedad'][$d <= 3 ? '0-3' : ($d <= 7 ? '4-7' : '8+')]++;
        }
        foreach (array_slice($pend, 0, 5) as $p) {
            $ind['pendientes_lista'][] = [
                'id'     => $p['id'],
                'numero' => $p['numero'],
                'recibe' => Acta::nombreUsuario((int) $p['users_id_recibe']),
                'dias'   => self::diasDesde((string) $p['fecha_generacion']),
            ];
        }

        // Cobertura: activos asignados en GLPI que tienen al menos un acta vigente (no anulada)
        $asignados = 0;
        $conActa = 0;
        foreach ($CFG_GLPI['assignable_types'] ?? [] as $itemtype) {
            $item = getItemForItemtype($itemtype);
            if (!$item || !$DB->fieldExists($item::getTable(), 'users_id')) {
                continue;
            }
            $w = ['users_id' => ['>', 0]];
            if ($item->maybeDeleted()) {
                $w['is_deleted'] = 0;
            }
            if ($item->maybeTemplate()) {
                $w['is_template'] = 0;
            }
            if ($item->isEntityAssign()) {
                $w += getEntitiesRestrictCriteria($item::getTable(), '', '', $item->maybeRecursive());
            }
            $asignados += (int) ($DB->request(['COUNT' => 'c', 'FROM' => $item::getTable(), 'WHERE' => $w])->current()['c'] ?? 0);
            $conActa += (int) ($DB->request([
                'COUNT'      => 'c',
                'FROM'       => $item::getTable(),
                'WHERE'      => $w + [
                    $item::getTable() . '.id' => new \Glpi\DBAL\QuerySubQuery([
                        'SELECT'     => 'i.items_id',
                        'FROM'       => ActaItem::getTable() . ' AS i',
                        'INNER JOIN' => [$t . ' AS a' => ['ON' => ['i' => 'plugin_actasentrega_actas_id', 'a' => 'id']]],
                        'WHERE'      => ['i.itemtype' => $itemtype, 'a.estado' => ['<>', Acta::ESTADO_ANULADA]],
                    ]),
                ],
            ])->current()['c'] ?? 0);
        }
        $ind['activos_asignados'] = $asignados;
        $ind['activos_con_acta']  = $conActa;
        $ind['cobertura']         = $asignados > 0 ? round(100 * $conActa / $asignados) : 0;

        return $ind;
    }

    public static function diasDesde(string $fecha): int
    {
        $ts = strtotime($fecha);
        return $ts === false ? 0 : max(0, (int) floor((time() - $ts) / 86400));
    }

    public static function mostrar(array $get): void
    {
        $f = self::filtros($get);
        $total = self::total($f);
        TemplateRenderer::getInstance()->display('@actasentrega/panel.html.twig', [
            'ind'        => self::indicadores(),
            'filas'      => self::filas($f),
            'total'      => $total,
            'f'          => $f,
            'paginas'    => max(1, (int) ceil($total / self::POR_PAGINA)),
            'tipos'      => Acta::getTipos(),
            'estados'    => Acta::getEstados(),
            'periodos'   => self::PERIODOS,
            'form_url'   => Acta::getFormURL(),
            'panel_url'  => Acta::getSearchURL(),
            'export_url' => $GLOBALS['CFG_GLPI']['root_doc'] . '/plugins/actasentrega/front/exportar.php',
            'pdf_base'   => $GLOBALS['CFG_GLPI']['root_doc'] . '/plugins/actasentrega/front/descargar.php?id=',
            'puede_generar' => Acta::canCreate(),
            'dropdown_usuario' => User::dropdown([
                'name'    => 'users_id',
                'right'   => 'all',
                'display' => false,
                'width'   => '100%',
            ]),
            'user_form_url' => User::getFormURL(),
        ]);
    }

    /** Exporta el listado filtrado en CSV (UTF-8 con BOM para Excel). */
    public static function exportarCsv(array $get): void
    {
        $f = self::filtros($get);
        $tipos = Acta::getTipos();
        $estados = Acta::getEstados();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="actas_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Número', 'Tipo', 'Estado', 'Fecha', 'Recibe', 'Entrega', 'Generada por', 'Activos', 'Días', 'Huella SHA-256'], ';');
        foreach (self::filas($f, null) as $r) {
            fputcsv($out, [
                $r['numero'], $tipos[$r['tipo']] ?? $r['tipo'], $estados[$r['estado']] ?? $r['estado'],
                $r['fecha_generacion'], $r['recibe'], $r['entrega'], $r['tecnico'], $r['activos_texto'],
                $r['dias'], $r['huella_sha256'],
            ], ';');
        }
        fclose($out);
    }
}
