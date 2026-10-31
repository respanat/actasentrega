<?php

/**
 * Verificación pública de un acta (destino del código QR del PDF).
 * No requiere sesión. Por minimización de datos (Ley 1581 de 2012) no muestra nombres
 * de personas ni datos de los activos: solo si el acta existe, su estado y si está íntegra.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

use GlpiPlugin\Actasentrega\Acta;

$numero = substr(preg_replace('/[^A-Z0-9-]/', '', strtoupper((string) ($_GET['n'] ?? ''))), 0, 20);
$huella = strtolower(substr(preg_replace('/[^a-fA-F0-9]/', '', (string) ($_GET['h'] ?? '')), 0, 64));

$resultado = ['clase' => 'gris', 'titulo' => 'Ingrese el número del acta', 'detalle' => [], 'datos' => null];

if ($numero !== '') {
    $acta = new Acta();
    if (!$acta->getFromDBByCrit(['numero' => $numero])) {
        $resultado = ['clase' => 'rojo', 'titulo' => 'No existe un acta con ese número', 'detalle' => [], 'datos' => null];
    } else {
        $integridad = $acta->verificarIntegridad();
        $coincide   = $huella !== '' && hash_equals((string) $acta->fields['huella_sha256'], $huella);
        $detalle    = $integridad['motivos'];
        if ($huella !== '' && !$coincide) {
            $detalle[] = 'La huella impresa en el documento no coincide con la registrada en GLPI: el documento fue alterado o no corresponde a esta acta.';
        }
        $valida = $integridad['valida'] && ($huella === '' || $coincide);
        $anulada = $acta->fields['estado'] === Acta::ESTADO_ANULADA;
        $resultado = [
            'clase'   => !$valida ? 'rojo' : ($anulada ? 'ambar' : 'verde'),
            'titulo'  => !$valida ? 'Acta NO válida'
                : ($anulada ? 'Acta íntegra, pero ANULADA' : ($huella === '' ? 'Acta registrada e íntegra' : 'Acta auténtica e íntegra')),
            'detalle' => $detalle,
            'datos'   => [
                'Número'  => $acta->fields['numero'],
                'Tipo'    => Acta::getTipos()[$acta->fields['tipo']] ?? $acta->fields['tipo'],
                'Fecha'   => $acta->fields['fecha_generacion'],
                'Estado'  => Acta::getEstados()[$acta->fields['estado']] ?? $acta->fields['estado'],
                'Activos' => countElementsInTable('glpi_plugin_actasentrega_actas_items', ['plugin_actasentrega_actas_id' => $acta->getID()]),
            ],
        ];
        Acta::registrarEvento($acta->getID(), 'verificacion', $valida ? 'resultado: válida' : 'resultado: NO válida');
    }
}

$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$colores = ['verde' => '#2fb344', 'rojo' => '#d63939', 'ambar' => '#f59f00', 'gris' => '#667382'];
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificación de acta</title>
  <style>
    body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f5f7fb; margin: 0; padding: 24px; color: #1d273b; }
    .caja { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); overflow: hidden; }
    .banda { padding: 18px 22px; color: #fff; font-size: 1.25rem; font-weight: 600; background: <?= $colores[$resultado['clase']] ?>; }
    .cuerpo { padding: 18px 22px; }
    dl { display: grid; grid-template-columns: 110px 1fr; gap: 6px 12px; margin: 0 0 12px; }
    dt { color: #667382; }
    ul { padding-left: 18px; color: #d63939; }
    form { display: flex; gap: 8px; margin-top: 12px; }
    input { flex: 1; padding: 8px; border: 1px solid #ccd3dd; border-radius: 6px; }
    button { padding: 8px 14px; border: 0; border-radius: 6px; background: #206bc4; color: #fff; }
    small { color: #667382; display: block; margin-top: 14px; }
  </style>
</head>
<body>
  <div class="caja">
    <div class="banda"><?= $e($resultado['titulo']) ?></div>
    <div class="cuerpo">
      <?php if ($resultado['datos']): ?>
        <dl>
          <?php foreach ($resultado['datos'] as $k => $v): ?>
            <dt><?= $e($k) ?></dt><dd style="margin:0"><?= $e($v) ?></dd>
          <?php endforeach; ?>
        </dl>
      <?php endif; ?>
      <?php if ($resultado['detalle']): ?>
        <ul><?php foreach ($resultado['detalle'] as $d): ?><li><?= $e($d) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <form method="get">
        <input name="n" placeholder="Número del acta (p. ej. AE-2026-000001)" value="<?= $e($numero) ?>">
        <button type="submit">Verificar</button>
      </form>
      <small>La verificación compara la huella SHA-256 del acta con la registrada en GLPI. Prueba que el contenido no fue alterado; no reemplaza una firma digital certificada.</small>
    </div>
  </div>
</body>
</html>
