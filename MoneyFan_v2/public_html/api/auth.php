<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/auth.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Endpoint de AUTENTICACIÓN.
 *    POST auth.php?accion=login          -> ingresar con RUT + clave
 *    GET  auth.php?accion=sesion         -> ¿quién está conectado?
 *    POST auth.php?accion=logout         -> cerrar sesión
 *    POST auth.php?accion=cambiar_clave  -> cambiar MI contraseña
 * =====================================================================
 */

declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';

switch (accion_actual()) {

    // -----------------------------------------------------------
    // [SECCIÓN 1] LOGIN
    // 1) valida formato  2) revisa bloqueo por intentos fallidos
    // 3) busca el RUT en MySQL  4) compara la clave con password_verify
    // El mensaje de error es el MISMO si el RUT no existe o si la clave
    // es incorrecta (no se revela qué RUT están registrados).
    // -----------------------------------------------------------
    case 'login':
        exigir_metodo('POST');
        $e   = leer_entrada();
        $rut = formatear_rut((string)($e['rut'] ?? ''));
        $clave = (string)($e['clave'] ?? '');

        $campos = array_filter([
            'rut'   => validar_rut($rut),
            'clave' => $clave === '' ? 'Ingresa la contraseña' : null,
        ]);
        if ($campos) {
            responder_error('Revisa los campos marcados', 422, $campos);
        }

        if (login_bloqueado($rut)) {
            responder_error('Demasiados intentos fallidos. Espera unos minutos e intenta de nuevo.', 429);
        }

        $u = fila('SELECT id, rut, nombre, email, rol, activo, clave_hash FROM usuarios WHERE rut = ?', [$rut]);
        if (!$u || !password_verify($clave, $u['clave_hash'])) {
            registrar_intento($rut, false);
            responder_error('RUT o contraseña incorrectos', 401);
        }
        if (!(int)$u['activo']) {
            registrar_intento($rut, false);
            responder_error('Tu cuenta está desactivada. Contacta al Administrador.', 403);
        }

        // Si PHP mejora el algoritmo de hash, se actualiza solo
        if (password_needs_rehash($u['clave_hash'], PASSWORD_DEFAULT)) {
            consulta('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
        }

        registrar_intento($rut, true);
        iniciar_sesion($u);
        responder([
            'usuario' => ['id' => (int)$u['id'], 'rut' => $u['rut'], 'nombre' => $u['nombre'],
                          'email' => $u['email'], 'rol' => $u['rol']],
            'csrf'    => token_csrf(),
        ]);

    // -----------------------------------------------------------
    // [SECCIÓN 2] SESIÓN ACTUAL
    // La PWA la consulta al abrirse para saber si mostrar el login.
    // -----------------------------------------------------------
    case 'sesion':
        exigir_metodo('GET');
        $u = usuario_actual();
        responder([
            'usuario' => $u,
            'csrf'    => $u ? token_csrf() : null,
        ]);

    // -----------------------------------------------------------
    // [SECCIÓN 3] LOGOUT
    // -----------------------------------------------------------
    case 'logout':
        exigir_metodo('POST');
        requerir_login();
        cerrar_sesion();
        responder(['mensaje' => 'Sesión cerrada']);

    // -----------------------------------------------------------
    // [SECCIÓN 4] CAMBIAR MI CONTRASEÑA
    // Exige la clave actual para evitar que alguien con el teléfono
    // desbloqueado la cambie sin conocerla.
    // -----------------------------------------------------------
    case 'cambiar_clave':
        exigir_metodo('POST');
        $u = requerir_login();
        $e = leer_entrada();
        $actual = (string)($e['clave_actual'] ?? '');
        $nueva  = (string)($e['clave_nueva'] ?? '');
        $repite = (string)($e['clave_repite'] ?? '');

        $campos = array_filter([
            'clave_actual' => $actual === '' ? 'Ingresa tu contraseña actual' : null,
            'clave_nueva'  => validar_clave($nueva),
            'clave_repite' => $nueva !== $repite ? 'Las contraseñas no coinciden' : null,
        ]);
        if ($campos) {
            responder_error('Revisa los campos marcados', 422, $campos);
        }

        $hash = fila('SELECT clave_hash FROM usuarios WHERE id = ?', [$u['id']])['clave_hash'];
        if (!password_verify($actual, $hash)) {
            responder_error('Revisa los campos marcados', 422, ['clave_actual' => 'La contraseña actual no es correcta']);
        }
        consulta('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
        responder(['mensaje' => 'Contraseña actualizada']);

    default:
        responder_error('Acción no válida', 404);
}
