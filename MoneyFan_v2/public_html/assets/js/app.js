/* =====================================================================
   MoneyFan v2 · assets/js/app.js
   ---------------------------------------------------------------------
   [QUÉ HACE ESTE ARCHIVO]
   Punto de entrada del Frontend (Single Page App):
     1. Consulta a la API si ya hay sesión iniciada.
     2. Muestra el login o la app según corresponda.
     3. ROUTER: según el #hash de la URL dibuja la pantalla pedida
        (#/inicio, #/nuevo, #/historial/2026/10 ...).
     4. Barra superior, navegación inferior y botón "atrás".
     5. Registra el Service Worker (PWA) y el aviso de instalación.
   ===================================================================== */

import { api, fijarCsrf, alExpirarSesion } from './api.js';
import { html, $, $$, icono, avisar } from './ui.js';
import * as vu from './vistas_usuario.js';
import * as va from './vistas_admin.js';

// ---------------------------------------------------------------
// [SECCIÓN 1] ESTADO GLOBAL
// ---------------------------------------------------------------
const estado = { usuario: null, categorias: [], eventoInstalar: null };
const raizApp = $('#app');
const raizLogin = $('#login');
const vista = $('#vista');

// ---------------------------------------------------------------
// [SECCIÓN 2] RUTAS
// Cada ruta es una expresión regular + la función que dibuja la
// pantalla. Los grupos (\d+) se entregan como parámetros.
// ---------------------------------------------------------------
const RUTAS = [
  [/^\/inicio$/, vu.inicio],
  [/^\/nuevo$/, vu.formulario],
  [/^\/editar\/(\d+)$/, vu.formulario],
  [/^\/gasto\/(\d+)$/, vu.detalle],
  [/^\/historial$/, vu.historial],
  [/^\/historial\/(\d{4})\/(\d{1,2})$/, vu.mes],
  [/^\/historial\/(\d{4})\/(\d{1,2})\/(\d{1,2})$/, vu.dia],
  [/^\/perfil$/, vu.perfil],
  [/^\/admin$/, va.panel, true],
  [/^\/admin\/usuarios$/, va.usuarios, true],
  [/^\/admin\/gastos$/, va.gastos, true],
];

/** Contexto que reciben todas las vistas. */
const ctx = {
  get usuario() { return estado.usuario; },
  get categorias() { return estado.categorias; },
  query: new URLSearchParams(),
  ir(hash, { reemplazar = false } = {}) {
    if (reemplazar) { history.replaceState(null, '', hash); dibujar(); } else location.hash = hash;
  },
  recargar: () => dibujar(),
  salir,
  puedeInstalar: () => Boolean(estado.eventoInstalar),
  instalar,
};

// ---------------------------------------------------------------
// [SECCIÓN 3] DIBUJAR LA PANTALLA ACTUAL
// Muestra un indicador de carga, llama a la vista (que consulta la
// API) y pinta el resultado. Si la API falla, muestra el error.
// ---------------------------------------------------------------
let turno = 0;
async function dibujar() {
  if (!estado.usuario) return;
  const [ruta, consulta = ''] = (location.hash.slice(1) || '/inicio').split('?');
  ctx.query = new URLSearchParams(consulta);

  const encontrada = RUTAS.find(([re]) => re.test(ruta));
  if (!encontrada || (encontrada[2] && estado.usuario.rol !== 'admin')) {
    history.replaceState(null, '', '#/inicio');
    return dibujar();
  }
  const [re, fn] = encontrada;
  const params = ruta.match(re).slice(1);
  const mio = ++turno;

  marcarNavegacion(ruta);
  vista.innerHTML = '<div class="cargando" aria-label="Cargando"></div>';
  try {
    const r = await fn(params, ctx);
    if (mio !== turno) return;            // el usuario ya navegó a otra pantalla
    $('#titulo').textContent = r.titulo;
    document.title = `${r.titulo} · MoneyFan`;
    $('#btn-atras').classList.toggle('oculto', !r.atras);
    $('#marca').classList.toggle('oculto', Boolean(r.atras));
    vista.classList.toggle('ancho', Boolean(r.ancho));
    vista.innerHTML = r.contenido;
    r.montar?.(vista);
    scrollTo(0, 0);
  } catch (err) {
    if (mio !== turno || err.estado === 401) return;
    vista.innerHTML = html`
      <div class="tarjeta vacio">${icono('alerta')}<p>${err.message}</p>
        <p style="margin-top:12px"><a class="btn secundario" href="#/inicio">Volver al inicio</a></p></div>`;
  }
}

function marcarNavegacion(ruta) {
  $$('#nav a').forEach((a) => {
    const destino = a.getAttribute('href').slice(1);
    a.classList.toggle('activo', ruta === destino || (destino !== '/inicio' && ruta.startsWith(destino + '/')));
  });
}

// ---------------------------------------------------------------
// [SECCIÓN 4] NAVEGACIÓN INFERIOR
// La opción "Admin" solo aparece si el rol es administrador.
// ---------------------------------------------------------------
function dibujarNavegacion() {
  const esAdmin = estado.usuario.rol === 'admin';
  $('#nav').innerHTML = html`
    <a href="#/inicio">${icono('inicio')}<span>Inicio</span></a>
    <a href="#/historial">${icono('carpeta')}<span>Historial</span></a>
    <a href="#/nuevo" class="nav-nuevo" aria-label="Registrar gasto"><span class="circulo">${icono('mas')}</span></a>
    ${esAdmin ? html`<a href="#/admin">${icono('escudo')}<span>Admin</span></a>` : ''}
    <a href="#/perfil">${icono('usuario')}<span>Perfil</span></a>`;
}

// ---------------------------------------------------------------
// [SECCIÓN 5] SESIÓN: entrar, salir y expiración
// ---------------------------------------------------------------
async function entrar({ usuario, csrf }) {
  estado.usuario = usuario;
  fijarCsrf(csrf);
  estado.categorias = (await api.categorias()).categorias;
  raizLogin.classList.add('oculto');
  raizLogin.innerHTML = '';
  raizApp.classList.remove('oculto');
  dibujarNavegacion();
  if (!location.hash || location.hash === '#/login') history.replaceState(null, '', '#/inicio');
  dibujar();
}

function mostrarLogin() {
  estado.usuario = null;
  fijarCsrf(null);
  raizApp.classList.add('oculto');
  raizLogin.classList.remove('oculto');
  document.title = 'Ingresar · MoneyFan';
  vu.dibujarLogin(raizLogin, entrar);
}

async function salir() {
  try { await api.logout(); } catch { /* si falla igual se sale */ }
  history.replaceState(null, '', '#/inicio');
  mostrarLogin();
}

alExpirarSesion((mensaje) => {
  if (!estado.usuario) return;
  avisar(mensaje, 'error');
  mostrarLogin();
});

// ---------------------------------------------------------------
// [SECCIÓN 6] PWA: Service Worker, instalación y modo sin conexión
// ---------------------------------------------------------------
if ('serviceWorker' in navigator) {
  addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));
}

addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();                 // se muestra desde el botón de Perfil
  estado.eventoInstalar = e;
  const b = $('#btn-instalar');
  if (b) b.disabled = false;
});

async function instalar() {
  if (!estado.eventoInstalar) return;
  estado.eventoInstalar.prompt();
  const { outcome } = await estado.eventoInstalar.userChoice;
  estado.eventoInstalar = null;
  if (outcome === 'accepted') avisar('MoneyFan se instaló en tu dispositivo');
}

function indicarConexion() {
  $('#offline').classList.toggle('oculto', navigator.onLine);
}
addEventListener('online', indicarConexion);
addEventListener('offline', indicarConexion);

// ---------------------------------------------------------------
// [SECCIÓN 7] ARRANQUE
// ---------------------------------------------------------------
addEventListener('hashchange', dibujar);
$('#btn-atras').addEventListener('click', () => (history.length > 1 ? history.back() : ctx.ir('#/inicio')));
indicarConexion();

(async () => {
  try {
    const r = await api.sesion();
    r.usuario ? await entrar(r) : mostrarLogin();
  } catch (err) {
    raizLogin.classList.remove('oculto');
    raizLogin.innerHTML = html`<div class="login"><div class="tarjeta vacio">${icono('alerta')}
      <p>${err.message}</p><p style="margin-top:12px"><button class="btn" id="reintentar">Reintentar</button></p></div></div>`;
    $('#reintentar').addEventListener('click', () => location.reload());
  }
})();
