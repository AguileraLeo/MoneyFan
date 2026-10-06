<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/_lib/excel.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Genera un archivo Excel REAL (.xlsx) sin librerías externas.
 *  Un .xlsx es un ZIP con varios XML adentro. Muchos hostings cPanel
 *  no traen la extensión "zip" de PHP activada, por eso aquí el ZIP
 *  se arma "a mano" (formato sin compresión, válido para Excel,
 *  LibreOffice y Google Sheets).
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// [SECCIÓN 1] HOJA DE CÁLCULO (XML)
// $filas: lista de arrays; la primera fila son los encabezados.
// Los números se guardan como número (Excel puede sumarlos) y los
// textos como "inlineStr". El estilo 1 = encabezado en negrita y
// el estilo 2 = formato de moneda $ #.##0.
// ---------------------------------------------------------------
function xlsx_hoja(array $filas, array $columnasMoneda = []): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
         . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
         . '<cols>';
    $anchos = [];
    foreach ($filas as $f) {
        foreach (array_values($f) as $i => $v) {
            $anchos[$i] = max($anchos[$i] ?? 8, min(50, mb_strlen((string)$v) + 2));
        }
    }
    foreach ($anchos as $i => $w) {
        $xml .= sprintf('<col min="%d" max="%d" width="%d" customWidth="1"/>', $i + 1, $i + 1, $w);
    }
    $xml .= '</cols><sheetData>';

    foreach ($filas as $r => $f) {
        $xml .= '<row r="' . ($r + 1) . '">';
        foreach (array_values($f) as $c => $v) {
            $ref = xlsx_columna($c) . ($r + 1);
            if ($r === 0) {
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . xlsx_esc((string)$v) . '</t></is></c>';
            } elseif (is_int($v) || is_float($v)) {
                $estilo = in_array($c, $columnasMoneda, true) ? ' s="2"' : '';
                $xml .= '<c r="' . $ref . '"' . $estilo . '><v>' . $v . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . xlsx_esc((string)$v) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }
    return $xml . '</sheetData></worksheet>';
}

/** 0 -> A, 25 -> Z, 26 -> AA ... */
function xlsx_columna(int $i): string
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
        $s = chr(65 + ($i - 1) % 26) . $s;
    }
    return $s;
}

function xlsx_esc(string $s): string
{
    // Quita caracteres de control que Excel no acepta
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------
// [SECCIÓN 2] ARCHIVO .xlsx COMPLETO
// Los XML mínimos que exige el formato Office Open XML.
// ---------------------------------------------------------------
function xlsx_generar(array $filas, string $nombreHoja = 'Gastos', array $columnasMoneda = []): string
{
    $archivos = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . xlsx_esc($nombreHoja) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;$&quot;#,##0"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2F4858"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf numFmtId="164" applyNumberFormat="1"/></cellXfs>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => xlsx_hoja($filas, $columnasMoneda),
    ];
    return zip_sin_compresion($archivos);
}

// ---------------------------------------------------------------
// [SECCIÓN 3] ZIP "STORED" (sin compresión) escrito a mano
// Estructura: [cabecera local + datos] x N, directorio central,
// y registro de fin. Cada entrada lleva su CRC32 y tamaño.
// ---------------------------------------------------------------
function zip_sin_compresion(array $archivos): string
{
    $datos = '';
    $central = '';
    $n = 0;
    foreach ($archivos as $nombre => $contenido) {
        $crc = crc32($contenido);
        $len = strlen($contenido);
        $offset = strlen($datos);
        // version, flags(bit 11 = UTF-8), método 0 (stored), hora, fecha
        $comun = pack('vvvvvVVVvv', 20, 0x0800, 0, 0, 0x21, $crc, $len, $len, strlen($nombre), 0);
        $datos .= "PK\x03\x04" . $comun . $nombre . $contenido;
        $central .= "PK\x01\x02" . pack('v', 20) . $comun . pack('vvvVV', 0, 0, 0, 0, $offset) . $nombre;
        $n++;
    }
    return $datos . $central
         . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $n, $n, strlen($central), strlen($datos), 0);
}
