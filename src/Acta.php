<?php

/**
 * Acta de entrega, devolución o traslado de activos.
 *
 * Reglas que diferencian este plugin:
 *  1. Antes de generar, valida que el inventario de GLPI sea consistente con el acta.
 *  2. Cada acta lleva una huella SHA-256 de su contenido canónico, impresa en el PDF con un QR.
 *  3. Una vez generada, el acta no se modifica ni se borra: solo se marca firmada o se anula con motivo.
 *  4. Todo queda en una bitácora de eventos.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Actasentrega;

use CommonDBTM;
use CommonGLPI;
use Document;
use Document_Item;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Session;
use Throwable;
use User;

class Acta extends CommonDBTM
{
    public static $rightname = 'plugin_actasentrega_acta';

    public $dohistory = true;

    public const ESTADO_GENERADA = 'generada';
    public const ESTADO_FIRMADA  = 'firmada';
    public const ESTADO_ANULADA  = 'anulada';

    /** Campos que nunca cambian después de generar el acta. */
    private const INMUTABLES = [
        'entities_id', 'numero', 'anio', 'consecutivo', 'tipo', 'users_id_entrega', 'users_id_recibe',
        'users_id_tecnico', 'fecha_generacion', 'observaciones', 'contenido_canonico', 'huella_sha256',
        'huella_pdf', 'documents_id', 'date_creation',
    ];

    /** Transiciones de estado permitidas. */
    private const TRANSICIONES = [
        self::ESTADO_GENERADA => [self::ESTADO_FIRMADA, self::ESTADO_ANULADA],
        self::ESTADO_FIRMADA  => [self::ESTADO_ANULADA],
        self::ESTADO_ANULADA  => [],
    ];

    public static function getTypeName($nb = 0)
    {
        return _n('Acta de entrega', 'Actas de entrega', $nb, 'actasentrega');
    }

    public static function getIcon()
    {
        return 'ti ti-file-certificate';
    }

    /** El número del acta hace las veces de nombre en GLPI (títulos, enlaces, búsquedas). */
    public static function getNameField()
    {
        return 'numero';
    }

    public static function getTipos(): array
    {
        return [
            ActaBuilder::TIPO_ENTREGA    => __('Entrega', 'actasentrega'),
            ActaBuilder::TIPO_DEVOLUCION => __('Devolución', 'actasentrega'),
            ActaBuilder::TIPO_TRASLADO   => __('Traslado', 'actasentrega'),
        ];
    }

    public static function getEstados(): array
    {
        return [
            self::ESTADO_GENERADA => __('Generada', 'actasentrega'),
            self::ESTADO_FIRMADA  => __('Firmada', 'actasentrega'),
            self::ESTADO_ANULADA  => __('Anulada', 'actasentrega'),
        ];
    }

    /** Derechos: leer, generar (CREATE) y firmar/anular (UPDATE). No hay borrado. */
    public function getRights($interface = 'central')
    {
        return [
            READ   => __('Read'),
            CREATE => __('Generar', 'actasentrega'),
            UPDATE => __('Firmar / anular', 'actasentrega'),
        ];
    }

    // ------------------------------------------------------------------
    // Inmutabilidad
    // ------------------------------------------------------------------

    public function prepareInputForAdd($input)
    {
        // Solo se crean actas a través de generar(), que valida y calcula la huella.
        if (empty($input['_desde_generar'])) {
            Session::addMessageAfterRedirect(__('Las actas solo se crean desde la pestaña «Actas» del usuario.', 'actasentrega'), false, ERROR);
            return false;
        }
        unset($input['_desde_generar']);
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        foreach (self::INMUTABLES as $campo) {
            if (array_key_exists($campo, $input) && (string) $input[$campo] !== (string) ($this->fields[$campo] ?? '')) {
                Session::addMessageAfterRedirect(__('Acta generada: su contenido no puede modificarse.', 'actasentrega'), false, ERROR);
                return false;
            }
        }

        $actual = $this->fields['estado'];
        if (isset($input['estado']) && $input['estado'] !== $actual) {
            if (!in_array($input['estado'], self::TRANSICIONES[$actual] ?? [], true)) {
                Session::addMessageAfterRedirect(__('Cambio de estado no permitido.', 'actasentrega'), false, ERROR);
                return false;
            }
            if ($input['estado'] === self::ESTADO_ANULADA && trim((string) ($input['motivo_anulacion'] ?? '')) === '') {
                Session::addMessageAfterRedirect(__('Para anular un acta debe indicar el motivo.', 'actasentrega'), false, ERROR);
                return false;
            }
        } elseif (array_key_exists('motivo_anulacion', $input)) {
            // El motivo solo se escribe en el momento de anular
            unset($input['motivo_anulacion']);
        }
        return $input;
    }

    public function post_updateItem($history = true)
    {
        if (in_array('estado', $this->updates, true)) {
            $detalle = $this->fields['estado'] === self::ESTADO_ANULADA ? (string) $this->fields['motivo_anulacion'] : null;
            self::registrarEvento($this->getID(), $this->fields['estado'], $detalle);
        }
        parent::post_updateItem($history);
    }

    public function pre_deleteItem()
    {
        Session::addMessageAfterRedirect(__('Las actas no se eliminan; puede anularlas indicando el motivo.', 'actasentrega'), false, ERROR);
        return false;
    }

    // ------------------------------------------------------------------
    // Generación
    // ------------------------------------------------------------------

    /**
     * Genera el acta a partir de lo enviado por el formulario de la pestaña del usuario.
     *
     * @return int|false id del acta generada
     */
    public function generar(array $post): int|false
    {
        /** @var \DBmysql $DB */
        global $DB;

        $tipo     = (string) ($post['tipo'] ?? '');
        $users_id = (int) ($post['users_id'] ?? 0);   // usuario de la ficha
        $tecnico  = (int) Session::getLoginUserID();

        [$entrega, $recibe] = match ($tipo) {
            ActaBuilder::TIPO_ENTREGA    => [$tecnico, $users_id],
            ActaBuilder::TIPO_DEVOLUCION => [$users_id, $tecnico],
            ActaBuilder::TIPO_TRASLADO   => [(int) ($post['users_id_anterior'] ?? 0), $users_id],
            default                      => [0, 0],
        };

        // Activos seleccionados, leídos de GLPI en este instante (no del formulario)
        $activos = [];
        foreach ((array) ($post['activos'] ?? []) as $clave) {
            [$itemtype, $id] = array_pad(explode('#', (string) $clave, 2), 2, '');
            $fila = self::leerActivo($itemtype, (int) $id);
            if ($fila === null) {
                Session::addMessageAfterRedirect(sprintf(__('El activo %s no existe o no tiene permiso para verlo.', 'actasentrega'), htmlspecialchars($clave)), false, ERROR);
                return false;
            }
            $fila['estado_fisico'] = trim((string) ($post['estado_fisico'][$clave] ?? ''));
            $activos[] = $fila;
        }

        $anio = (int) date('Y');
        $datos = [
            'tipo'          => $tipo,
            'fecha'         => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            'entidad'       => (int) ($_SESSION['glpiactive_entity'] ?? 0),
            'entrega'       => ['id' => $entrega, 'nombre' => self::nombreUsuario($entrega)],
            'recibe'        => ['id' => $recibe, 'nombre' => self::nombreUsuario($recibe)],
            'observaciones' => (string) ($post['observaciones'] ?? ''),
            'activos'       => $activos,
        ];

        $errores = ActaBuilder::validar($datos);
        if ($errores !== []) {
            Session::addMessageAfterRedirect(
                __('No se generó el acta porque el inventario no es consistente:', 'actasentrega')
                . '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $errores)) . '</li></ul>',
                false,
                ERROR
            );
            return false;
        }

        $tmpfile = null;
        $DB->beginTransaction();
        try {
            // Consecutivo por tipo y año; la clave única protege ante generaciones simultáneas
            $max = $DB->request([
                'SELECT' => ['MAX' => 'consecutivo AS m'],
                'FROM'   => self::getTable(),
                'WHERE'  => ['tipo' => $tipo, 'anio' => $anio],
            ])->current();
            $consecutivo     = (int) ($max['m'] ?? 0) + 1;
            $datos['numero'] = ActaBuilder::numero($tipo, $anio, $consecutivo);

            $canonico = ActaBuilder::canonico($datos);
            $huella   = hash('sha256', $canonico);

            // PDF con la huella y el QR de verificación
            $pdf     = PdfRenderer::render($datos, $huella, self::urlVerificacion($datos['numero'], $huella));
            $prefix  = uniqid('', true);
            $nombre  = $datos['numero'] . '.pdf';
            $tmpfile = GLPI_TMP_DIR . '/' . $prefix . $nombre;
            file_put_contents($tmpfile, $pdf);

            // Document::add() deja mensajes técnicos («Document copy succeeded»); no se muestran al usuario.
            $mensajes_previos = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
            $doc = new Document();
            $documents_id = $doc->add([
                'name'             => sprintf('%s %s', self::getTipos()[$tipo], $datos['numero']),
                'entities_id'      => $datos['entidad'],
                'is_recursive'     => 0,
                'comment'          => 'SHA-256: ' . $huella,
                '_filename'        => [$prefix . $nombre],
                '_prefix_filename' => [$prefix],
                '_no_message'      => true,
            ]);
            $_SESSION['MESSAGE_AFTER_REDIRECT'] = $mensajes_previos;
            if (!$documents_id) {
                throw new \RuntimeException('No se pudo guardar el PDF en Documentos.');
            }

            $acta_id = $this->add([
                '_desde_generar'     => true,
                'entities_id'        => $datos['entidad'],
                'numero'             => $datos['numero'],
                'anio'               => $anio,
                'consecutivo'        => $consecutivo,
                'tipo'               => $tipo,
                'estado'             => self::ESTADO_GENERADA,
                'users_id_entrega'   => $entrega,
                'users_id_recibe'    => $recibe,
                'users_id_tecnico'   => $tecnico,
                'fecha_generacion'   => $datos['fecha'],
                'observaciones'      => $datos['observaciones'],
                'contenido_canonico' => $canonico,
                'huella_sha256'      => $huella,
                'huella_pdf'         => hash('sha256', $pdf),
                'documents_id'       => $documents_id,
            ]);
            if (!$acta_id) {
                throw new \RuntimeException('No se pudo registrar el acta.');
            }

            foreach ($activos as $a) {
                $DB->insert(ActaItem::getTable(), [
                    'plugin_actasentrega_actas_id' => $acta_id,
                    'itemtype'      => $a['itemtype'],
                    'items_id'      => $a['items_id'],
                    'nombre'        => $a['nombre'],
                    'serial'        => $a['serial'],
                    'otherserial'   => $a['otherserial'],
                    'fabricante'    => $a['fabricante'],
                    'modelo'        => $a['modelo'],
                    'estado_fisico' => $a['estado_fisico'],
                ]);
            }

            // Vincular el PDF al acta, a las personas y a cada activo
            $vinculos = [[self::class, $acta_id], [User::class, $entrega], [User::class, $recibe]];
            foreach ($activos as $a) {
                $vinculos[] = [$a['itemtype'], $a['items_id']];
            }
            $docitem = new Document_Item();
            foreach ($vinculos as [$itemtype, $items_id]) {
                $docitem->add([
                    'documents_id' => $documents_id,
                    'itemtype'     => $itemtype,
                    'items_id'     => $items_id,
                    '_no_message'  => true,
                ], [], false);
            }

            self::registrarEvento($acta_id, 'generada', sprintf('%d activo(s); completitud %s %%', count($activos), ActaBuilder::completitud($datos)));
            $DB->commit();
        } catch (Throwable $e) {
            $DB->rollBack();
            if ($tmpfile !== null && is_file($tmpfile)) {
                @unlink($tmpfile);
            }
            Session::addMessageAfterRedirect(__('No se generó el acta: ', 'actasentrega') . htmlspecialchars($e->getMessage()), false, ERROR);
            return false;
        }

        Session::addMessageAfterRedirect(sprintf(__('Acta %s generada.', 'actasentrega'), $datos['numero']));
        return $acta_id;
    }

    /**
     * Lee el activo directamente de GLPI. Devuelve null si no existe o el usuario no puede verlo.
     */
    public static function leerActivo(string $itemtype, int $id): ?array
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        if (!in_array($itemtype, $CFG_GLPI['assignable_types'] ?? [], true) || $id <= 0) {
            return null;
        }
        $item = getItemForItemtype($itemtype);
        if (!$item || !$item->getFromDB($id) || !$item->can($id, READ)) {
            return null;
        }

        $modelo = '';
        foreach ($item->fields as $campo => $valor) {
            if (str_ends_with($campo, 'models_id') && (int) $valor > 0) {
                $modelo = Dropdown::getDropdownName(getTableNameForForeignKeyField($campo), (int) $valor);
                break;
            }
        }
        $fabricante = (int) ($item->fields['manufacturers_id'] ?? 0) > 0
            ? Dropdown::getDropdownName('glpi_manufacturers', (int) $item->fields['manufacturers_id'])
            : '';

        return [
            'itemtype'          => $itemtype,
            'items_id'          => $id,
            'tipo_activo'       => $item::getTypeName(1),
            'nombre'            => (string) ($item->fields['name'] ?? ''),
            'serial'            => (string) ($item->fields['serial'] ?? ''),
            'otherserial'       => (string) ($item->fields['otherserial'] ?? ''),
            'fabricante'        => $fabricante,
            'modelo'            => $modelo,
            'users_id_asignado' => (int) ($item->fields['users_id'] ?? 0),
        ];
    }

    /** Activos asignados en GLPI a un usuario (todos los tipos asignables). */
    public static function activosAsignados(int $users_id): array
    {
        /** @var \DBmysql $DB */
        /** @var array $CFG_GLPI */
        global $DB, $CFG_GLPI;

        $activos = [];
        foreach ($CFG_GLPI['assignable_types'] ?? [] as $itemtype) {
            $item = getItemForItemtype($itemtype);
            if (!$item || !$item::canView() || !$DB->fieldExists($item::getTable(), 'users_id')) {
                continue;
            }
            $where = ['users_id' => $users_id];
            if ($item->maybeDeleted()) {
                $where['is_deleted'] = 0;
            }
            if ($item->maybeTemplate()) {
                $where['is_template'] = 0;
            }
            if ($item->isEntityAssign()) {
                $where += getEntitiesRestrictCriteria($item::getTable(), '', '', $item->maybeRecursive());
            }
            $orden = $DB->fieldExists($item::getTable(), 'name') ? 'name' : 'id';
            foreach ($DB->request(['SELECT' => ['id'], 'FROM' => $item::getTable(), 'WHERE' => $where, 'ORDER' => $orden]) as $row) {
                $fila = self::leerActivo($itemtype, (int) $row['id']);
                if ($fila !== null) {
                    $fila['clave'] = $itemtype . '#' . $row['id'];
                    $fila['completo'] = trim($fila['serial']) !== '' && trim($fila['otherserial']) !== '';
                    $activos[] = $fila;
                }
            }
        }
        return $activos;
    }

    // ------------------------------------------------------------------
    // Verificación
    // ------------------------------------------------------------------

    /**
     * Verifica la integridad del acta guardada:
     *  - la huella corresponde al contenido canónico guardado;
     *  - el contenido canónico corresponde a la cabecera y a los ítems guardados.
     *
     * @return array{valida: bool, motivos: string[]}
     */
    public function verificarIntegridad(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $motivos  = [];
        $canonico = (string) $this->fields['contenido_canonico'];
        if (!hash_equals((string) $this->fields['huella_sha256'], hash('sha256', $canonico))) {
            $motivos[] = __('La huella no corresponde al contenido guardado.', 'actasentrega');
        }

        $doc = json_decode($canonico, true);
        if (!is_array($doc)) {
            $motivos[] = __('El contenido guardado está dañado.', 'actasentrega');
            return ['valida' => false, 'motivos' => $motivos];
        }

        $items = iterator_to_array($DB->request([
            'FROM'  => ActaItem::getTable(),
            'WHERE' => ['plugin_actasentrega_actas_id' => $this->getID()],
        ]), false);

        $reconstruido = ActaBuilder::canonico([
            'numero'        => $this->fields['numero'],
            'tipo'          => $this->fields['tipo'],
            'fecha'         => $doc['fecha'] ?? '',
            'entidad'       => (int) $this->fields['entities_id'],
            'entrega'       => ['id' => (int) $this->fields['users_id_entrega'], 'nombre' => $doc['entrega']['nombre'] ?? ''],
            'recibe'        => ['id' => (int) $this->fields['users_id_recibe'], 'nombre' => $doc['recibe']['nombre'] ?? ''],
            'observaciones' => (string) $this->fields['observaciones'],
            'activos'       => $items,
        ]);
        if (!hash_equals($canonico, $reconstruido)) {
            $motivos[] = __('Los datos del acta o de sus activos fueron modificados en la base de datos.', 'actasentrega');
        }
        return ['valida' => $motivos === [], 'motivos' => $motivos];
    }

    public static function urlVerificacion(string $numero, string $huella): string
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;
        return rtrim((string) $CFG_GLPI['url_base'], '/') . '/plugins/actasentrega/front/verificar.php?'
            . http_build_query(['n' => $numero, 'h' => $huella]);
    }

    public static function registrarEvento(int $actas_id, string $accion, ?string $detalle = null): void
    {
        /** @var \DBmysql $DB */
        global $DB;
        $DB->insert('glpi_plugin_actasentrega_eventos', [
            'plugin_actasentrega_actas_id' => $actas_id,
            'users_id'      => (int) Session::getLoginUserID(),
            'accion'        => $accion,
            'detalle'       => $detalle !== null ? mb_substr($detalle, 0, 255) : null,
            'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public static function nombreUsuario(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $u = new User();
        return $u->getFromDB($id) ? $u->getFriendlyName() : '';
    }

    // ------------------------------------------------------------------
    // Interfaz
    // ------------------------------------------------------------------

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof User && self::canView()) {
            $nb = 0;
            if ($_SESSION['glpishow_count_on_tabs']) {
                $nb = countElementsInTable(self::getTable(), [
                    'OR' => ['users_id_recibe' => $item->getID(), 'users_id_entrega' => $item->getID()],
                ]);
            }
            return self::createTabEntry(__('Actas', 'actasentrega'), $nb, $item::class, self::getIcon());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof User) {
            self::mostrarParaUsuario($item);
        }
        return true;
    }

    public static function mostrarParaUsuario(User $user): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $actas = iterator_to_array($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['OR' => ['users_id_recibe' => $user->getID(), 'users_id_entrega' => $user->getID()]],
            'ORDER' => 'id DESC',
        ]), false);

        TemplateRenderer::getInstance()->display('@actasentrega/user_tab.html.twig', [
            'user'          => $user,
            'activos'       => self::activosAsignados($user->getID()),
            'actas'         => $actas,
            'tipos'         => self::getTipos(),
            'estados'       => self::getEstados(),
            'puede_generar' => self::canCreate(),
            'form_url'      => self::getFormURL(),
            'dropdown_anterior' => User::dropdown([
                'name'    => 'users_id_anterior',
                'right'   => 'all',
                'used'    => [$user->getID()],
                'display' => false,
                'width'   => '100%',
            ]),
        ]);
    }

    public function showForm($ID, array $options = [])
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (!$this->getFromDB($ID)) {
            return false;
        }
        $this->check($ID, READ);

        $items = iterator_to_array($DB->request([
            'FROM'  => ActaItem::getTable(),
            'WHERE' => ['plugin_actasentrega_actas_id' => $ID],
        ]), false);
        $eventos = iterator_to_array($DB->request([
            'FROM'  => 'glpi_plugin_actasentrega_eventos',
            'WHERE' => ['plugin_actasentrega_actas_id' => $ID],
            'ORDER' => 'id ASC',
        ]), false);
        foreach ($eventos as &$ev) {
            $ev['usuario'] = $ev['users_id'] > 0 ? self::nombreUsuario((int) $ev['users_id']) : __('Público (QR)', 'actasentrega');
        }
        unset($ev);

        TemplateRenderer::getInstance()->display('@actasentrega/acta_form.html.twig', [
            'acta'         => $this->fields,
            'items'        => $items,
            'eventos'      => $eventos,
            'tipos'        => self::getTipos(),
            'estados'      => self::getEstados(),
            'entrega'      => self::nombreUsuario((int) $this->fields['users_id_entrega']),
            'recibe'       => self::nombreUsuario((int) $this->fields['users_id_recibe']),
            'tecnico'      => self::nombreUsuario((int) $this->fields['users_id_tecnico']),
            'integridad'   => $this->verificarIntegridad(),
            'puede_editar' => self::canUpdate(),
            'transiciones' => self::TRANSICIONES[$this->fields['estado']] ?? [],
            'form_url'     => self::getFormURL(),
            'url_verificacion' => self::urlVerificacion($this->fields['numero'], $this->fields['huella_sha256']),
            'doc_url'      => $this->fields['documents_id'] > 0
                ? Document::getFormURLWithID((int) $this->fields['documents_id']) : '',
            'pdf_url'      => $this->fields['documents_id'] > 0
                ? $GLOBALS['CFG_GLPI']['root_doc'] . '/front/document.send.php?docid=' . (int) $this->fields['documents_id']
                : '',
        ]);
        return true;
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'            => 1,
            'table'         => self::getTable(),
            'field'         => 'numero',
            'name'          => __('Número', 'actasentrega'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 2,
            'table'         => self::getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'datatype'      => 'number',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 3,
            'table'         => self::getTable(),
            'field'         => 'tipo',
            'name'          => __('Tipo', 'actasentrega'),
            'datatype'      => 'specific',
            'searchtype'    => ['equals', 'notequals'],
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 4,
            'table'         => self::getTable(),
            'field'         => 'estado',
            'name'          => __('Estado', 'actasentrega'),
            'datatype'      => 'specific',
            'searchtype'    => ['equals', 'notequals'],
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 5,
            'table'         => self::getTable(),
            'field'         => 'fecha_generacion',
            'name'          => __('Fecha', 'actasentrega'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 6,
            'table'         => 'glpi_users',
            'field'         => 'name',
            'linkfield'     => 'users_id_recibe',
            'name'          => __('Recibe', 'actasentrega'),
            'datatype'      => 'dropdown',
            'right'         => 'all',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 7,
            'table'         => 'glpi_users',
            'field'         => 'name',
            'linkfield'     => 'users_id_entrega',
            'name'          => __('Entrega', 'actasentrega'),
            'datatype'      => 'dropdown',
            'right'         => 'all',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 8,
            'table'         => self::getTable(),
            'field'         => 'huella_sha256',
            'name'          => __('Huella SHA-256', 'actasentrega'),
            'datatype'      => 'string',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 80,
            'table'         => 'glpi_entities',
            'field'         => 'completename',
            'name'          => \Entity::getTypeName(1),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
        ];
        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        return match ($field) {
            'tipo'   => htmlspecialchars(self::getTipos()[$values[$field]] ?? $values[$field]),
            'estado' => htmlspecialchars(self::getEstados()[$values[$field]] ?? $values[$field]),
            default  => parent::getSpecificValueToDisplay($field, $values, $options),
        };
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        return match ($field) {
            'tipo'   => Dropdown::showFromArray($name, self::getTipos(), ['value' => $values[$field]] + $options),
            'estado' => Dropdown::showFromArray($name, self::getEstados(), ['value' => $values[$field]] + $options),
            default  => parent::getSpecificValueToSelect($field, $name, $values, $options),
        };
    }
}
