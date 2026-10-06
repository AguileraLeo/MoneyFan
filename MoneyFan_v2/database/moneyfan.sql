-- =====================================================================
--  MoneyFan v2 · database/moneyfan.sql
-- ---------------------------------------------------------------------
--  [QUÉ HACE ESTE ARCHIVO]
--  Crea TODA la estructura de la base de datos de MoneyFan v2 y carga
--  los datos de ejemplo (1 Admin + 1 Usuaria + gastos de muestra).
--
--  CÓMO USARLO (phpMyAdmin):
--    1. Crea la base de datos (en cPanel: "Bases de datos MySQL").
--    2. Entra a phpMyAdmin, selecciónala en el panel izquierdo.
--    3. Pestaña "Importar" -> elige este archivo -> "Importar".
--  No incluye "CREATE DATABASE" a propósito: en cPanel el nombre de la
--  base lleva un prefijo (ej: usuario_moneyfan) y se crea desde cPanel.
--
--  Compatible con MySQL 5.7+ / 8.x y MariaDB 10.3+.
--
--  CUENTAS DE EJEMPLO (cámbialas después de la primera entrada):
--    Admin   -> RUT 11.111.111-1  clave admin123
--    Usuaria -> RUT 12.345.678-9  clave 123456
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS v_resumen_mensual;
DROP TABLE IF EXISTS intentos_login;
DROP TABLE IF EXISTS documentos;
DROP TABLE IF EXISTS gastos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS secuencias;
DROP TABLE IF EXISTS usuarios;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- [SECCIÓN 1] USUARIOS
-- Cada persona que entra a la app. La contraseña NUNCA se guarda en
-- texto plano: "clave_hash" contiene un hash bcrypt (password_hash de
-- PHP). "rol" distingue al Admin de la Usuaria; "activo" permite al
-- Admin bloquear una cuenta sin borrarla.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  rut             VARCHAR(12)      NOT NULL COMMENT 'Formato 12.345.678-9',
  nombre          VARCHAR(60)      NOT NULL,
  email           VARCHAR(120)     NULL,
  clave_hash      VARCHAR(255)     NOT NULL COMMENT 'bcrypt (password_hash)',
  rol             ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  activo          TINYINT(1)       NOT NULL DEFAULT 1,
  ultimo_acceso   DATETIME         NULL,
  creado_en       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_rut (rut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- [SECCIÓN 2] CATEGORÍAS
-- Las mismas categorías de la v1 (con su color) + "Supermercado" y
-- "Servicios" para gastos que ya no son solo micro.
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
  id      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(30)      NOT NULL,
  color   CHAR(7)          NOT NULL COMMENT 'Color hex para gráficos',
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- [SECCIÓN 3] SECUENCIAS (correlativo automático)
-- Guarda el último correlativo entregado. El backend lo incrementa
-- dentro de una transacción con bloqueo (SELECT ... FOR UPDATE), así
-- dos registros simultáneos nunca reciben el mismo número.
-- ---------------------------------------------------------------------
CREATE TABLE secuencias (
  nombre  VARCHAR(30)  NOT NULL,
  valor   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- [SECCIÓN 4] GASTOS
-- Un registro por boleta/factura/otro. "correlativo" es el número
-- único que también aparece en el nombre de los archivos subidos
-- (0001_2026-10-04_proveedor.jpg). El monto es un entero (pesos CLP).
-- ---------------------------------------------------------------------
CREATE TABLE gastos (
  id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  usuario_id        INT UNSIGNED     NOT NULL,
  correlativo       INT UNSIGNED     NOT NULL,
  tipo_documento    ENUM('boleta','factura','otro') NOT NULL DEFAULT 'boleta',
  numero_documento  VARCHAR(30)      NULL COMMENT 'Folio de la boleta/factura (opcional)',
  proveedor         VARCHAR(60)      NOT NULL,
  monto             INT UNSIGNED     NOT NULL,
  descripcion       VARCHAR(160)     NOT NULL,
  fecha             DATE             NOT NULL,
  categoria_id      TINYINT UNSIGNED NOT NULL,
  creado_en         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gastos_correlativo (correlativo),
  KEY idx_gastos_usuario_fecha (usuario_id, fecha),
  KEY idx_gastos_fecha (fecha),
  CONSTRAINT fk_gastos_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_gastos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- [SECCIÓN 5] DOCUMENTOS
-- Referencia en la BD a cada archivo guardado en /uploads/.
-- "ruta_relativa" = 2026/10/04/0001_2026-10-04_proveedor.jpg
-- El archivo físico vive en disco; aquí solo se guarda dónde está.
-- Al borrar un gasto se borran sus documentos (ON DELETE CASCADE);
-- el backend elimina además los archivos físicos.
-- ---------------------------------------------------------------------
CREATE TABLE documentos (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  gasto_id         INT UNSIGNED  NOT NULL,
  nombre_archivo   VARCHAR(120)  NOT NULL,
  ruta_relativa    VARCHAR(200)  NOT NULL,
  nombre_original  VARCHAR(160)  NULL,
  mime             VARCHAR(60)   NOT NULL,
  tamano_bytes     INT UNSIGNED  NOT NULL,
  creado_en        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_documentos_ruta (ruta_relativa),
  KEY idx_documentos_gasto (gasto_id),
  CONSTRAINT fk_documentos_gasto FOREIGN KEY (gasto_id) REFERENCES gastos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- [SECCIÓN 6] INTENTOS DE LOGIN (seguridad básica)
-- Registra cada intento para bloquear temporalmente un RUT o una IP
-- tras varios intentos fallidos (protección contra fuerza bruta).
-- ---------------------------------------------------------------------
CREATE TABLE intentos_login (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rut        VARCHAR(12)  NOT NULL,
  ip         VARCHAR(45)  NOT NULL,
  exito      TINYINT(1)   NOT NULL DEFAULT 0,
  creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_intentos_rut (rut, creado_en),
  KEY idx_intentos_ip (ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- [SECCIÓN 7] VISTA: RESUMEN MENSUAL
-- Útil para VERIFICAR en phpMyAdmin que los datos se guardan:
--   SELECT * FROM v_resumen_mensual;
-- ---------------------------------------------------------------------
CREATE VIEW v_resumen_mensual AS
SELECT  u.rut,
        u.nombre,
        YEAR(g.fecha)            AS anio,
        MONTH(g.fecha)           AS mes,
        COUNT(DISTINCT g.id)     AS cantidad_gastos,
        SUM(g.monto)             AS total_monto,
        (SELECT COUNT(*) FROM documentos d
           JOIN gastos g2 ON g2.id = d.gasto_id
          WHERE g2.usuario_id = u.id
            AND YEAR(g2.fecha) = YEAR(g.fecha)
            AND MONTH(g2.fecha) = MONTH(g.fecha)) AS cantidad_documentos
FROM gastos g
JOIN usuarios u ON u.id = g.usuario_id
GROUP BY u.id, u.rut, u.nombre, YEAR(g.fecha), MONTH(g.fecha);

-- =====================================================================
-- [SECCIÓN 8] DATOS DE EJEMPLO
-- =====================================================================

-- 8.1 Cuentas: Admin y Usuaria (hashes bcrypt de "admin123" y "123456")
INSERT INTO usuarios (id, rut, nombre, email, clave_hash, rol, activo) VALUES
  (1, '11.111.111-1', 'Administrador MoneyFan', 'admin@moneyfan.cl',
      '$2y$10$mfu3COQ7nPzOtjsPGHztLusstIsHmGuIPyriapfxcuxkkfbvwHLl6', 'admin', 1),
  (2, '12.345.678-9', 'Leonarda Usuaria', 'usuaria@moneyfan.cl',
      '$2y$10$S8K5P.8zdKJO7iK1iv8Gb.wpsffMg2vDuBscLDnt7k3IGazmvGMla', 'usuario', 1);

-- 8.2 Categorías (colores de la paleta suave de la v1)
INSERT INTO categorias (id, nombre, color) VALUES
  (1, 'Snacks',       '#C9A66B'),
  (2, 'Farmacia',     '#86A397'),
  (3, 'Café',         '#9C7A6B'),
  (4, 'Transporte',   '#5B7C8D'),
  (5, 'Supermercado', '#7E9CB5'),
  (6, 'Servicios',    '#B58A8A'),
  (7, 'Otros',        '#9A8FB0');

-- 8.3 Gastos de muestra de la Usuaria. Las fechas son RELATIVAS a hoy
-- (CURDATE() - INTERVAL n DAY) para que la demo siempre tenga datos
-- recientes en el día, la semana y el mes. No traen documento adjunto:
-- los documentos se suben desde la app.
INSERT INTO gastos (usuario_id, correlativo, tipo_documento, numero_documento, proveedor, monto, descripcion, fecha, categoria_id) VALUES
  (2,  1, 'boleta',  '220956', 'Café Aroma',          2800, 'Café latte para llevar',            CURDATE(),                       3),
  (2,  2, 'boleta',  '603730', 'Kiosco Don Pepe',      500, 'Chicle menta',                      CURDATE(),                       1),
  (2,  3, 'boleta',  '427000', 'Recarga Movilidad',   1800, 'Pasaje bus interurbano',            CURDATE() - INTERVAL 1 DAY,      4),
  (2,  4, 'boleta',  '118342', 'Farmacia Salud+',     3990, 'Desodorante roll-on',               CURDATE() - INTERVAL 1 DAY,      2),
  (2,  5, 'boleta',  '774120', 'Kiosco Don Pepe',     1200, 'Galletas de chocolate',             CURDATE() - INTERVAL 2 DAY,      1),
  (2,  6, 'factura', 'F-3381', 'Supermercado Central',18450, 'Compra semanal de abarrotes',      CURDATE() - INTERVAL 3 DAY,      5),
  (2,  7, 'boleta',  '220011', 'Café Aroma',          2500, 'Capuccino',                         CURDATE() - INTERVAL 4 DAY,      3),
  (2,  8, 'otro',    NULL,     'Feria libre',         3000, 'Frutas (pago en efectivo)',         CURDATE() - INTERVAL 6 DAY,      7),
  (2,  9, 'boleta',  '553201', 'Recarga Movilidad',   5000, 'Recarga tarjeta de transporte',     CURDATE() - INTERVAL 9 DAY,      4),
  (2, 10, 'factura', 'F-9920', 'Internet Hogar',     19990, 'Plan de internet mensual',          CURDATE() - INTERVAL 12 DAY,     6),
  (2, 11, 'boleta',  '118999', 'Farmacia Salud+',     6490, 'Vitamina C',                        CURDATE() - INTERVAL 18 DAY,     2),
  (2, 12, 'boleta',  '774777', 'Kiosco Don Pepe',      900, 'Jugo en caja',                      CURDATE() - INTERVAL 25 DAY,     1),
  (2, 13, 'boleta',  '220777', 'Café Aroma',          3200, 'Café + medialuna',                  CURDATE() - INTERVAL 33 DAY,     3),
  (2, 14, 'factura', 'F-3122', 'Supermercado Central',22780, 'Compra mensual',                   CURDATE() - INTERVAL 40 DAY,     5),
  (2, 15, 'boleta',  '552000', 'Recarga Movilidad',   5000, 'Recarga tarjeta de transporte',     CURDATE() - INTERVAL 45 DAY,     4);

-- 8.4 El correlativo continúa desde el último gasto de muestra
INSERT INTO secuencias (nombre, valor) VALUES ('gastos', 15);

-- =====================================================================
-- [SECCIÓN 9] VERIFICACIÓN RÁPIDA EN phpMyAdmin (pestaña "SQL")
--   SELECT id, rut, nombre, rol, activo FROM usuarios;
--   SELECT correlativo, proveedor, monto, fecha FROM gastos ORDER BY fecha DESC;
--   SELECT * FROM v_resumen_mensual;
--   SELECT * FROM documentos;   -- se llena al subir boletas desde la app
-- =====================================================================
