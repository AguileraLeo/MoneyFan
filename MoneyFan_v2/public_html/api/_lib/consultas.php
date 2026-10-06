<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/_lib/consultas.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Consultas de GASTOS que se repiten en varios endpoints (usuaria,
 *  resumen, admin y exportación), para escribirlas una sola vez.
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// [SECCIÓN 1] SELECT BASE DE UN GASTO
// Une el gasto con su categoría y su dueño, y cuenta sus documentos.
// ---------------------------------------------------------------
const MF_SQL_GASTO = "
    SELECT g.id, g.usuario_id, g.correlativo, g.tipo_documento, g.numero_documento,
           g.proveedor, g.monto, g.descripcion, g.fecha, g.categoria_id,
           g.creado_en, g.actualizado_en,
           c.nombre AS categoria, c.color AS categoria_color,
           u.nombre AS usuario_nombre, u.rut AS usuario_rut,
           (SELECT COUNT(*) FROM documentos d WHERE d.gasto_id = g.id) AS documentos
    FROM gastos g
    JOIN categorias c ON c.id = g.categoria_id
    JOIN usuarios   u ON u.id = g.usuario_id
";

/** Convierte los números que MySQL devuelve como texto en enteros. */
function normalizar_gasto(array $g): array
{
    foreach (['id', 'usuario_id', 'correlativo', 'monto', 'categoria_id', 'documentos'] as $k) {
        if (isset($g[$k])) {
            $g[$k] = (int)$g[$k];
        }
    }
    return $g;
}

// ---------------------------------------------------------------
// [SECCIÓN 2] FILTROS COMUNES
// Arma el WHERE según los parámetros GET recibidos:
//   usuario_id, anio, mes, dia, desde, hasta, q (texto libre)
// ---------------------------------------------------------------
function filtros_gastos(array $f): array
{
    $where = [];
    $p = [];
    if (!empty($f['usuario_id'])) { $where[] = 'g.usuario_id = ?';  $p[] = (int)$f['usuario_id']; }
    if (!empty($f['anio']))       { $where[] = 'YEAR(g.fecha) = ?';  $p[] = (int)$f['anio']; }
    if (!empty($f['mes']))        { $where[] = 'MONTH(g.fecha) = ?'; $p[] = (int)$f['mes']; }
    if (!empty($f['dia']))        { $where[] = 'DAY(g.fecha) = ?';   $p[] = (int)$f['dia']; }
    if (!empty($f['desde']) && !validar_fecha_simple($f['desde'])) { $where[] = 'g.fecha >= ?'; $p[] = $f['desde']; }
    if (!empty($f['hasta']) && !validar_fecha_simple($f['hasta'])) { $where[] = 'g.fecha <= ?'; $p[] = $f['hasta']; }
    if (!empty($f['q'])) {
        $where[] = '(g.proveedor LIKE ? OR g.descripcion LIKE ? OR g.numero_documento LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], (string)$f['q']) . '%';
        array_push($p, $like, $like, $like);
    }
    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $p];
}

/** Devuelve null si la fecha AAAA-MM-DD es válida (sin regla de "no futura"). */
function validar_fecha_simple(string $fecha): ?string
{
    $d = DateTime::createFromFormat('!Y-m-d', $fecha);
    return ($d && $d->format('Y-m-d') === $fecha) ? null : 'Fecha no válida';
}

// ---------------------------------------------------------------
// [SECCIÓN 3] UN GASTO CON PERMISOS
// - $soloDueno = true  -> solo el dueño (para editar/eliminar).
// - $soloDueno = false -> el dueño o el Admin (para ver).
// Responde 404 (no 403) para no revelar gastos ajenos.
// ---------------------------------------------------------------
function obtener_gasto(int $id, array $usuario, bool $soloDueno): array
{
    $g = fila(MF_SQL_GASTO . ' WHERE g.id = ?', [$id]);
    $permitido = $g && ((int)$g['usuario_id'] === $usuario['id'] || (!$soloDueno && $usuario['rol'] === 'admin'));
    if (!$permitido) {
        responder_error('Gasto no encontrado', 404);
    }
    return normalizar_gasto($g);
}

function documentos_de_gasto(int $gastoId): array
{
    $docs = filas(
        'SELECT id, nombre_archivo, ruta_relativa, nombre_original, mime, tamano_bytes, creado_en
         FROM documentos WHERE gasto_id = ? ORDER BY id',
        [$gastoId]
    );
    foreach ($docs as &$d) {
        $d['id'] = (int)$d['id'];
        $d['tamano_bytes'] = (int)$d['tamano_bytes'];
        $d['es_imagen'] = str_starts_with($d['mime'], 'image/');
    }
    return $docs;
}
