<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/_lib/auth.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Autenticación y autorización:
 *    - Saber quién está conectado (usuario_actual).
 *    - Exigir sesión iniciada o rol Admin en cada endpoint.
 *    - Token CSRF para toda petición que modifica datos.
 *    - Bloqueo temporal tras varios intentos fallidos de login.
 *    - Cierre de sesión por inactividad.
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// [SECCIÓN 1] USUARIO ACTUAL
// La sesión guarda solo el id. En cada petición se relee el usuario
// desde MySQL: si el Admin lo desactivó, pierde el acceso al instante.
// ---------------------------------------------------------------
function usuario_actual(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;

    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    // Cierre por inactividad
    global $CONFIG;
    $limite = 60 * (int)($CONFIG['seguridad']['sesion_inactividad_min'] ?? 120);
    if (isset($_SESSION['ultimo_uso']) && time() - (int)$_SESSION['ultimo_uso'] > $limite) {
        cerrar_sesion();
        return null;
    }
    $_SESSION['ultimo_uso'] = time();

    $u = fila('SELECT id, rut, nombre, email, rol, activo FROM usuarios WHERE id = ?', [$_SESSION['usuario_id']]);
    if (!$u || !(int)$u['activo']) {
        cerrar_sesion();
        return null;
    }
    $u['id'] = (int)$u['id'];
    $u['activo'] = (bool)$u['activo'];
    $cache = $u;
    return $cache;
}

// ---------------------------------------------------------------
// [SECCIÓN 2] GUARDIAS DE ACCESO
// Se llaman al inicio de cada endpoint protegido.
// ---------------------------------------------------------------
function requerir_login(): array
{
    $u = usuario_actual();
    if ($u === null) {
        responder_error('Tu sesión expiró. Vuelve a ingresar.', 401);
    }
    // Toda petición que MODIFICA datos debe traer el token CSRF
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        verificar_csrf();
    }
    return $u;
}

function requerir_admin(): array
{
    $u = requerir_login();
    if ($u['rol'] !== 'admin') {
        responder_error('Esta sección es solo para el Administrador.', 403);
    }
    return $u;
}

// ---------------------------------------------------------------
// [SECCIÓN 3] TOKEN CSRF
// Se genera al iniciar sesión y el frontend lo envía en la cabecera
// X-CSRF-Token. Un sitio malicioso no puede leerlo, así que no puede
// forjar peticiones en nombre del usuario.
// ---------------------------------------------------------------
function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verificar_csrf(): void
{
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        responder_error('La sesión no es válida. Recarga la página.', 419);
    }
}

// ---------------------------------------------------------------
// [SECCIÓN 4] INICIO Y CIERRE DE SESIÓN
// session_regenerate_id evita la "fijación de sesión".
// ---------------------------------------------------------------
function iniciar_sesion(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'usuario_id' => (int)$usuario['id'],
        'ultimo_uso' => time(),
        'csrf'       => bin2hex(random_bytes(32)),
    ];
    consulta('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$usuario['id']]);
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

// ---------------------------------------------------------------
// [SECCIÓN 5] PROTECCIÓN CONTRA FUERZA BRUTA
// Si un RUT o una IP acumulan N fallos en la ventana de tiempo,
// se bloquea el ingreso hasta que la ventana pase.
// ---------------------------------------------------------------
function ip_cliente(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_bloqueado(string $rut): bool
{
    global $CONFIG;
    $max = (int)($CONFIG['seguridad']['max_intentos_login'] ?? 5);
    $min = (int)($CONFIG['seguridad']['ventana_bloqueo_min'] ?? 15);

    $f = fila(
        "SELECT
           SUM(rut = :rut) AS por_rut,
           SUM(ip  = :ip)  AS por_ip
         FROM intentos_login
         WHERE exito = 0 AND creado_en > (NOW() - INTERVAL $min MINUTE)",
        ['rut' => $rut, 'ip' => ip_cliente()]
    );
    return (int)($f['por_rut'] ?? 0) >= $max || (int)($f['por_ip'] ?? 0) >= $max * 3;
}

function registrar_intento(string $rut, bool $exito): void
{
    consulta('INSERT INTO intentos_login (rut, ip, exito) VALUES (?, ?, ?)', [$rut, ip_cliente(), $exito ? 1 : 0]);
    if ($exito) {
        // Un ingreso correcto "perdona" los fallos anteriores de ese RUT
        consulta('DELETE FROM intentos_login WHERE rut = ? AND exito = 0', [$rut]);
    }
}
