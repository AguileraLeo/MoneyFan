<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/exportar.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  EXPORTA los gastos (solo Administrador) a:
 *    GET exportar.php?formato=csv   -> CSV listo para Excel en español
 *    GET exportar.php?formato=xlsx  -> Excel .xlsx real
 *  Acepta los mismos filtros del panel: usuario_id, desde, hasta, q.
 * =====================================================================
 */

declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';
require __DIR__ . '/_lib/excel.php';

requerir_admin();
exigir_metodo('GET');

// ---------------------------------------------------------------
// [SECCIÓN 1] DATOS A EXPORTAR (sin límite de filas)
// ---------------------------------------------------------------
[$where, $p] = filtros_gastos(array_intersect_key($_GET, array_flip(['usuario_id', 'desde', 'hasta', 'q'])));
$gastos = array_map('normalizar_gasto', filas(MF_SQL_GASTO . " $where ORDER BY g.fecha, g.correlativo", $p));

$encabezados = ['Correlativo', 'Fecha', 'RUT', 'Usuario', 'Tipo', 'N° documento',
                'Proveedor', 'Categoría', 'Descripción', 'Monto', 'Documentos adjuntos'];
$filas = [$encabezados];
$total = 0;
foreach ($gastos as $g) {
    $total += $g['monto'];
    $filas[] = [
        sprintf('%04d', $g['correlativo']),
        date('d-m-Y', strtotime($g['fecha'])),
        $g['usuario_rut'],
        $g['usuario_nombre'],
        ucfirst($g['tipo_documento']),
        (string)($g['numero_documento'] ?? ''),
        $g['proveedor'],
        $g['categoria'],
        $g['descripcion'],
        $g['monto'],
        $g['documentos'],
    ];
}
$filas[] = ['', '', '', '', '', '', '', '', 'TOTAL', $total, ''];

$nombre = 'moneyfan_gastos_' . date('Y-m-d_His');
header_remove('Cache-Control');
header('Cache-Control: private, no-store');

// ---------------------------------------------------------------
// [SECCIÓN 2] CSV
// - BOM UTF-8 al inicio: Excel muestra bien tildes y ñ.
// - Separador ";": Excel en español (Chile) lo abre en columnas.
// - Protección contra "inyección de fórmulas": un texto que empieza
//   con = + - @ se antepone con ' para que Excel no lo ejecute.
// ---------------------------------------------------------------
if (($_GET['formato'] ?? 'csv') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$nombre.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($filas as $f) {
        $f = array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $f);
        fputcsv($out, $f, ';', '"', '\\');
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------
// [SECCIÓN 3] EXCEL .xlsx (columna "Monto" con formato $)
// ---------------------------------------------------------------
$contenido = xlsx_generar($filas, 'Gastos MoneyFan', [9]);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$nombre.xlsx\"");
header('Content-Length: ' . strlen($contenido));
echo $contenido;
