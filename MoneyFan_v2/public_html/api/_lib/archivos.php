<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/_lib/archivos.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Todo lo relacionado con los DOCUMENTOS (fotos/PDF de boletas):
 *    - Validar cada archivo subido (tipo real y tamaño).
 *    - Guardarlo ordenado en carpetas  uploads/AAAA/MM/DD/
 *    - Nombrarlo con el correlativo:   0001_2026-10-04_proveedor.jpg
 *    - Registrar la referencia en la tabla "documentos".
 *    - Moverlo/renombrarlo si cambian la fecha o el proveedor del gasto.
 *    - Borrarlo del disco cuando se elimina.
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// [SECCIÓN 1] REGLAS DE ARCHIVOS
// El tipo se detecta leyendo el CONTENIDO (finfo), no la extensión
// que manda el usuario, y la extensión final la decide el servidor.
// ---------------------------------------------------------------
const MF_DOC_MAX_BYTES     = 5 * 1024 * 1024;   // 5 MB por archivo
const MF_DOC_MAX_POR_GASTO = 5;
const MF_MIME_PERMITIDOS   = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
];

// ---------------------------------------------------------------
// [SECCIÓN 2] RUTAS
// ---------------------------------------------------------------
/** Carpeta raíz de los documentos (por defecto public_html/uploads). */
function ruta_uploads(): string
{
    global $CONFIG;
    $ruta = $CONFIG['app']['ruta_uploads'] ?? null;
    if (!$ruta) {
        $ruta = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads';
    }
    return rtrim($ruta, '/\\');
}

/** Convierte una ruta relativa de la BD (2026/10/04/x.jpg) en absoluta. */
function ruta_absoluta(string $relativa): string
{
    return ruta_uploads() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativa);
}

// ---------------------------------------------------------------
// [SECCIÓN 3] NOMBRES DE ARCHIVO
// ---------------------------------------------------------------
/** "Café Aroma Ñuñoa" -> "cafe-aroma-nunoa" (seguro para el disco). */
function slug(string $texto): string
{
    $mapa = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
             'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
    $texto = strtolower(strtr($texto, $mapa));
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = trim((string)$texto, '-');
    return substr($texto !== '' ? $texto : 'proveedor', 0, 40);
}

/**
 * Calcula carpeta y nombre de un documento:
 *   ['2026/10/04', '0001_2026-10-04_cafe-aroma.jpg']
 * El 2°, 3°... documento del mismo gasto lleva sufijo _2, _3...
 * Si ese nombre ya existe (por un borrado previo) busca el siguiente.
 */
function nombre_documento(array $gasto, string $ext, int $indice = 1, ?int $ignorarDocId = null): array
{
    [$a, $m, $d] = explode('-', $gasto['fecha']);
    $carpeta = "$a/$m/$d";
    $base = sprintf('%04d_%s_%s', $gasto['correlativo'], $gasto['fecha'], slug($gasto['proveedor']));

    for ($i = $indice; $i < 1000; $i++) {
        $nombre = $base . ($i > 1 ? "_$i" : '') . ".$ext";
        $relativa = "$carpeta/$nombre";
        $ocupado = fila(
            'SELECT id FROM documentos WHERE ruta_relativa = ? AND id <> ?',
            [$relativa, $ignorarDocId ?? 0]
        );
        if (!$ocupado && !is_file(ruta_absoluta($relativa))) {
            return [$carpeta, $nombre];
        }
    }
    throw new RuntimeException('No se pudo generar un nombre de archivo libre');
}

// ---------------------------------------------------------------
// [SECCIÓN 4] LECTURA Y VALIDACIÓN DE $_FILES
// PHP entrega los archivos múltiples "al revés" (name[0], name[1]…);
// aquí se transforman en una lista simple y se valida cada uno.
// ---------------------------------------------------------------
function archivos_subidos(string $campo = 'documentos'): array
{
    if (empty($_FILES[$campo])) {
        return [];
    }
    $f = $_FILES[$campo];
    $lista = [];
    if (is_array($f['name'])) {
        foreach ($f['name'] as $i => $nombre) {
            if ((int)$f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $lista[] = ['name' => $nombre, 'tmp_name' => $f['tmp_name'][$i],
                        'size' => (int)$f['size'][$i], 'error' => (int)$f['error'][$i]];
        }
    } elseif ((int)$f['error'] !== UPLOAD_ERR_NO_FILE) {
        $lista[] = ['name' => $f['name'], 'tmp_name' => $f['tmp_name'],
                    'size' => (int)$f['size'], 'error' => (int)$f['error']];
    }
    return $lista;
}

/** Devuelve [mime, extension] o un mensaje de error (string). */
function validar_archivo(array $archivo): array|string
{
    $nombre = mb_substr((string)$archivo['name'], 0, 60);
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE
        || $archivo['size'] > MF_DOC_MAX_BYTES) {
        return "\"$nombre\" supera el máximo de 5 MB";
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        return "No se pudo recibir \"$nombre\". Intenta de nuevo";
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']) ?: '';
    if (!isset(MF_MIME_PERMITIDOS[$mime])) {
        return "\"$nombre\" no es una imagen (JPG/PNG/WEBP) ni PDF";
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($archivo['tmp_name']) === false) {
        return "\"$nombre\" no es una imagen válida";
    }
    return [$mime, MF_MIME_PERMITIDOS[$mime]];
}

/**
 * Valida TODOS los archivos antes de guardar nada (o se guardan
 * todos, o ninguno). Devuelve la lista lista para guardar_documentos().
 */
function preparar_documentos(int $yaExistentes = 0): array
{
    $archivos = archivos_subidos();
    if ($yaExistentes + count($archivos) > MF_DOC_MAX_POR_GASTO) {
        responder_error('Máximo ' . MF_DOC_MAX_POR_GASTO . ' documentos por gasto', 422,
            ['documentos' => 'Máximo ' . MF_DOC_MAX_POR_GASTO . ' documentos por gasto']);
    }
    $listos = [];
    foreach ($archivos as $a) {
        $r = validar_archivo($a);
        if (is_string($r)) {
            responder_error($r, 422, ['documentos' => $r]);
        }
        $listos[] = $a + ['mime' => $r[0], 'ext' => $r[1]];
    }
    return $listos;
}

// ---------------------------------------------------------------
// [SECCIÓN 5] GUARDAR DOCUMENTOS (disco + referencia en MySQL)
// Se llama dentro de la transacción del gasto. Devuelve las rutas
// absolutas creadas para poder borrarlas si la transacción falla.
// ---------------------------------------------------------------
function guardar_documentos(array $gasto, array $archivos): array
{
    $creados = [];
    foreach ($archivos as $a) {
        [$carpeta, $nombre] = nombre_documento($gasto, $a['ext']);
        $dirAbs = ruta_absoluta($carpeta);
        if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            throw new RuntimeException("No se pudo crear la carpeta $carpeta");
        }
        $destino = $dirAbs . DIRECTORY_SEPARATOR . $nombre;
        if (!move_uploaded_file($a['tmp_name'], $destino)) {
            throw new RuntimeException("No se pudo guardar $nombre");
        }
        @chmod($destino, 0644);
        $creados[] = $destino;

        consulta(
            'INSERT INTO documentos (gasto_id, nombre_archivo, ruta_relativa, nombre_original, mime, tamano_bytes)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$gasto['id'], $nombre, "$carpeta/$nombre", mb_substr((string)$a['name'], 0, 160), $a['mime'], $a['size']]
        );
    }
    return $creados;
}

// ---------------------------------------------------------------
// [SECCIÓN 6] REUBICAR DOCUMENTOS
// Si se edita la fecha o el proveedor, los archivos se mueven a la
// carpeta del nuevo día y se renombran, para que la estructura
// uploads/AAAA/MM/DD siga siendo fiel a los datos.
// ---------------------------------------------------------------
function reubicar_documentos(array $gasto): void
{
    $docs = filas('SELECT id, ruta_relativa FROM documentos WHERE gasto_id = ? ORDER BY id', [$gasto['id']]);
    $indice = 1;
    foreach ($docs as $doc) {
        $ext = pathinfo($doc['ruta_relativa'], PATHINFO_EXTENSION);
        [$carpeta, $nombre] = nombre_documento($gasto, $ext, $indice, (int)$doc['id']);
        $nueva = "$carpeta/$nombre";
        $indice++;
        if ($nueva === $doc['ruta_relativa']) {
            continue;
        }
        $dirAbs = ruta_absoluta($carpeta);
        if (!is_dir($dirAbs)) {
            mkdir($dirAbs, 0755, true);
        }
        $origen = ruta_absoluta($doc['ruta_relativa']);
        if (is_file($origen) && rename($origen, ruta_absoluta($nueva))) {
            consulta('UPDATE documentos SET nombre_archivo = ?, ruta_relativa = ? WHERE id = ?', [$nombre, $nueva, $doc['id']]);
            limpiar_carpetas_vacias(dirname($origen));
        }
    }
}

// ---------------------------------------------------------------
// [SECCIÓN 7] BORRADO
// ---------------------------------------------------------------
function borrar_archivo(string $relativa): void
{
    $abs = ruta_absoluta($relativa);
    if (is_file($abs)) {
        @unlink($abs);
        limpiar_carpetas_vacias(dirname($abs));
    }
}

/** Elimina las carpetas día/mes/año que quedaron vacías. */
function limpiar_carpetas_vacias(string $dir): void
{
    $raiz = realpath(ruta_uploads());
    for ($i = 0; $i < 3; $i++) {
        $real = realpath($dir);
        if (!$real || $real === $raiz || !is_dir($real)) {
            return;
        }
        if (count(scandir($real)) > 2 || !@rmdir($real)) {
            return;
        }
        $dir = dirname($real);
    }
}
