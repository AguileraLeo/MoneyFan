<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/_lib/validaciones.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Validación de datos en el SERVIDOR (la del navegador ayuda al
 *  usuario, pero la que manda es esta: nunca se confía en el cliente).
 *  Mantiene las mismas reglas de la v1 (RUT 12.345.678-9, montos
 *  enteros positivos, campos obligatorios) ampliadas para la v2.
 *  Cada validador devuelve un mensaje de error o null si está bien.
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// [SECCIÓN 1] REGLAS (mismas en el frontend: assets/js/validaciones.js)
// ---------------------------------------------------------------
const MF_CLAVE_MIN       = 6;
const MF_CLAVE_MAX       = 72;          // límite de bcrypt
const MF_MONTO_MIN       = 1;
const MF_MONTO_MAX       = 50000000;    // $50.000.000
const MF_PROVEEDOR_MIN   = 2;
const MF_PROVEEDOR_MAX   = 60;
const MF_DESC_MIN        = 3;
const MF_DESC_MAX        = 160;
const MF_NOMBRE_MIN      = 3;
const MF_NOMBRE_MAX      = 60;
const MF_TIPOS_DOCUMENTO = ['boleta', 'factura', 'otro'];

// ---------------------------------------------------------------
// [SECCIÓN 2] RUT CHILENO
// ---------------------------------------------------------------
function limpiar_rut(string $rut): string
{
    return strtoupper(str_replace(['.', '-', ' '], '', $rut));
}

/** '123456789' -> '12.345.678-9' (o lo devuelve igual si no se puede). */
function formatear_rut(string $rut): string
{
    $limpio = limpiar_rut($rut);
    if (strlen($limpio) < 2 || !ctype_digit(substr($limpio, 0, -1))) {
        return trim($rut);
    }
    $cuerpo = substr($limpio, 0, -1);
    $dv     = substr($limpio, -1);
    return number_format((int)$cuerpo, 0, '', '.') . '-' . $dv;
}

/** Dígito verificador con el algoritmo módulo 11. */
function calcular_dv(string $cuerpo): string
{
    $suma = 0;
    $multiplicador = 2;
    for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
        $suma += (int)$cuerpo[$i] * $multiplicador;
        $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
    }
    $resto = 11 - ($suma % 11);
    return $resto === 11 ? '0' : ($resto === 10 ? 'K' : (string)$resto);
}

function validar_rut(string $rut): ?string
{
    global $CONFIG;
    $rut = trim($rut);
    if ($rut === '') {
        return 'Ingresa el RUT';
    }
    if (!preg_match('/^\d{1,2}\.\d{3}\.\d{3}-[\dkK]$/', $rut)) {
        return 'Formato inválido. Ej: 12.345.678-9';
    }
    if (!empty($CONFIG['app']['validar_dv_rut'])) {
        $limpio = limpiar_rut($rut);
        if (calcular_dv(substr($limpio, 0, -1)) !== substr($limpio, -1)) {
            return 'El dígito verificador no es válido';
        }
    }
    return null;
}

// ---------------------------------------------------------------
// [SECCIÓN 3] CONTRASEÑA Y DATOS DE USUARIO
// ---------------------------------------------------------------
function validar_clave(string $clave): ?string
{
    if ($clave === '') {
        return 'Ingresa la contraseña';
    }
    if (preg_match('/\s/', $clave)) {
        return 'La contraseña no puede tener espacios';
    }
    $largo = mb_strlen($clave);
    if ($largo < MF_CLAVE_MIN) {
        return 'Mínimo ' . MF_CLAVE_MIN . ' caracteres';
    }
    if ($largo > MF_CLAVE_MAX) {
        return 'Máximo ' . MF_CLAVE_MAX . ' caracteres';
    }
    return null;
}

function validar_nombre(string $nombre): ?string
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        return 'Ingresa el nombre';
    }
    $largo = mb_strlen($nombre);
    if ($largo < MF_NOMBRE_MIN || $largo > MF_NOMBRE_MAX) {
        return 'Entre ' . MF_NOMBRE_MIN . ' y ' . MF_NOMBRE_MAX . ' caracteres';
    }
    return null;
}

function validar_email_opcional(string $email): ?string
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? null : 'Correo no válido';
}

// ---------------------------------------------------------------
// [SECCIÓN 4] DATOS DEL GASTO
// ---------------------------------------------------------------
/** Monto: entero positivo SIN puntos ni decimales (igual que la v1). */
function validar_monto($monto): ?string
{
    $texto = trim((string)$monto);
    if ($texto === '') {
        return 'Ingresa el monto';
    }
    if (!preg_match('/^[1-9]\d*$/', $texto)) {
        return 'Solo números enteros positivos (sin puntos)';
    }
    if (strlen($texto) > 9 || (int)$texto > MF_MONTO_MAX) {
        return 'El monto máximo es $' . number_format(MF_MONTO_MAX, 0, ',', '.');
    }
    return null;
}

function validar_texto(string $texto, int $min, int $max, string $vacio): ?string
{
    $texto = trim($texto);
    if ($texto === '') {
        return $vacio;
    }
    if (!mb_check_encoding($texto, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $texto)) {
        return 'Contiene caracteres no válidos';
    }
    $largo = mb_strlen($texto);
    if ($largo < $min) {
        return "Mínimo $min caracteres";
    }
    if ($largo > $max) {
        return "Máximo $max caracteres";
    }
    return null;
}

/** Fecha AAAA-MM-DD real, no futura y desde el año 2000. */
function validar_fecha(string $fecha): ?string
{
    if ($fecha === '') {
        return 'Ingresa la fecha';
    }
    $d = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        return 'Fecha no válida';
    }
    if ($d > new DateTime('today')) {
        return 'La fecha no puede ser futura';
    }
    if ((int)$d->format('Y') < 2000) {
        return 'La fecha debe ser desde el año 2000';
    }
    return null;
}

/**
 * Valida TODOS los campos de un gasto de una vez y devuelve
 * [datosLimpios, erroresPorCampo]. Si hay errores el endpoint responde
 * 422 con el detalle para mostrarlo junto a cada input.
 */
function validar_gasto(array $e): array
{
    $err = [];

    $tipo = (string)($e['tipo_documento'] ?? '');
    if (!in_array($tipo, MF_TIPOS_DOCUMENTO, true)) {
        $err['tipo_documento'] = 'Elige boleta, factura u otro';
    }

    $numero = trim((string)($e['numero_documento'] ?? ''));
    if ($numero !== '' && !preg_match('/^[A-Za-z0-9\-\/ ]{1,30}$/', $numero)) {
        $err['numero_documento'] = 'Solo letras, números y guiones (máx. 30)';
    }

    if ($m = validar_texto((string)($e['proveedor'] ?? ''), MF_PROVEEDOR_MIN, MF_PROVEEDOR_MAX, 'Ingresa el proveedor')) {
        $err['proveedor'] = $m;
    }
    if ($m = validar_monto($e['monto'] ?? '')) {
        $err['monto'] = $m;
    }
    if ($m = validar_texto((string)($e['descripcion'] ?? ''), MF_DESC_MIN, MF_DESC_MAX, 'Completa la descripción')) {
        $err['descripcion'] = $m;
    }
    if ($m = validar_fecha((string)($e['fecha'] ?? ''))) {
        $err['fecha'] = $m;
    }

    $categoriaId = filter_var($e['categoria_id'] ?? null, FILTER_VALIDATE_INT);
    if ($categoriaId === false || !fila('SELECT id FROM categorias WHERE id = ?', [$categoriaId])) {
        $err['categoria_id'] = 'Selecciona una categoría';
    }

    $limpios = [
        'tipo_documento'   => $tipo,
        'numero_documento' => $numero !== '' ? $numero : null,
        'proveedor'        => trim((string)($e['proveedor'] ?? '')),
        'monto'            => (int)($e['monto'] ?? 0),
        'descripcion'      => trim((string)($e['descripcion'] ?? '')),
        'fecha'            => (string)($e['fecha'] ?? ''),
        'categoria_id'     => (int)$categoriaId,
    ];
    return [$limpios, $err];
}
