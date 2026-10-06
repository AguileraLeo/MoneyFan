<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/documentos.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Entrega y elimina DOCUMENTOS (fotos/PDF de boletas).
 *    GET  documentos.php?accion=ver&id=            -> muestra el archivo
 *    GET  documentos.php?accion=ver&id=&descargar=1 -> lo descarga
 *    POST documentos.php?accion=eliminar&id=       -> lo elimina
 *
 *  ¿Por qué no se enlaza directo a /uploads/...? Porque la carpeta
 *  uploads está bloqueada por .htaccess: así SOLO el dueño del gasto
 *  (o el Admin) puede ver sus boletas, previa revisión de la sesión.
 * =====================================================================
 */

declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';

$usuario = requerir_login();

// ---------------------------------------------------------------
// [SECCIÓN 1] BUSCAR EL DOCUMENTO Y SU GASTO (con permisos)
// ---------------------------------------------------------------
function obtener_documento(int $id, array $usuario, bool $soloDueno): array
{
    $d = fila(
        'SELECT d.*, g.usuario_id FROM documentos d JOIN gastos g ON g.id = d.gasto_id WHERE d.id = ?',
        [$id]
    );
    $permitido = $d && ((int)$d['usuario_id'] === $usuario['id'] || (!$soloDueno && $usuario['rol'] === 'admin'));
    if (!$permitido) {
        responder_error('Documento no encontrado', 404);
    }
    return $d;
}

switch (accion_actual()) {

    // -----------------------------------------------------------
    // [SECCIÓN 2] VER / DESCARGAR
    // Envía el archivo con su tipo real. "private, max-age" permite
    // que el navegador lo guarde un rato sin que lo compartan proxies.
    // -----------------------------------------------------------
    case 'ver':
        exigir_metodo('GET');
        $d = obtener_documento((int)param_int('id', 0), $usuario, false);
        $abs = ruta_absoluta($d['ruta_relativa']);
        if (!is_file($abs)) {
            responder_error('El archivo ya no existe en el servidor', 410);
        }
        $modo = !empty($_GET['descargar']) ? 'attachment' : 'inline';
        header_remove('Cache-Control');
        header('Content-Type: ' . $d['mime']);
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: ' . $modo . '; filename="' . $d['nombre_archivo'] . '"');
        header('Cache-Control: private, max-age=3600');
        if (str_starts_with($d['mime'], 'image/')) {
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        }
        readfile($abs);
        exit;

    // -----------------------------------------------------------
    // [SECCIÓN 3] ELIMINAR (solo el dueño)
    // -----------------------------------------------------------
    case 'eliminar':
        exigir_metodo('POST');
        $d = obtener_documento((int)param_int('id', 0), $usuario, true);
        consulta('DELETE FROM documentos WHERE id = ?', [$d['id']]);
        borrar_archivo($d['ruta_relativa']);
        responder(['mensaje' => 'Documento eliminado']);

    default:
        responder_error('Acción no válida', 404);
}
