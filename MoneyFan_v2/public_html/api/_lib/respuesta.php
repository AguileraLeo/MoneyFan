<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/_lib/respuesta.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Funciones para RESPONDER siempre con el mismo formato JSON:
 *     Éxito:  { "ok": true,  ...datos }
 *     Error:  { "ok": false, "error": "mensaje", "campos": {campo: msg} }
 *  "campos" permite al frontend pintar el error junto a cada input.
 *  También lee la entrada (JSON o formulario) y controla el método HTTP.
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// [SECCIÓN 1] RESPUESTAS JSON
// ---------------------------------------------------------------
function responder(array $datos = [], int $estado = 200): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function responder_error(string $mensaje, int $estado = 400, array $campos = []): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    $cuerpo = ['ok' => false, 'error' => $mensaje];
    if ($campos) {
        $cuerpo['campos'] = $campos;
    }
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------
// [SECCIÓN 2] LECTURA DE LA ENTRADA
// El frontend envía JSON (application/json) o multipart/form-data
// cuando adjunta archivos. Esta función unifica ambos casos.
// ---------------------------------------------------------------
function leer_entrada(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($tipo, 'application/json') !== false) {
        $datos = json_decode((string)file_get_contents('php://input'), true);
        return is_array($datos) ? $datos : [];
    }
    return $_POST;
}

// ---------------------------------------------------------------
// [SECCIÓN 3] CONTROL DE MÉTODO Y ACCIÓN
// Cada endpoint recibe ?accion=... y valida que el método HTTP sea
// el correcto (GET para leer, POST para crear/modificar/eliminar).
// ---------------------------------------------------------------
function exigir_metodo(string $metodo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $metodo) {
        responder_error('Método no permitido', 405);
    }
}

function accion_actual(): string
{
    return (string)($_GET['accion'] ?? '');
}

function param_int(string $nombre, ?int $porDefecto = null): ?int
{
    $valor = $_GET[$nombre] ?? null;
    if ($valor === null || $valor === '') {
        return $porDefecto;
    }
    return filter_var($valor, FILTER_VALIDATE_INT) !== false ? (int)$valor : $porDefecto;
}
