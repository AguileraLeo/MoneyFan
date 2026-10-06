/* =====================================================================
   MoneyFan v2 · assets/js/ui.js
   ---------------------------------------------------------------------
   [QUÉ HACE ESTE ARCHIVO]
   Piezas de interfaz reutilizables por todas las pantallas:
     1. Plantillas HTML SEGURAS (escapan el texto -> evita XSS).
     2. Íconos SVG.
     3. Formatos chilenos de dinero y fecha.
     4. Avisos (toast), confirmaciones y modales.
     5. Errores de formulario junto a cada campo.
     6. Gráficos SVG livianos + tooltip.
     7. Visor de documentos y compresión de fotos antes de subirlas.
   ===================================================================== */

// ---------------------------------------------------------------
// [SECCIÓN 1] PLANTILLAS SEGURAS
// html`<p>${texto}</p>` escapa automáticamente cada valor, así un
// proveedor llamado "<script>" se muestra como texto y no se ejecuta.
// raw() marca HTML de confianza (generado por la propia app).
// ---------------------------------------------------------------
class Crudo { constructor(v) { this.v = v; } toString() { return this.v; } }
export const raw = (v) => new Crudo(v);

export function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function valor(v) {
  if (v instanceof Crudo) return v.v;
  if (Array.isArray(v)) return v.map(valor).join('');
  if (v === false || v === null || v === undefined) return '';
  return esc(v);
}

export function html(partes, ...valores) {
  return raw(partes.reduce((acc, p, i) => acc + p + (i < valores.length ? valor(valores[i]) : ''), ''));
}

export const $ = (sel, raiz = document) => raiz.querySelector(sel);
export const $$ = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];

// ---------------------------------------------------------------
// [SECCIÓN 2] ÍCONOS (SVG de trazo, heredan el color del texto)
// ---------------------------------------------------------------
const P = {
  inicio: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
  carpeta: '<path d="M3 6.5A1.5 1.5 0 0 1 4.5 5H9l2 2.5h8.5A1.5 1.5 0 0 1 21 9v9.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z"/>',
  mas: '<path d="M12 5v14M5 12h14"/>',
  usuario: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  usuarios: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
  escudo: '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
  atras: '<path d="M15 5l-7 7 7 7"/>',
  salir: '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
  camara: '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
  clip: '<path d="M21 11.5l-8.6 8.6a5 5 0 0 1-7.1-7.1l8.6-8.6a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.4-2.4l7.9-7.9"/>',
  boleta: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
  archivo: '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>',
  basura: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
  editar: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
  descargar: '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
  ok: '<path d="m5 12 5 5 9-10"/>',
  alerta: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/>',
  buscar: '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
  derecha: '<path d="m9 5 7 7-7 7"/>',
  cerrar: '<path d="M6 6l12 12M18 6 6 18"/>',
  ojo: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  instalar: '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M12 7v7M9 11l3 3 3-3"/>',
  llave: '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3"/>',
  tabla: '<rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 10h18M3 15h18M9 4v16"/>',
  calendario: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
  // Categorías
  Snacks: '<circle cx="12" cy="12" r="9"/><circle cx="9" cy="9" r="1"/><circle cx="14.5" cy="10" r="1"/><circle cx="10" cy="15" r="1"/><circle cx="15" cy="15" r="1"/>',
  Farmacia: '<rect x="3" y="8.5" width="18" height="7" rx="3.5" transform="rotate(-45 12 12)"/><path d="m9.5 9.5 5 5"/>',
  'Café': '<path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16M8 3v3M12 3v3"/>',
  Transporte: '<rect x="4" y="3" width="16" height="15" rx="2"/><path d="M4 11h16M8 18v3M16 18v3"/>',
  Supermercado: '<path d="M3 4h2l2.4 11h11l2-8H6.2"/><circle cx="9" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/>',
  Servicios: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
  Otros: '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
};

export const icono = (nombre) => raw(
  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[nombre] || P.Otros}</svg>`
);

// ---------------------------------------------------------------
// [SECCIÓN 3] FORMATOS CHILENOS
// ---------------------------------------------------------------
export const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
export const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
export const TIPOS = { boleta: 'Boleta', factura: 'Factura', otro: 'Otro' };

export const pesos = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CL');
export const pesosCorto = (n) => n >= 1_000_000 ? '$' + (n / 1_000_000).toLocaleString('es-CL', { maximumFractionDigits: 1 }) + 'M'
  : n >= 1000 ? '$' + Math.round(n / 1000).toLocaleString('es-CL') + 'k' : pesos(n);
export const capital = (s) => s.charAt(0).toUpperCase() + s.slice(1);
export const correlativo = (n) => 'N° ' + String(n).padStart(4, '0');

const aFecha = (iso) => new Date(iso.slice(0, 10) + 'T00:00:00');
/** '2026-10-05' -> 'lunes 5 de octubre' */
export const fechaLarga = (iso) => { const d = aFecha(iso); return `${DIAS[d.getDay()]} ${d.getDate()} de ${MESES[d.getMonth()]}`; };
/** '2026-10-05' -> '05-10-2026' */
export const fechaCorta = (iso) => iso.slice(0, 10).split('-').reverse().join('-');
export const nombreMes = (m, a) => capital(MESES[m - 1]) + (a ? ' ' + a : '');
export const diaSemana = (iso) => DIAS[aFecha(iso).getDay()];
export const tamano = (b) => b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.ceil(b / 1024) + ' KB';

// ---------------------------------------------------------------
// [SECCIÓN 4] AVISOS, CONFIRMACIONES Y MODALES
// ---------------------------------------------------------------
export function avisar(mensaje, tipo = 'ok') {
  const caja = $('#avisos');
  const el = document.createElement('div');
  el.className = `aviso ${tipo}`;
  el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
  el.innerHTML = html`${icono(tipo === 'error' ? 'alerta' : 'ok')}<span>${mensaje}</span>`;
  caja.append(el);
  setTimeout(() => el.remove(), tipo === 'error' ? 5000 : 3000);
}

/** Abre un modal con el HTML dado. Devuelve el elemento y una función cerrar(). */
export function abrirModal(contenido) {
  const fondo = document.createElement('div');
  fondo.className = 'modal-fondo';
  fondo.innerHTML = html`<div class="modal" role="dialog" aria-modal="true">${contenido}</div>`;
  const anterior = document.activeElement;
  const cerrar = () => { fondo.remove(); document.removeEventListener('keydown', alTeclear); anterior?.focus?.(); };
  const alTeclear = (e) => { if (e.key === 'Escape') cerrar(); };
  fondo.addEventListener('click', (e) => { if (e.target === fondo || e.target.closest('[data-cerrar]')) cerrar(); });
  document.addEventListener('keydown', alTeclear);
  document.body.append(fondo);
  setTimeout(() => $('input, button.btn', fondo)?.focus(), 30);
  return { el: fondo, cerrar };
}

/** Pregunta "¿Seguro?" y resuelve true/false. */
export function confirmar(titulo, texto, textoBoton = 'Eliminar') {
  return new Promise((resolver) => {
    const { el, cerrar } = abrirModal(html`
      <h2>${titulo}</h2><p>${texto}</p>
      <div class="botones">
        <button class="btn secundario" data-r="no">Cancelar</button>
        <button class="btn peligro" data-r="si">${textoBoton}</button>
      </div>`);
    el.addEventListener('click', (e) => {
      const b = e.target.closest('[data-r]');
      if (b) { cerrar(); resolver(b.dataset.r === 'si'); }
    });
  });
}

// ---------------------------------------------------------------
// [SECCIÓN 5] ERRORES DE FORMULARIO
// Cada input está dentro de <div class="campo" data-campo="nombre">.
// Se pinta el mensaje bajo el campo y se enfoca el primero con error.
// ---------------------------------------------------------------
export function mostrarErrores(form, errores) {
  limpiarErrores(form);
  let primero = null;
  for (const [campo, mensaje] of Object.entries(errores)) {
    if (!mensaje) continue;
    const caja = $(`[data-campo="${campo}"]`, form);
    if (!caja) continue;
    caja.classList.add('con-error');
    $('.error', caja).textContent = mensaje;
    primero ??= $('input, select, textarea', caja);
  }
  primero?.focus();
  return !primero;
}

export function limpiarErrores(form) {
  $$('.con-error', form).forEach((c) => c.classList.remove('con-error'));
  $$('.campo .error', form).forEach((e) => { e.textContent = ''; });
}

// Al corregir un campo, su mensaje de error desaparece
for (const evento of ['input', 'change']) {
  document.addEventListener(evento, (e) => {
    const caja = e.target.closest?.('.campo.con-error');
    if (caja) { caja.classList.remove('con-error'); $('.error', caja).textContent = ''; }
  });
}

/** Bloquea el botón mientras se envía (evita doble registro). */
export async function conBoton(boton, tarea) {
  const texto = boton.innerHTML;
  boton.disabled = true;
  boton.textContent = 'Guardando…';
  try { return await tarea(); } finally { boton.disabled = false; boton.innerHTML = texto; }
}

// ---------------------------------------------------------------
// [SECCIÓN 6] GRÁFICOS
// Una sola serie por gráfico -> un solo tono (sin leyenda; el título
// dice qué se grafica). Barras de máx. 24px, extremo redondeado de
// 4px y base recta, grilla tenue de 1px, y solo se rotula el valor
// mayor; el resto aparece en el tooltip al pasar el dedo o el mouse.
// ---------------------------------------------------------------
function techo(max) {
  if (max <= 0) return 1000;
  const p = 10 ** Math.floor(Math.log10(max));
  for (const m of [1, 2, 2.5, 5, 10]) if (m * p >= max) return m * p;
  return 10 * p;
}

/** Barra con extremo superior redondeado y base recta. */
function barraRedondeada(x, y, w, h, r = 4) {
  r = Math.min(r, w / 2, h);
  return `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + w - r}Q${x + w},${y} ${x + w},${y + r}V${y + h}Z`;
}

/**
 * Gráfico de columnas. datos = [{ etiqueta, valor, tip }]
 * opciones.marcar = índice a destacar (ej. hoy); el resto en tono pleno.
 */
export function graficoColumnas(datos, { alto = 170, cadaEtiqueta = 1, titulo = '' } = {}) {
  const W = 320, izq = 34, der = 6, arriba = 18, abajo = 22;
  const anchoUtil = W - izq - der, altoUtil = alto - arriba - abajo;
  const max = techo(Math.max(...datos.map((d) => d.valor), 0));
  const slot = anchoUtil / Math.max(datos.length, 1);
  const ancho = Math.min(24, Math.max(3, slot * 0.62));
  const iMax = datos.reduce((im, d, i) => (d.valor > datos[im].valor ? i : im), 0);

  let s = '';
  for (const f of [0, 0.5, 1]) {
    const y = arriba + altoUtil * (1 - f);
    s += `<line class="grilla" x1="${izq}" x2="${W - der}" y1="${y}" y2="${y}"/>`;
    s += `<text class="eje" x="${izq - 6}" y="${y + 3.5}" text-anchor="end">${esc(pesosCorto(max * f))}</text>`;
  }
  datos.forEach((d, i) => {
    const cx = izq + slot * i + slot / 2;
    const h = d.valor > 0 ? Math.max(2, (d.valor / max) * altoUtil) : 0;
    const y = arriba + altoUtil - h;
    s += `<rect class="zona" x="${cx - slot / 2}" y="${arriba}" width="${slot}" height="${altoUtil}" data-tip="${esc(d.tip)}" tabindex="0"/>`;
    if (h) s += `<path class="barra-dato" d="${barraRedondeada(cx - ancho / 2, y, ancho, h)}"/>`;
    if (i % cadaEtiqueta === 0 || i === datos.length - 1) {
      s += `<text class="eje" x="${cx}" y="${alto - 6}" text-anchor="middle">${esc(d.etiqueta)}</text>`;
    }
    if (i === iMax && d.valor > 0) {
      s += `<text class="valor" x="${cx}" y="${y - 5}" text-anchor="middle">${esc(pesos(d.valor))}</text>`;
    }
  });
  return raw(`<div class="grafico"><svg viewBox="0 0 ${W} ${alto}" role="img" aria-label="${esc(titulo)}">${s}</svg></div>`);
}

/** Barras horizontales rotuladas: items = [{ nombre, valor, color, tip }] */
export function barrasHorizontales(items) {
  const max = Math.max(...items.map((i) => i.valor), 1);
  return html`<div class="barras-h">${items.map((i) => html`
    <div class="fila" data-tip="${i.tip || ''}">
      <span class="nombre"><span class="punto" style="--c:${i.color || ''}"></span><span>${i.nombre}</span></span>
      <span class="pista"><span class="relleno" style="--p:${Math.max(1, (i.valor / max) * 100).toFixed(1)}"></span></span>
      <span class="cifra num">${pesos(i.valor)}</span>
    </div>`)}</div>`;
}

// Tooltip compartido: funciona con mouse, teclado y toque
let tip = null;
function mostrarTip(el) {
  const texto = el.dataset.tip;
  if (!texto) return;
  tip ??= Object.assign(document.createElement('div'), { className: 'tooltip', role: 'tooltip' });
  tip.textContent = texto;
  document.body.append(tip);
  const r = el.getBoundingClientRect();
  const x = Math.min(Math.max(r.left + r.width / 2, 70), innerWidth - 70);
  tip.style.left = x + 'px';
  tip.style.top = Math.max(r.top, 40) + 'px';
  $$('.zona.foco').forEach((z) => z.classList.remove('foco'));
  el.classList.add('foco');
}
function ocultarTip() { tip?.remove(); $$('.zona.foco').forEach((z) => z.classList.remove('foco')); }
document.addEventListener('pointerover', (e) => { const el = e.target.closest?.('[data-tip]'); el ? mostrarTip(el) : ocultarTip(); });
document.addEventListener('focusin', (e) => { const el = e.target.closest?.('[data-tip]'); el ? mostrarTip(el) : ocultarTip(); });
document.addEventListener('scroll', ocultarTip, { passive: true });

// ---------------------------------------------------------------
// [SECCIÓN 7] VISOR DE DOCUMENTOS
// Imagen a pantalla completa o PDF incrustado, con descarga.
// ---------------------------------------------------------------
export function abrirVisor({ url, urlDescarga, nombre, esImagen }) {
  const v = document.createElement('div');
  v.className = 'visor';
  v.setAttribute('role', 'dialog');
  v.innerHTML = html`
    <header>
      <button class="btn-icono" data-cerrar aria-label="Cerrar">${icono('cerrar')}</button>
      <span class="nombre">${nombre}</span>
      ${urlDescarga ? html`<a class="btn-icono" href="${urlDescarga}" aria-label="Descargar">${icono('descargar')}</a>` : ''}
    </header>
    <div class="contenido">
      ${esImagen ? html`<img src="${url}" alt="${nombre}">` : html`<iframe src="${url}" title="${nombre}"></iframe>`}
    </div>`;
  const cerrar = () => { v.remove(); document.removeEventListener('keydown', tecla); };
  const tecla = (e) => { if (e.key === 'Escape') cerrar(); };
  v.addEventListener('click', (e) => { if (e.target.closest('[data-cerrar]')) cerrar(); });
  document.addEventListener('keydown', tecla);
  document.body.append(v);
}

// ---------------------------------------------------------------
// [SECCIÓN 8] COMPRESIÓN DE FOTOS
// Las fotos del celular pesan 3-8 MB. Antes de subirlas se reducen a
// máx. 1600 px y JPEG 85%: carga más rápida y menos espacio en el
// hosting. Los PDF y las imágenes pequeñas se envían tal cual.
// ---------------------------------------------------------------
export async function comprimirImagen(archivo, lado = 1600) {
  if (!archivo.type.startsWith('image/') || !('createImageBitmap' in window)) return archivo;
  try {
    const bmp = await createImageBitmap(archivo);
    const escala = Math.min(1, lado / Math.max(bmp.width, bmp.height));
    if (escala === 1 && archivo.size < 1.5 * 1024 * 1024) return archivo;
    const c = document.createElement('canvas');
    c.width = Math.round(bmp.width * escala);
    c.height = Math.round(bmp.height * escala);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.85));
    if (!blob || blob.size >= archivo.size) return archivo;
    return new File([blob], archivo.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
  } catch {
    return archivo;
  }
}
