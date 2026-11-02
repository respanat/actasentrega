<?php

/**
 * Genera el PDF del acta con la biblioteca TCPDF que ya incluye GLPI (sin dependencias nuevas).
 * El PDF lleva texto real (no imagen), la huella SHA-256 y un código QR de verificación.
 *
 * @copyright 2026 Ramiro Alfonso España Tejada
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Actasentrega;

use Dropdown;
use TCPDF;

final class PdfRenderer
{
    private const TITULOS = [
        ActaBuilder::TIPO_ENTREGA    => 'ACTA DE ENTREGA DE ACTIVOS TECNOLÓGICOS',
        ActaBuilder::TIPO_DEVOLUCION => 'ACTA DE DEVOLUCIÓN DE ACTIVOS TECNOLÓGICOS',
        ActaBuilder::TIPO_TRASLADO   => 'ACTA DE TRASLADO DE ACTIVOS TECNOLÓGICOS',
    ];

    private const DECLARACIONES = [
        ActaBuilder::TIPO_ENTREGA    => 'Quien recibe declara que recibe los activos relacionados en el estado indicado y que asume su custodia y buen uso conforme a las políticas de la organización.',
        ActaBuilder::TIPO_DEVOLUCION => 'Quien entrega declara que devuelve los activos relacionados en el estado indicado; quien recibe verifica su estado y los reintegra al inventario.',
        ActaBuilder::TIPO_TRASLADO   => 'Los activos relacionados pasan de la custodia de quien entrega a la de quien recibe, en el estado indicado.',
    ];

    public static function render(array $d, string $huella, string $url_verificacion): string
    {
        $pdf = new class ('P', 'mm', 'LETTER', true, 'UTF-8') extends TCPDF {
            public string $pie = '';
            public function Header(): void
            {
            }
            public function Footer(): void
            {
                $this->SetY(-12);
                $this->SetFont('dejavusans', '', 7);
                $this->SetTextColor(110, 110, 110);
                $this->Cell(0, 5, $this->pie . '   ·   Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
            }
        };
        $pdf->pie = $d['numero'] . '   ·   SHA-256 ' . substr($huella, 0, 16) . '…';
        $pdf->SetCreator('GLPI · Actas de entrega');
        $pdf->SetAuthor((string) ($d['entrega']['nombre'] ?? ''));
        $pdf->SetTitle(self::TITULOS[$d['tipo']] . ' ' . $d['numero']);
        $pdf->SetSubject('SHA-256 ' . $huella);
        $pdf->SetMargins(18, 16, 18);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 10);

        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $entidad = Dropdown::getDropdownName('glpi_entities', (int) $d['entidad']);

        $th = 'style="background-color:#e8eef5;font-weight:bold;"';
        $sec = fn ($n, $t) => '<p style="font-size:9.5pt;color:#1F4E79;"><b>' . $n . '. ' . $e($t) . '</b></p>';

        // 1. Personas: nombre, cargo, dependencia y ubicación tal como estaban en GLPI al generar
        $p = fn ($rol, $c) => $e($d[$rol][$c] ?? '') ?: '—';
        $personas = <<<HTML
<table border="0.5" cellpadding="3" style="font-size:8.5pt;">
  <tr {$th}><td width="20%"></td><td width="40%">Entrega</td><td width="40%">Recibe</td></tr>
  <tr><td><b>Nombre</b></td><td>{$p('entrega', 'nombre')}</td><td>{$p('recibe', 'nombre')}</td></tr>
  <tr><td><b>Cargo</b></td><td>{$p('entrega', 'cargo')}</td><td>{$p('recibe', 'cargo')}</td></tr>
  <tr><td><b>Dependencia</b></td><td>{$p('entrega', 'dependencia')}</td><td>{$p('recibe', 'dependencia')}</td></tr>
  <tr><td><b>Ubicación</b></td><td>{$p('entrega', 'ubicacion')}</td><td>{$p('recibe', 'ubicacion')}</td></tr>
</table>
HTML;

        // 2. Activos: identificación y, debajo, sus especificaciones técnicas
        $filas = '';
        foreach (array_values($d['activos']) as $i => $a) {
            $modelo = trim(($a['fabricante'] ?? '') . ' ' . ($a['modelo'] ?? ''));
            $filas .= sprintf(
                '<tr nobr="true"><td width="5%%" align="center">%d</td><td width="13%%">%s</td><td width="17%%">%s</td><td width="20%%">%s</td>'
                . '<td width="16%%">%s</td><td width="16%%">%s</td><td width="13%%">%s</td></tr>',
                $i + 1,
                $e($a['tipo_activo'] ?? $a['itemtype']),
                $e($a['nombre']),
                $e($modelo),
                $e($a['serial']),
                $e($a['otherserial']),
                $e($a['estado_fisico'])
            );
            $det = (array) ActaBuilder::detalle($a['detalle'] ?? []);
            if ($det !== []) {
                $partes = [];
                foreach ($det as $clave => $valor) {
                    $partes[] = '<b>' . $e(ActaBuilder::DETALLE[$clave] ?? $clave) . ':</b> ' . $e($valor);
                }
                $filas .= '<tr nobr="true"><td width="5%"></td><td width="95%" style="font-size:7.5pt;color:#333333;">'
                    . implode('&nbsp;&nbsp;·&nbsp;&nbsp;', $partes) . '</td></tr>';
            }
        }

        $acc = trim((string) ($d['accesorios'] ?? '')) !== ''
            ? '<p style="font-size:8.5pt;"><b>Accesorios entregados:</b> ' . nl2br($e($d['accesorios'])) . '</p>' : '';
        $obs = trim((string) $d['observaciones']) !== ''
            ? '<p style="font-size:8.5pt;"><b>Observaciones:</b> ' . nl2br($e($d['observaciones'])) . '</p>' : '';

        // 3. Declaración y, en entregas y traslados, responsabilidades de quien recibe
        $resp = '';
        if ($d['tipo'] !== ActaBuilder::TIPO_DEVOLUCION) {
            $resp = '<p style="font-size:8pt;">Quien recibe se compromete a usar los activos solo para las funciones de su cargo, '
                . 'a conservarlos y restituirlos en buen estado salvo el deterioro natural (Código Sustantivo del Trabajo, art. 58, num. 3) '
                . 'y a informar de inmediato cualquier daño, pérdida o hurto. Un cobro por daño causado con dolo o culpa debe comprobarse '
                . 'escuchando al trabajador, y cualquier descuento de salario requiere su autorización escrita para ese caso o una orden '
                . 'judicial (arts. 59 y 149). Los datos personales se tratan conforme a la Ley 1581 de 2012.</p>';
        }

        $s1 = $sec(1, 'Personas');
        $s2 = $sec(2, 'Activos (datos de GLPI al momento del acta)');
        $s3 = $sec(3, 'Declaración');
        $html = <<<HTML
<table cellpadding="2"><tr>
  <td width="65%"><span style="font-size:9pt;color:#555555;">{$e($entidad)}</span><br/>
    <b style="font-size:12pt;">{$e(self::TITULOS[$d['tipo']])}</b></td>
  <td width="35%" align="right"><b style="font-size:12pt;">{$e($d['numero'])}</b><br/>
    <span style="font-size:9pt;">Fecha: {$e($d['fecha'])}</span></td>
</tr></table>
<hr/>
{$s1}
{$personas}
{$s2}
<table border="0.5" cellpadding="3" style="font-size:8.5pt;">
  <thead><tr {$th}>
    <th width="5%" align="center">#</th><th width="13%">Tipo</th><th width="17%">Nombre</th>
    <th width="20%">Fabricante / modelo</th><th width="16%">N.º de serie</th>
    <th width="16%">N.º inventario</th><th width="13%">Estado</th>
  </tr></thead>
  <tbody>{$filas}</tbody>
</table>
{$acc}
{$obs}
{$s3}
<p style="font-size:8.5pt;">{$e(self::DECLARACIONES[$d['tipo']])}</p>
{$resp}
<p></p><p></p>
<table cellpadding="1" style="font-size:8.5pt;" nobr="true"><tr>
  <td width="45%" align="center">______________________________<br/><b>Entrega</b><br/>{$p('entrega', 'nombre')}<br/><span style="color:#555555;">{$e($d['entrega']['cargo'] ?? '')}</span></td>
  <td width="10%"></td>
  <td width="45%" align="center">______________________________<br/><b>Recibe</b><br/>{$p('recibe', 'nombre')}<br/><span style="color:#555555;">{$e($d['recibe']['cargo'] ?? '')}</span></td>
</tr></table>
HTML;
        $pdf->writeHTML($html, true, false, true, false, '');

        // Bloque de integridad con QR
        $pdf->Ln(6);
        if ($pdf->GetY() > $pdf->getPageHeight() - 60) {
            $pdf->AddPage();
        }
        $y = $pdf->GetY();
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->RoundedRect(18, $y, $pdf->getPageWidth() - 36, 34, 2);
        $pdf->write2DBarcode($url_verificacion, 'QRCODE,M', 21, $y + 2, 30, 30, ['border' => false, 'padding' => 0], 'N');
        $pdf->SetXY(55, $y + 3);
        $pdf->SetFont('dejavusans', 'B', 8.5);
        $pdf->Cell(0, 4, 'Verificación de integridad', 0, 1);
        $pdf->SetX(55);
        $pdf->SetFont('dejavusansmono', '', 7.5);
        $pdf->MultiCell($pdf->getPageWidth() - 76, 4, 'SHA-256: ' . $huella, 0, 'L');
        $pdf->SetX(55);
        $pdf->SetFont('dejavusans', '', 7.5);
        $pdf->MultiCell(
            $pdf->getPageWidth() - 76,
            4,
            'Escanee el código o consulte el número del acta en GLPI para comprobar que este documento no fue alterado. '
            . 'La huella prueba la integridad del contenido; no reemplaza una firma digital certificada (Ley 527 de 1999).',
            0,
            'L'
        );

        return $pdf->Output('', 'S');
    }
}
