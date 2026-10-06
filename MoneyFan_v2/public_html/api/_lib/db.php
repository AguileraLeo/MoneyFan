<?php
/**
 * =====================================================================
 *  MoneyFan v2 · api/_lib/db.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  Crea la CONEXIÓN REAL a MySQL usando PDO. Se abre una sola vez por
 *  petición (patrón singleton) y se reutiliza en toda la API.
 *  Todas las consultas del proyecto usan sentencias preparadas
 *  (prepare + execute) para evitar inyección SQL.
 * =====================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// [SECCIÓN 1] CONEXIÓN
// - ERRMODE_EXCEPTION: cualquier error SQL lanza una excepción.
// - EMULATE_PREPARES=false: el servidor MySQL prepara la consulta.
// - time_zone de la sesión alineada con PHP (fechas coherentes).
// ---------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $CONFIG;
    $c = $CONFIG['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'], (int)($c['puerto'] ?? 3306), $c['nombre'], $c['charset'] ?? 'utf8mb4'
    );

    try {
        $pdo = new PDO($dsn, $c['usuario'], $c['clave'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('[MoneyFan] Error de conexión a MySQL: ' . $e->getMessage());
        responder_error(
            MF_DEBUG ? 'No se pudo conectar a MySQL: ' . $e->getMessage()
                     : 'No se pudo conectar con la base de datos.',
            503
        );
    }

    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $pdo;
}

// ---------------------------------------------------------------
// [SECCIÓN 2] ATAJOS DE CONSULTA
// ---------------------------------------------------------------
/** Ejecuta una consulta preparada y devuelve el statement. */
function consulta(string $sql, array $parametros = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($parametros);
    return $st;
}

/** Devuelve una sola fila (o null). */
function fila(string $sql, array $parametros = []): ?array
{
    $r = consulta($sql, $parametros)->fetch();
    return $r === false ? null : $r;
}

/** Devuelve todas las filas. */
function filas(string $sql, array $parametros = []): array
{
    return consulta($sql, $parametros)->fetchAll();
}

// ---------------------------------------------------------------
// [SECCIÓN 3] CORRELATIVO AUTOMÁTICO
// Debe llamarse DENTRO de una transacción. "FOR UPDATE" bloquea la
// fila de la secuencia hasta el commit, así dos usuarios que guardan
// al mismo tiempo nunca obtienen el mismo número.
// ---------------------------------------------------------------
function siguiente_correlativo(string $nombre = 'gastos'): int
{
    $actual = fila('SELECT valor FROM secuencias WHERE nombre = ? FOR UPDATE', [$nombre]);
    if ($actual === null) {
        consulta('INSERT INTO secuencias (nombre, valor) VALUES (?, 1)', [$nombre]);
        return 1;
    }
    $nuevo = (int)$actual['valor'] + 1;
    consulta('UPDATE secuencias SET valor = ? WHERE nombre = ?', [$nuevo, $nombre]);
    return $nuevo;
}
