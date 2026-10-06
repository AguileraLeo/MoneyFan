/* =====================================================================
   MoneyFan v2 · sw.js (Service Worker)
   ---------------------------------------------------------------------
   [QUÉ HACE ESTE ARCHIVO]
   Es lo que convierte la web en una PWA instalable:
     - Guarda en caché el "cascarón" de la app (HTML, CSS, JS, íconos)
       para que abra rápido y funcione aunque la señal sea mala.
     - NUNCA guarda en caché la API ni los documentos: los datos y las
       boletas siempre se piden frescos a MySQL / al servidor, y quedan
       protegidos por la sesión.
   Al cambiar cualquier archivo del cascarón, sube la VERSION.
   ===================================================================== */

const VERSION = 'moneyfan-v2.0.0';
const CASCARON = [
  './',
  'index.html',
  'manifest.webmanifest',
  'assets/css/app.css',
  'assets/js/app.js',
  'assets/js/api.js',
  'assets/js/ui.js',
  'assets/js/validaciones.js',
  'assets/js/vistas_usuario.js',
  'assets/js/vistas_admin.js',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
  'assets/icons/favicon.svg',
];

// [SECCIÓN 1] Instalación: descarga y guarda el cascarón
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(CASCARON)).then(() => self.skipWaiting()));
});

// [SECCIÓN 2] Activación: borra cachés de versiones anteriores
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((claves) => Promise.all(claves.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// [SECCIÓN 3] Peticiones
//  - /api/ y otros orígenes: directo a la red (sin caché).
//  - Cascarón: "red primero" para tener siempre la última versión;
//    si no hay conexión, se usa la copia guardada.
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin || url.pathname.includes('/api/')) return;

  e.respondWith(
    fetch(e.request)
      .then((resp) => {
        if (resp.ok) {
          const copia = resp.clone();
          caches.open(VERSION).then((c) => c.put(e.request, copia));
        }
        return resp;
      })
      .catch(() => caches.match(e.request, { ignoreSearch: true })
        .then((r) => r || caches.match('index.html')))
  );
});
