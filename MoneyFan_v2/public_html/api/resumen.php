<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/resumen.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Totales y agrupaciones calculados por MySQL (SUM / COUNT / GROUP BY)
 *  para la persona conectada.
 *    GET resumen.php?accion=inicio              -> hoy / semana / mes
 *    GET resumen.php?accion=carpetas            -> árbol Año > Mes > Día
 *    GET resumen.php?accion=mes&anio=2026&mes=10 -> resumen mensual
 * =====================================================================
 */

declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';

$usuario = requerir_login();
$uid = $usuario['id'];

/** Total y cantidad de gastos entre dos fechas (incluidas). */
function totales_rango(int $uid, string $desde, string $hasta): array
{
    $f = fila(
        'SELECT COALESCE(SUM(monto),0) AS total, COUNT(*) AS cantidad
         FROM gastos WHERE usuario_id = ? AND fecha BETWEEN ? AND ?',
        [$uid, $desde, $hasta]
    );
    return ['total' => (int)$f['total'], 'cantidad' => (int)$f['cantidad'], 'desde' => $desde, 'hasta' => $hasta];
}

/** Total por categoría entre dos fechas (para los gráficos de dona). */
function por_categoria(int $uid, string $desde, string $hasta): array
{
    $r = filas(
        'SELECT c.nombre AS categoria, c.color, SUM(g.monto) AS total, COUNT(*) AS cantidad
         FROM gastos g JOIN categorias c ON c.id = g.categoria_id
         WHERE g.usuario_id = ? AND g.fecha BETWEEN ? AND ?
         GROUP BY c.id, c.nombre, c.color ORDER BY total DESC',
        [$uid, $desde, $hasta]
    );
    return array_map(fn($x) => ['categoria' => $x['categoria'], 'color' => $x['color'],
                                'total' => (int)$x['total'], 'cantidad' => (int)$x['cantidad']], $r);
}

switch (accion_actual()) {

    // -----------------------------------------------------------
    // [SECCIÓN 1] PANTALLA DE INICIO
    // Mismo recuento de la v1 (diario, semanal, mensual), ahora
    // calculado sobre los datos reales guardados en MySQL.
    // La semana va de lunes a hoy.
    // -----------------------------------------------------------
    case 'inicio':
        exigir_metodo('GET');
        $hoy    = new DateTimeImmutable('today');
        $lunes  = $hoy->modify('-' . ((int)$hoy->format('N') - 1) . ' days');
        $inicioMes = $hoy->modify('first day of this month');
        $h = $hoy->format('Y-m-d');

        // Serie de los últimos 7 días (barras del inicio), con días en 0
        $hace6 = $hoy->modify('-6 days');
        $porDia = [];
        foreach (filas(
            'SELECT fecha, SUM(monto) AS total FROM gastos
             WHERE usuario_id = ? AND fecha BETWEEN ? AND ? GROUP BY fecha',
            [$uid, $hace6->format('Y-m-d'), $h]) as $r) {
            $porDia[$r['fecha']] = (int)$r['total'];
        }
        $serie = [];
        for ($d = $hace6; $d <= $hoy; $d = $d->modify('+1 day')) {
            $k = $d->format('Y-m-d');
            $serie[] = ['fecha' => $k, 'total' => $porDia[$k] ?? 0];
        }

        $ultimos = array_map('normalizar_gasto',
            filas(MF_SQL_GASTO . ' WHERE g.usuario_id = ? ORDER BY g.fecha DESC, g.id DESC LIMIT 5', [$uid]));

        responder([
            'hoy'           => totales_rango($uid, $h, $h),
            'semana'        => totales_rango($uid, $lunes->format('Y-m-d'), $h),
            'mes'           => totales_rango($uid, $inicioMes->format('Y-m-d'), $h),
            'serie_7_dias'  => $serie,
            'categorias_mes'=> por_categoria($uid, $inicioMes->format('Y-m-d'), $h),
            'ultimos'       => $ultimos,
        ]);

    // -----------------------------------------------------------
    // [SECCIÓN 2] CARPETAS AÑO > MES > DÍA
    // Una sola consulta agrupada; PHP arma el árbol anidado.
    // Es la misma organización que tienen los archivos en /uploads.
    // -----------------------------------------------------------
    case 'carpetas':
        exigir_metodo('GET');
        $r = filas(
            'SELECT YEAR(g.fecha) AS a, MONTH(g.fecha) AS m, DAY(g.fecha) AS d,
                    COUNT(*) AS cantidad, SUM(g.monto) AS total,
                    SUM((SELECT COUNT(*) FROM documentos x WHERE x.gasto_id = g.id)) AS documentos
             FROM gastos g WHERE g.usuario_id = ?
             GROUP BY a, m, d ORDER BY a DESC, m DESC, d DESC',
            [$uid]
        );
        $arbol = [];
        foreach ($r as $x) {
            [$a, $m, $d] = [(int)$x['a'], (int)$x['m'], (int)$x['d']];
            $arbol[$a] ??= ['anio' => $a, 'total' => 0, 'cantidad' => 0, 'documentos' => 0, 'meses' => []];
            $arbol[$a]['meses'][$m] ??= ['mes' => $m, 'total' => 0, 'cantidad' => 0, 'documentos' => 0, 'dias' => []];
            foreach (['total', 'cantidad', 'documentos'] as $k) {
                $arbol[$a][$k] += (int)$x[$k];
                $arbol[$a]['meses'][$m][$k] += (int)$x[$k];
            }
            $arbol[$a]['meses'][$m]['dias'][] = ['dia' => $d, 'total' => (int)$x['total'],
                                                 'cantidad' => (int)$x['cantidad'], 'documentos' => (int)$x['documentos']];
        }
        // Quitar las claves numéricas para que JSON entregue listas
        $lista = array_values(array_map(function ($anio) {
            $anio['meses'] = array_values($anio['meses']);
            return $anio;
        }, $arbol));
        responder(['carpetas' => $lista]);

    // -----------------------------------------------------------
    // [SECCIÓN 3] RESUMEN MENSUAL
    // Total, cantidad, documentos, promedio diario, gasto mayor,
    // distribución por categoría y por tipo, gasto por día y
    // comparación con el mes anterior.
    // -----------------------------------------------------------
    case 'mes':
        exigir_metodo('GET');
        $anio = param_int('anio', (int)date('Y'));
        $mes  = param_int('mes', (int)date('n'));
        if ($anio < 2000 || $anio > 2100 || $mes < 1 || $mes > 12) {
            responder_error('Mes no válido', 422);
        }
        $ini = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
        $fin = $ini->modify('last day of this month');
        [$d1, $d2] = [$ini->format('Y-m-d'), $fin->format('Y-m-d')];

        $base = totales_rango($uid, $d1, $d2);
        $docs = (int)fila(
            'SELECT COUNT(*) AS n FROM documentos d JOIN gastos g ON g.id = d.gasto_id
             WHERE g.usuario_id = ? AND g.fecha BETWEEN ? AND ?', [$uid, $d1, $d2])['n'];

        $mayor = fila(MF_SQL_GASTO . ' WHERE g.usuario_id = ? AND g.fecha BETWEEN ? AND ? ORDER BY g.monto DESC LIMIT 1',
                      [$uid, $d1, $d2]);

        $porTipo = array_map(fn($x) => ['tipo' => $x['tipo_documento'], 'total' => (int)$x['total'], 'cantidad' => (int)$x['cantidad']],
            filas('SELECT tipo_documento, SUM(monto) AS total, COUNT(*) AS cantidad FROM gastos
                   WHERE usuario_id = ? AND fecha BETWEEN ? AND ? GROUP BY tipo_documento', [$uid, $d1, $d2]));

        $porDia = array_map(fn($x) => ['dia' => (int)$x['dia'], 'total' => (int)$x['total']],
            filas('SELECT DAY(fecha) AS dia, SUM(monto) AS total FROM gastos
                   WHERE usuario_id = ? AND fecha BETWEEN ? AND ? GROUP BY dia ORDER BY dia', [$uid, $d1, $d2]));

        // Días "transcurridos": el mes completo, o hasta hoy si es el mes actual
        $hoy = new DateTimeImmutable('today');
        $diasTranscurridos = $fin < $hoy ? (int)$fin->format('j')
                           : ($ini > $hoy ? 0 : (int)$hoy->format('j'));

        $antIni = $ini->modify('-1 month');
        $anterior = totales_rango($uid, $antIni->format('Y-m-d'), $antIni->modify('last day of this month')->format('Y-m-d'));

        responder([
            'anio'             => $anio,
            'mes'              => $mes,
            'total'            => $base['total'],
            'cantidad'         => $base['cantidad'],
            'documentos'       => $docs,
            'promedio_diario'  => $diasTranscurridos ? (int)round($base['total'] / $diasTranscurridos) : 0,
            'mayor_gasto'      => $mayor ? normalizar_gasto($mayor) : null,
            'por_categoria'    => por_categoria($uid, $d1, $d2),
            'por_tipo'         => $porTipo,
            'por_dia'          => $porDia,
            'dias_mes'         => (int)$fin->format('j'),
            'total_mes_anterior' => $anterior['total'],
        ]);

    default:
        responder_error('Acción no válida', 404);
}
