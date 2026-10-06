<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/gastos.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  CRUD de GASTOS de la persona conectada.
 *    GET  gastos.php?accion=categorias        -> lista de categorías
 *    GET  gastos.php?accion=listar&anio=&mes=&dia=&q=  -> historial
 *    GET  gastos.php?accion=ver&id=           -> detalle + documentos
 *    POST gastos.php?accion=crear             -> registrar (multipart)
 *    POST gastos.php?accion=editar&id=        -> modificar (multipart)
 *    POST gastos.php?accion=eliminar&id=      -> eliminar + archivos
 *
 *  Flujo de un registro:
 *    Usuario -> Frontend (formulario) -> esta API -> MySQL (gastos)
 *                                                 -> uploads/AAAA/MM/DD
 *                                                 -> MySQL (documentos)
 * =====================================================================
 */

declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';

$usuario = requerir_login();

switch (accion_actual()) {

    // -----------------------------------------------------------
    // [SECCIÓN 1] CATEGORÍAS (para el selector del formulario)
    // -----------------------------------------------------------
    case 'categorias':
        exigir_metodo('GET');
        $cats = filas('SELECT id, nombre, color FROM categorias ORDER BY id');
        foreach ($cats as &$c) {
            $c['id'] = (int)$c['id'];
        }
        responder(['categorias' => $cats]);

    // -----------------------------------------------------------
    // [SECCIÓN 2] HISTORIAL
    // Siempre filtrado por el usuario conectado (nadie ve gastos
    // ajenos desde aquí; el Admin usa admin.php).
    // -----------------------------------------------------------
    case 'listar':
        exigir_metodo('GET');
        $filtros = ['usuario_id' => $usuario['id']] + array_intersect_key($_GET, array_flip(['anio', 'mes', 'dia', 'q']));
        [$where, $p] = filtros_gastos($filtros);
        $limite = min(max(param_int('limite', 200), 1), 500);
        $gastos = array_map('normalizar_gasto',
            filas(MF_SQL_GASTO . " $where ORDER BY g.fecha DESC, g.id DESC LIMIT $limite", $p));
        responder(['gastos' => $gastos]);

    // -----------------------------------------------------------
    // [SECCIÓN 3] DETALLE (con la lista de documentos para verlos)
    // -----------------------------------------------------------
    case 'ver':
        exigir_metodo('GET');
        $g = obtener_gasto((int)param_int('id', 0), $usuario, false);
        $g['documentos_lista'] = documentos_de_gasto($g['id']);
        $g['es_mio'] = $g['usuario_id'] === $usuario['id'];
        responder(['gasto' => $g]);

    // -----------------------------------------------------------
    // [SECCIÓN 4] CREAR
    // Todo ocurre en UNA transacción:
    //   correlativo -> INSERT gasto -> mover archivos -> INSERT documentos
    // Si algo falla se deshace todo (rollback) y se borran los archivos
    // ya movidos: nunca queda un archivo sin registro ni al revés.
    // -----------------------------------------------------------
    case 'crear':
        exigir_metodo('POST');
        [$datos, $errores] = validar_gasto(leer_entrada());
        if ($errores) {
            responder_error('Revisa los campos marcados', 422, $errores);
        }
        $archivos = preparar_documentos();

        $pdo = db();
        $creados = [];
        try {
            $pdo->beginTransaction();
            $datos['correlativo'] = siguiente_correlativo('gastos');
            consulta(
                'INSERT INTO gastos (usuario_id, correlativo, tipo_documento, numero_documento, proveedor, monto, descripcion, fecha, categoria_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$usuario['id'], $datos['correlativo'], $datos['tipo_documento'], $datos['numero_documento'],
                 $datos['proveedor'], $datos['monto'], $datos['descripcion'], $datos['fecha'], $datos['categoria_id']]
            );
            $datos['id'] = (int)$pdo->lastInsertId();
            $creados = guardar_documentos($datos, $archivos);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            foreach ($creados as $ruta) {
                @unlink($ruta);
            }
            throw $ex;
        }

        $g = obtener_gasto($datos['id'], $usuario, true);
        responder([
            'mensaje' => sprintf('Gasto N° %04d registrado', $g['correlativo']),
            'gasto'   => $g,
        ], 201);

    // -----------------------------------------------------------
    // [SECCIÓN 5] EDITAR
    // Actualiza los datos, agrega documentos nuevos (si vienen) y
    // reubica los existentes si cambió la fecha o el proveedor.
    // -----------------------------------------------------------
    case 'editar':
        exigir_metodo('POST');
        $actual = obtener_gasto((int)param_int('id', 0), $usuario, true);
        [$datos, $errores] = validar_gasto(leer_entrada());
        if ($errores) {
            responder_error('Revisa los campos marcados', 422, $errores);
        }
        $archivos = preparar_documentos($actual['documentos']);

        $pdo = db();
        $creados = [];
        try {
            $pdo->beginTransaction();
            consulta(
                'UPDATE gastos SET tipo_documento = ?, numero_documento = ?, proveedor = ?, monto = ?,
                        descripcion = ?, fecha = ?, categoria_id = ?
                 WHERE id = ? AND usuario_id = ?',
                [$datos['tipo_documento'], $datos['numero_documento'], $datos['proveedor'], $datos['monto'],
                 $datos['descripcion'], $datos['fecha'], $datos['categoria_id'], $actual['id'], $usuario['id']]
            );
            $datos['id'] = $actual['id'];
            $datos['correlativo'] = $actual['correlativo'];
            if ($datos['fecha'] !== $actual['fecha'] || slug($datos['proveedor']) !== slug($actual['proveedor'])) {
                reubicar_documentos($datos);
            }
            $creados = guardar_documentos($datos, $archivos);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            foreach ($creados as $ruta) {
                @unlink($ruta);
            }
            throw $ex;
        }

        $g = obtener_gasto($actual['id'], $usuario, true);
        responder(['mensaje' => 'Gasto actualizado', 'gasto' => $g]);

    // -----------------------------------------------------------
    // [SECCIÓN 6] ELIMINAR
    // Primero se leen las rutas de sus documentos, luego se borra el
    // gasto (la BD borra los documentos en cascada) y por último los
    // archivos físicos de /uploads.
    // -----------------------------------------------------------
    case 'eliminar':
        exigir_metodo('POST');
        $g = obtener_gasto((int)param_int('id', 0), $usuario, true);
        $rutas = array_column(documentos_de_gasto($g['id']), 'ruta_relativa');
        consulta('DELETE FROM gastos WHERE id = ? AND usuario_id = ?', [$g['id'], $usuario['id']]);
        foreach ($rutas as $r) {
            borrar_archivo($r);
        }
        responder(['mensaje' => sprintf('Gasto N° %04d eliminado', $g['correlativo'])]);

    default:
        responder_error('Acción no válida', 404);
}
