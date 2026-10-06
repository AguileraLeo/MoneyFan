/* =====================================================================
   MoneyFan v2 · assets/js/api.js
   ---------------------------------------------------------------------
   [QUÉ HACE ESTE ARCHIVO]
   Es el "puente" entre el Frontend y el Backend/API PHP.
   Todas las pantallas llaman a estas funciones en vez de usar fetch
   directamente, así en un solo lugar se resuelve:
     - Enviar la cookie de sesión y el token CSRF.
     - Convertir la respuesta JSON y detectar errores.
     - Volver al login si la sesión expiró (401).
   Flujo: Usuario -> Frontend -> api.js -> /api/*.php -> MySQL
   ===================================================================== */

// ---------------------------------------------------------------
// [SECCIÓN 1] ESTADO DE LA SESIÓN
// El token CSRF vive solo en memoria (no en localStorage) para que
// un script ajeno no pueda leerlo.
// ---------------------------------------------------------------
const BASE = 'api/';
let csrf = null;
let alExpirar = () => {};

export function fijarCsrf(token) { csrf = token; }
export function alExpirarSesion(fn) { alExpirar = fn; }

/** Error con el detalle por campo que devuelve la API (422). */
export class ErrorApi extends Error {
  constructor(mensaje, estado, campos = {}) {
    super(mensaje);
    this.estado = estado;
    this.campos = campos;
  }
}

// ---------------------------------------------------------------
// [SECCIÓN 2] PETICIÓN GENÉRICA
// - GET: parámetros en la URL.
// - POST con objeto: se envía como JSON.
// - POST con FormData: multipart (para adjuntar documentos).
// ---------------------------------------------------------------
async function pedir(archivo, accion, { metodo = 'GET', params = {}, cuerpo = null } = {}) {
  const url = new URL(BASE + archivo, document.baseURI);
  url.searchParams.set('accion', accion);
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
  }

  const opciones = { method: metodo, credentials: 'same-origin', headers: {} };
  if (metodo !== 'GET') {
    if (csrf) opciones.headers['X-CSRF-Token'] = csrf;
    if (cuerpo instanceof FormData) {
      opciones.body = cuerpo;                       // el navegador pone el boundary
    } else if (cuerpo) {
      opciones.headers['Content-Type'] = 'application/json';
      opciones.body = JSON.stringify(cuerpo);
    }
  }

  let resp;
  try {
    resp = await fetch(url, opciones);
  } catch {
    throw new ErrorApi('Sin conexión con el servidor. Revisa tu internet.', 0);
  }

  let datos = null;
  try { datos = await resp.json(); } catch { /* respuesta no JSON */ }

  if (!resp.ok || !datos || datos.ok === false) {
    const mensaje = datos?.error || `Error del servidor (${resp.status})`;
    if (resp.status === 401 && accion !== 'login') alExpirar(mensaje);
    throw new ErrorApi(mensaje, resp.status, datos?.campos || {});
  }
  return datos;
}

// ---------------------------------------------------------------
// [SECCIÓN 3] ENDPOINTS (uno por cada acción de la API)
// ---------------------------------------------------------------
export const api = {
  // Autenticación (api/auth.php)
  sesion:        ()      => pedir('auth.php', 'sesion'),
  login:         (datos) => pedir('auth.php', 'login', { metodo: 'POST', cuerpo: datos }),
  logout:        ()      => pedir('auth.php', 'logout', { metodo: 'POST' }),
  cambiarClave:  (datos) => pedir('auth.php', 'cambiar_clave', { metodo: 'POST', cuerpo: datos }),

  // Gastos (api/gastos.php)
  categorias:    ()      => pedir('gastos.php', 'categorias'),
  listarGastos:  (f)     => pedir('gastos.php', 'listar', { params: f }),
  verGasto:      (id)    => pedir('gastos.php', 'ver', { params: { id } }),
  crearGasto:    (fd)    => pedir('gastos.php', 'crear', { metodo: 'POST', cuerpo: fd }),
  editarGasto:   (id, fd)=> pedir('gastos.php', 'editar', { metodo: 'POST', params: { id }, cuerpo: fd }),
  eliminarGasto: (id)    => pedir('gastos.php', 'eliminar', { metodo: 'POST', params: { id } }),

  // Documentos (api/documentos.php)
  eliminarDocumento: (id) => pedir('documentos.php', 'eliminar', { metodo: 'POST', params: { id } }),

  // Resúmenes (api/resumen.php)
  inicio:        ()          => pedir('resumen.php', 'inicio'),
  carpetas:      ()          => pedir('resumen.php', 'carpetas'),
  resumenMes:    (anio, mes) => pedir('resumen.php', 'mes', { params: { anio, mes } }),

  // Administrador (api/admin.php)
  panel:          ()      => pedir('admin.php', 'panel'),
  usuarios:       ()      => pedir('admin.php', 'usuarios'),
  crearUsuario:   (datos) => pedir('admin.php', 'crear_usuario', { metodo: 'POST', cuerpo: datos }),
  estadoUsuario:  (id, activo) => pedir('admin.php', 'estado_usuario', { metodo: 'POST', cuerpo: { id, activo } }),
  claveUsuario:   (id, clave)  => pedir('admin.php', 'cambiar_clave', { metodo: 'POST', cuerpo: { id, clave } }),
  gastosAdmin:    (f)     => pedir('admin.php', 'gastos', { params: f }),
};

// ---------------------------------------------------------------
// [SECCIÓN 4] URLs DIRECTAS (imágenes, PDF y descargas)
// Se usan en <img src> o enlaces; la cookie de sesión viaja sola.
// ---------------------------------------------------------------
export const urlDocumento = (id, descargar = false) =>
  `${BASE}documentos.php?accion=ver&id=${encodeURIComponent(id)}${descargar ? '&descargar=1' : ''}`;

export function urlExportar(formato, filtros = {}) {
  const p = new URLSearchParams({ formato });
  for (const [k, v] of Object.entries(filtros)) if (v) p.set(k, v);
  return `${BASE}exportar.php?${p}`;
}
