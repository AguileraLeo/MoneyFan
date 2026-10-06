<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/_lib/bootstrap.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Es lo PRIMERO que carga cada endpoint de la API. Prepara todo:
 *    1. Busca y carga la configuración (fuera de public_html).
 *    2. Ajusta zona horaria y manejo de errores.
 *    3. Envía cabeceras de seguridad y de respuesta JSON.
 *    4. Inicia la sesión PHP con cookies seguras.
 *    5. Carga el resto de las librerías (_lib/*.php).
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// [SECCIÓN 1] CARGA DE CONFIGURACIÓN
// Orden de búsqueda:
//   a) Variable de entorno MONEYFAN_CONFIG (ruta absoluta).
//   b) Carpeta "moneyfan_config/config.php" en alguna carpeta padre
//      (sube hasta 6 niveles desde api/_lib). Así las credenciales
//      quedan FUERA de public_html y nunca se publican.
// ---------------------------------------------------------------
function mf_buscar_config(): ?string
{
    $env = getenv('MONEYFAN_CONFIG');
    if ($env && is_file($env)) {
        return $env;
    }
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $dir = dirname($dir);
        $candidato = $dir . DIRECTORY_SEPARATOR . 'moneyfan_config' . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($candidato)) {
            return $candidato;
        }
    }
    return null;
}

$rutaConfig = mf_buscar_config();
if ($rutaConfig === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => false,
        'error' => 'Falta la configuración: copia moneyfan_config/config.example.php como config.php (ver README).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @var array $CONFIG  Configuración global disponible en toda la API */
$CONFIG = require $rutaConfig;

// ---------------------------------------------------------------
// [SECCIÓN 2] ZONA HORARIA Y ERRORES
// En producción (modo_debug=false) los errores técnicos se registran
// en el log del servidor pero NUNCA se muestran al usuario.
// ---------------------------------------------------------------
date_default_timezone_set($CONFIG['app']['zona_horaria'] ?? 'America/Santiago');
define('MF_DEBUG', (bool)($CONFIG['app']['modo_debug'] ?? false));
ini_set('display_errors', '0');
error_reporting(E_ALL);

// ---------------------------------------------------------------
// [SECCIÓN 3] CABECERAS DE SEGURIDAD
// - nosniff: el navegador no "adivina" tipos de archivo.
// - DENY: la API no puede incrustarse en iframes de otros sitios.
// - no-store: las respuestas con datos personales no se guardan en caché.
// ---------------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, max-age=0');

// ---------------------------------------------------------------
// [SECCIÓN 4] SESIÓN SEGURA
// - HttpOnly: JavaScript no puede leer la cookie (mitiga XSS).
// - SameSite=Strict: la cookie no viaja en peticiones de otros sitios
//   (mitiga CSRF, junto con el token X-CSRF-Token).
// - Secure: solo por HTTPS cuando el sitio usa HTTPS (cPanel + SSL).
// ---------------------------------------------------------------
$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name('MONEYFANSESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $esHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
session_start();

// ---------------------------------------------------------------
// [SECCIÓN 5] LIBRERÍAS DE LA API
// ---------------------------------------------------------------
require_once __DIR__ . '/respuesta.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validaciones.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/archivos.php';
require_once __DIR__ . '/consultas.php';

// ---------------------------------------------------------------
// [SECCIÓN 6] RED DE SEGURIDAD
// Cualquier excepción no controlada se convierte en un JSON de error
// genérico (el detalle técnico solo se ve en modo_debug).
// ---------------------------------------------------------------
set_exception_handler(function (Throwable $e) {
    error_log('[MoneyFan] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    $mensaje = MF_DEBUG ? $e->getMessage() : 'Ocurrió un error inesperado. Intenta nuevamente.';
    responder_error($mensaje, 500);
});
