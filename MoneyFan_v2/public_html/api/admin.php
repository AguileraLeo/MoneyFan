<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/admin.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Funciones EXCLUSIVAS del perfil Administrador (rol = 'admin').
 *    GET  admin.php?accion=panel             -> totales generales
 *    GET  admin.php?accion=usuarios          -> ver todos los usuarios
 *    POST admin.php?accion=crear_usuario     -> crear usuario
 *    POST admin.php?accion=estado_usuario    -> activar / desactivar
 *    POST admin.php?accion=cambiar_clave     -> cambiar clave de un usuario
 *    GET  admin.php?accion=gastos&usuario_id=&desde=&hasta=&q=
 *                                            -> ver todos los gastos
 *  La exportación CSV/Excel está en exportar.php.
 * =====================================================================
 */

declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';

$admin = requerir_admin();

switch (accion_actual()) {

    // -----------------------------------------------------------
    // [SECCIÓN 1] PANEL: totales, cantidad de documentos y usuarios
    // -----------------------------------------------------------
    case 'panel':
        exigir_metodo('GET');
        $inicioMes = date('Y-m-01');
        $t = fila(
            'SELECT
               (SELECT COUNT(*) FROM usuarios)                         AS usuarios,
               (SELECT COUNT(*) FROM usuarios WHERE activo = 1)        AS usuarios_activos,
               (SELECT COUNT(*) FROM gastos)                           AS gastos,
               (SELECT COALESCE(SUM(monto),0) FROM gastos)             AS total,
               (SELECT COALESCE(SUM(monto),0) FROM gastos WHERE fecha >= ?) AS total_mes,
               (SELECT COUNT(*) FROM gastos WHERE fecha >= ?)          AS gastos_mes,
               (SELECT COUNT(*) FROM documentos)                       AS documentos,
               (SELECT COALESCE(SUM(tamano_bytes),0) FROM documentos)  AS bytes_documentos',
            [$inicioMes, $inicioMes]
        );
        $porUsuario = filas(
            'SELECT u.id, u.nombre, u.rut, COUNT(g.id) AS gastos, COALESCE(SUM(g.monto),0) AS total
             FROM usuarios u LEFT JOIN gastos g ON g.usuario_id = u.id
             GROUP BY u.id, u.nombre, u.rut ORDER BY total DESC'
        );
        $porCategoria = filas(
            'SELECT c.nombre AS categoria, c.color, COALESCE(SUM(g.monto),0) AS total
             FROM categorias c LEFT JOIN gastos g ON g.categoria_id = c.id
             GROUP BY c.id, c.nombre, c.color HAVING total > 0 ORDER BY total DESC'
        );
        responder([
            'totales'       => array_map('intval', $t),
            'por_usuario'   => array_map(fn($u) => ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'rut' => $u['rut'],
                                                    'gastos' => (int)$u['gastos'], 'total' => (int)$u['total']], $porUsuario),
            'por_categoria' => array_map(fn($c) => ['categoria' => $c['categoria'], 'color' => $c['color'],
                                                    'total' => (int)$c['total']], $porCategoria),
        ]);

    // -----------------------------------------------------------
    // [SECCIÓN 2] VER TODOS LOS USUARIOS (con sus estadísticas)
    // Nunca se envía el hash de la contraseña al navegador.
    // -----------------------------------------------------------
    case 'usuarios':
        exigir_metodo('GET');
        $us = filas(
            'SELECT u.id, u.rut, u.nombre, u.email, u.rol, u.activo, u.ultimo_acceso, u.creado_en,
                    COUNT(g.id) AS gastos, COALESCE(SUM(g.monto),0) AS total,
                    (SELECT COUNT(*) FROM documentos d JOIN gastos g2 ON g2.id = d.gasto_id WHERE g2.usuario_id = u.id) AS documentos
             FROM usuarios u LEFT JOIN gastos g ON g.usuario_id = u.id
             GROUP BY u.id ORDER BY u.rol, u.nombre'
        );
        foreach ($us as &$u) {
            foreach (['id', 'gastos', 'total', 'documentos'] as $k) {
                $u[$k] = (int)$u[$k];
            }
            $u['activo'] = (bool)$u['activo'];
            $u['soy_yo'] = $u['id'] === $admin['id'];
        }
        responder(['usuarios' => $us]);

    // -----------------------------------------------------------
    // [SECCIÓN 3] CREAR USUARIO
    // La clave se guarda con password_hash (bcrypt). El RUT se
    // normaliza al formato 12.345.678-9 y debe ser único.
    // -----------------------------------------------------------
    case 'crear_usuario':
        exigir_metodo('POST');
        $e = leer_entrada();
        $rut    = formatear_rut((string)($e['rut'] ?? ''));
        $nombre = trim((string)($e['nombre'] ?? ''));
        $email  = trim((string)($e['email'] ?? ''));
        $clave  = (string)($e['clave'] ?? '');
        $rol    = (string)($e['rol'] ?? 'usuario');

        $campos = array_filter([
            'rut'    => validar_rut($rut),
            'nombre' => validar_nombre($nombre),
            'email'  => validar_email_opcional($email),
            'clave'  => validar_clave($clave),
            'rol'    => in_array($rol, ['admin', 'usuario'], true) ? null : 'Rol no válido',
        ]);
        if (!isset($campos['rut']) && fila('SELECT id FROM usuarios WHERE rut = ?', [$rut])) {
            $campos['rut'] = 'Ya existe un usuario con ese RUT';
        }
        if ($campos) {
            responder_error('Revisa los campos marcados', 422, $campos);
        }

        consulta(
            'INSERT INTO usuarios (rut, nombre, email, clave_hash, rol, activo) VALUES (?, ?, ?, ?, ?, 1)',
            [$rut, $nombre, $email !== '' ? $email : null, password_hash($clave, PASSWORD_DEFAULT), $rol]
        );
        responder(['mensaje' => "Usuario $nombre creado", 'id' => (int)db()->lastInsertId()], 201);

    // -----------------------------------------------------------
    // [SECCIÓN 4] ACTIVAR / DESACTIVAR
    // Desactivar NO borra datos: solo impide el ingreso. El Admin no
    // puede desactivarse a sí mismo (quedaría sin acceso).
    // -----------------------------------------------------------
    case 'estado_usuario':
        exigir_metodo('POST');
        $e = leer_entrada();
        $id = (int)($e['id'] ?? 0);
        $activo = !empty($e['activo']);
        if ($id === $admin['id']) {
            responder_error('No puedes desactivar tu propia cuenta', 422);
        }
        $u = fila('SELECT id, nombre FROM usuarios WHERE id = ?', [$id]);
        if (!$u) {
            responder_error('Usuario no encontrado', 404);
        }
        consulta('UPDATE usuarios SET activo = ? WHERE id = ?', [$activo ? 1 : 0, $id]);
        responder(['mensaje' => $u['nombre'] . ($activo ? ' activado' : ' desactivado')]);

    // -----------------------------------------------------------
    // [SECCIÓN 5] CAMBIAR CONTRASEÑA DE UN USUARIO
    // (Ej: la persona la olvidó). Se valida igual que una clave nueva.
    // -----------------------------------------------------------
    case 'cambiar_clave':
        exigir_metodo('POST');
        $e = leer_entrada();
        $id = (int)($e['id'] ?? 0);
        $clave = (string)($e['clave'] ?? '');
        if ($m = validar_clave($clave)) {
            responder_error('Revisa los campos marcados', 422, ['clave' => $m]);
        }
        $u = fila('SELECT id, nombre FROM usuarios WHERE id = ?', [$id]);
        if (!$u) {
            responder_error('Usuario no encontrado', 404);
        }
        consulta('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $id]);
        // Libera un posible bloqueo por intentos fallidos
        consulta('DELETE FROM intentos_login WHERE rut = (SELECT rut FROM usuarios WHERE id = ?) AND exito = 0', [$id]);
        responder(['mensaje' => 'Contraseña de ' . $u['nombre'] . ' actualizada']);

    // -----------------------------------------------------------
    // [SECCIÓN 6] VER TODOS LOS GASTOS (de todos los usuarios)
    // -----------------------------------------------------------
    case 'gastos':
        exigir_metodo('GET');
        [$where, $p] = filtros_gastos(array_intersect_key($_GET, array_flip(['usuario_id', 'desde', 'hasta', 'q'])));
        $gastos = array_map('normalizar_gasto',
            filas(MF_SQL_GASTO . " $where ORDER BY g.fecha DESC, g.id DESC LIMIT 500", $p));
        $suma = fila("SELECT COALESCE(SUM(g.monto),0) AS total, COUNT(*) AS cantidad FROM gastos g $where", $p);
        responder(['gastos' => $gastos, 'total' => (int)$suma['total'], 'cantidad' => (int)$suma['cantidad']]);

    default:
        responder_error('Acción no válida', 404);
}
