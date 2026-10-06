# MoneyFan v2 · Gastos y documentos con MySQL

Evolución de la demo v1 (Kivy, datos simulados) a una aplicación real:
**PWA instalable + Backend/API PHP + MySQL**, lista para hosting **cPanel**.

```
Usuario ─► Frontend PWA (HTML/CSS/JS) ─► Backend/API (PHP + PDO) ─► MySQL
                                                │
Documentos: Registro ─► /uploads/AAAA/MM/DD/0001_AAAA-MM-DD_proveedor.jpg
            ─► referencia en tabla "documentos" ─► Historial ─► Visor
```

> **¿Por qué ya no es Kivy?** La v2 exige instalación como **PWA** y despliegue en
> **cPanel (PHP + MySQL)**. Una PWA es, por definición, una aplicación web, así que la
> interfaz pasó a HTML/CSS/JavaScript. Se mantiene la identidad de la v1: nombre, paleta
> azul petróleo/salvia/arena, ingreso con RUT `12.345.678-9`, montos enteros positivos y
> comentarios `[SECCIÓN N]` en cada archivo. La v1 sigue intacta en la carpeta superior.

---

## 1. Cuentas de ejemplo

| Perfil  | RUT           | Contraseña |
|---------|---------------|------------|
| Admin   | 11.111.111-1  | admin123   |
| Usuaria | 12.345.678-9  | 123456     |

Las contraseñas se guardan con **bcrypt** (`password_hash`); en la BD nunca hay texto plano.
Cámbialas desde *Perfil → Cambiar contraseña* después de la primera entrada.

---

## 2. Requisitos cumplidos

| Requisito | Dónde |
|---|---|
| BD funcional con conexión real (no simulada) | `api/_lib/db.php` (PDO + sentencias preparadas) |
| Archivo SQL de estructura | `database/moneyfan.sql` |
| phpMyAdmin para crear/importar/verificar | Paso 4 de la guía + vista `v_resumen_mensual` |
| Desplegable en cPanel / PHP / MySQL | Guía de la sección 3 + `.htaccess` |
| Credenciales fuera del código | `moneyfan_config/config.php` **fuera de public_html** (solo se entrega `config.example.php`) |
| Login con RUT y contraseña (Admin y Usuaria) | `api/auth.php`, pantalla de ingreso |
| Registro de boleta/factura/otro, proveedor, monto, descripción, fecha | `api/gastos.php` + formulario *Registrar gasto* |
| Captura (cámara) y carga de documentos + correlativo automático | Botones *Tomar foto* / *Subir archivo*; tabla `secuencias` con bloqueo `FOR UPDATE` |
| Historial con visualización de documentos | *Historial* → gasto → visor de imagen/PDF (`api/documentos.php`) |
| Carpetas año/mes/día + resumen mensual | *Historial* (Año › Mes › Día) y `api/resumen.php?accion=mes` |
| Contraseñas protegidas, interfaz adaptable al celular | bcrypt; CSS mobile-first con modo oscuro |
| PWA + Backend/API conectado a MySQL | `manifest.webmanifest`, `sw.js`, `api/*.php` |
| Validación de datos y mensajes de error | Navegador (`assets/js/validaciones.js`) **y** servidor (`api/_lib/validaciones.php`), error bajo cada campo |
| Modificar y eliminar gastos y documentos | *Editar* / *Eliminar* en el detalle; ícono 🗑 en cada documento |
| **Admin:** crear usuarios, activar/desactivar, cambiar contraseña, ver todos los usuarios, ver todos los gastos, ver totales, ver cantidad de documentos, exportar CSV/Excel | Menú *Admin* (`api/admin.php`, `api/exportar.php`) |

### Seguridad básica incluida
- Sesión PHP con cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` con HTTPS) y cierre por inactividad (2 h).
- Token **CSRF** en toda petición que modifica datos.
- Bloqueo temporal tras **5 intentos fallidos** de login por RUT (15 min).
- **Sentencias preparadas** en todas las consultas (sin inyección SQL).
- Archivos validados por su **contenido real** (JPG/PNG/WEBP/PDF, máx. 5 MB); el nombre lo decide el servidor.
- `uploads/` bloqueada al acceso directo: las boletas solo se entregan vía API a su dueño o al Admin.
- Plantillas que **escapan todo texto** (evita XSS) y cabeceras de seguridad (CSP, nosniff, X-Frame-Options).
- CSV protegido contra inyección de fórmulas; HTTPS forzado en el hosting.

---

## 3. Despliegue en cPanel (paso a paso)

1. **Base de datos** – cPanel → *Bases de datos MySQL*:
   - Crea la base `moneyfan` (quedará como `tuusuario_moneyfan`).
   - Crea un usuario con una contraseña segura (`tuusuario_appuser`).
   - *Añadir usuario a la base de datos* → marca **TODOS LOS PRIVILEGIOS**.
2. **phpMyAdmin** – cPanel → *phpMyAdmin* → selecciona la base a la izquierda →
   pestaña **Importar** → elige `database/moneyfan.sql` → **Importar**.
3. **Verifica** en la pestaña **SQL**:
   ```sql
   SELECT id, rut, nombre, rol, activo FROM usuarios;
   SELECT * FROM v_resumen_mensual;
   ```
4. **Archivos de la app** – cPanel → *Administrador de archivos*:
   - Sube el **contenido** de `public_html/` a tu `public_html/` (o a una subcarpeta, ej. `public_html/moneyfan/`).
   - Activa *Configuración → Mostrar archivos ocultos* y confirma que se subieron los `.htaccess`.
5. **Credenciales (fuera de lo público)** – en tu carpeta personal (`/home/tuusuario/`, al
   mismo nivel que `public_html`) crea la carpeta `moneyfan_config/` y dentro sube
   `config.example.php` renombrado a **`config.php`**. Edítalo con los datos del paso 1:
   ```
   /home/tuusuario/
   ├── moneyfan_config/config.php   ← credenciales reales (NO público)
   └── public_html/                 ← la app
   ```
6. **PHP** – cPanel → *Select PHP Version*: **PHP 8.1 o superior**, con `pdo_mysql`,
   `fileinfo` y `mbstring` activas. En *Options*: `upload_max_filesize = 6M`, `post_max_size = 32M`.
7. **HTTPS** – cPanel → *SSL/TLS Status* → **Run AutoSSL**. Es obligatorio para instalar la
   PWA y para la cámara del celular (el `.htaccess` ya redirige a https).
8. **Permisos** – la carpeta `uploads/` debe tener permisos `755` (el servidor crea las subcarpetas solo).
9. Entra a `https://tudominio.cl/` (o `/moneyfan/`), ingresa como Admin y **cambia las contraseñas de ejemplo**.

**Instalar como app:** en Android/Chrome o Edge → *Perfil → Instalar app* (o el ícono de
instalar en la barra). En iPhone → Safari → Compartir → *Agregar a inicio*.

---

## 4. Probar en tu computador (VS Code + XAMPP)

1. Abre **XAMPP Control Panel** e inicia **MySQL** (Apache es opcional).
2. En `http://localhost/phpmyadmin` crea la base `moneyfan` (utf8mb4_unicode_ci) e
   **importa** `database/moneyfan.sql`.
3. Copia `moneyfan_config/config.example.php` como `moneyfan_config/config.php` y pon:
   `'nombre' => 'moneyfan'`, `'usuario' => 'root'`, `'clave' => ''`, `'modo_debug' => true`.
4. Abre la carpeta `MoneyFan_v2` en VS Code → *Terminal → Ejecutar tarea…* →
   **"MoneyFan: servidor PHP local"** (o en la terminal):
   ```bash
   php -S localhost:8000 -t public_html
   ```
5. Abre `http://localhost:8000`.

> Con Apache de XAMPP: copia `public_html` a `C:\xampp\htdocs\moneyfan` y `moneyfan_config`
> a `C:\xampp\moneyfan_config`; abre `http://localhost/moneyfan`.
> En `localhost` la PWA y la cámara funcionan sin HTTPS.

---

## 5. Estructura del proyecto

```
MoneyFan_v2/
├── database/
│   └── moneyfan.sql              # Tablas, vista y datos de ejemplo (importar en phpMyAdmin)
├── moneyfan_config/              # ⚠ va FUERA de public_html
│   ├── config.example.php        # Plantilla (sin credenciales reales)
│   └── .htaccess                 # Bloqueo extra por si queda expuesta
└── public_html/                  # Lo que se sube al hosting
    ├── index.html                # Esqueleto de la PWA
    ├── manifest.webmanifest      # Datos de instalación (nombre, íconos, colores)
    ├── sw.js                     # Service Worker (caché del cascarón, nunca de la API)
    ├── .htaccess                 # HTTPS, cabeceras de seguridad, MIME
    ├── assets/
    │   ├── css/app.css           # Diseño mobile-first + modo oscuro
    │   ├── icons/                # Íconos de la app
    │   └── js/
    │       ├── app.js            # Arranque, router y navegación
    │       ├── api.js            # Puente Frontend → Backend/API
    │       ├── ui.js             # Plantillas seguras, avisos, gráficos, visor
    │       ├── validaciones.js   # Reglas en el navegador
    │       ├── vistas_usuario.js # Login, inicio, registro, historial, perfil
    │       └── vistas_admin.js   # Panel, usuarios, gastos de todos
    ├── api/
    │   ├── auth.php              # login / sesión / logout / cambiar clave
    │   ├── gastos.php            # CRUD de gastos + subida de documentos
    │   ├── documentos.php        # ver / descargar / eliminar documento
    │   ├── resumen.php           # inicio, carpetas año/mes/día, resumen mensual
    │   ├── admin.php             # funciones del Administrador
    │   ├── exportar.php          # CSV y Excel (.xlsx)
    │   └── _lib/                 # Núcleo (bloqueado al navegador)
    │       ├── bootstrap.php     # Config, sesión segura, cabeceras
    │       ├── db.php            # Conexión PDO + correlativo
    │       ├── auth.php          # Permisos, CSRF, anti fuerza bruta
    │       ├── validaciones.php  # Reglas del servidor
    │       ├── archivos.php      # uploads/AAAA/MM/DD y nombres 0001_...
    │       ├── consultas.php     # Consultas comunes de gastos
    │       ├── respuesta.php     # Formato JSON de respuestas
    │       └── excel.php         # Generador .xlsx sin librerías
    └── uploads/                  # Documentos (protegida por .htaccess)
        └── 2026/10/04/0001_2026-10-04_proveedor.jpg
```

## 6. Base de datos

| Tabla | Para qué |
|---|---|
| `usuarios` | RUT único, nombre, hash bcrypt, rol (`admin`/`usuario`), activo |
| `categorias` | Snacks, Farmacia, Café, Transporte, Supermercado, Servicios, Otros |
| `gastos` | Tipo, N° documento, proveedor, monto (entero), descripción, fecha, categoría, **correlativo único** |
| `documentos` | Referencia al archivo: `ruta_relativa` (`2026/10/04/0001_...jpg`), MIME, tamaño |
| `secuencias` | Último correlativo entregado |
| `intentos_login` | Registro para bloquear fuerza bruta |
| `v_resumen_mensual` (vista) | Totales por usuario y mes, para verificar en phpMyAdmin |

## 7. Reglas de validación

- **RUT** `12.345.678-9` (se formatea solo al salir del campo). Para exigir dígito verificador
  real: `'validar_dv_rut' => true` en `config.php`.
- **Contraseña**: mínimo 6 caracteres, sin espacios.
- **Monto**: solo enteros positivos, sin puntos (el campo bloquea letras y signos), máx. $50.000.000.
- **Proveedor** 2–60 caracteres · **Descripción** 3–160 · **Fecha** real, desde el año 2000 y no futura.
- **Documentos**: JPG/PNG/WEBP/PDF, máx. 5 MB c/u, hasta 5 por gasto. Las fotos se comprimen
  en el celular antes de subir (máx. 1600 px).
- Si cambias la fecha o el proveedor de un gasto, sus archivos se **mueven y renombran** solos
  a la carpeta del nuevo día.
