<?php
/**
 * =====================================================================
 *  MoneyFan v2 · moneyfan_config/config.example.php
 * ---------------------------------------------------------------------
 *  [QUÉ HACE ESTE ARCHIVO]
 *  PLANTILLA de configuración. Las credenciales REALES nunca van en el
 *  código entregado: copia este archivo como "config.php" en la carpeta
 *  moneyfan_config/ que queda FUERA de public_html y rellena los datos.
 *
 *    /home/tu_usuario_cpanel/
 *       ├── moneyfan_config/
 *       │     └── config.php      <- datos reales (NO público)
 *       └── public_html/          <- la app (sí pública)
 *
 *  El backend busca moneyfan_config/config.php subiendo carpetas desde
 *  public_html/api/, así funciona aunque la app esté en una subcarpeta.
 *  Alternativa: definir la variable de entorno MONEYFAN_CONFIG con la
 *  ruta absoluta del archivo.
 * =====================================================================
 */

return [
    // -----------------------------------------------------------------
    // [SECCIÓN 1] BASE DE DATOS MySQL
    // En cPanel: "Bases de datos MySQL" -> crea la base y el usuario,
    // y asígnale TODOS los privilegios sobre esa base.
    // Ojo: cPanel antepone tu usuario (ej: miusuario_moneyfan).
    // -----------------------------------------------------------------
    'db' => [
        'host'     => 'localhost',
        'puerto'   => 3306,
        'nombre'   => 'cpanelusuario_moneyfan',
        'usuario'  => 'cpanelusuario_appuser',
        'clave'    => 'CAMBIA_ESTA_CLAVE',
        'charset'  => 'utf8mb4',
    ],

    // -----------------------------------------------------------------
    // [SECCIÓN 2] APLICACIÓN
    // -----------------------------------------------------------------
    'app' => [
        'zona_horaria'   => 'America/Santiago',
        'modo_debug'     => false,   // true SOLO en local: muestra errores técnicos
        'validar_dv_rut' => false,   // true = exige dígito verificador real (módulo 11)
        // Carpeta física de los documentos. null = public_html/uploads
        // (protegida por .htaccess). Puedes moverla fuera de public_html
        // indicando una ruta absoluta, ej: '/home/usuario/moneyfan_uploads'
        'ruta_uploads'   => null,
    ],

    // -----------------------------------------------------------------
    // [SECCIÓN 3] SEGURIDAD
    // -----------------------------------------------------------------
    'seguridad' => [
        'max_intentos_login'   => 5,     // fallos permitidos...
        'ventana_bloqueo_min'  => 15,    // ...dentro de estos minutos
        'sesion_inactividad_min' => 120, // cierra la sesión tras 2 h sin uso
    ],
];
