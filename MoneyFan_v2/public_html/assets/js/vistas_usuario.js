/* =====================================================================
   MoneyFan v2 · assets/js/vistas_usuario.js
   ---------------------------------------------------------------------
   [QUÉ HACE ESTE ARCHIVO]
   Las PANTALLAS que usa cualquier persona (Usuaria y Admin):
     1. Login (RUT + contraseña)
     2. Inicio (recuento diario / semanal / mensual + gráficos)
     3. Registrar / editar gasto (con captura y carga de documentos)
     4. Detalle del gasto (visualización de documentos)
     5. Historial en carpetas Año > Mes > Día + resumen mensual
     6. Perfil (cambiar contraseña, instalar la app, salir)
   Cada vista devuelve { titulo, contenido, montar } y app.js la dibuja.
   ===================================================================== */

import { api, urlDocumento, ErrorApi } from './api.js';
import {
  html, raw, $, $$, icono, pesos, fechaLarga, fechaCorta, nombreMes, diaSemana, correlativo,
  capital, tamano, TIPOS, MESES, avisar, confirmar, mostrarErrores, limpiarErrores, conBoton,
  graficoColumnas, barrasHorizontales, abrirVisor, comprimirImagen,
} from './ui.js';
import {
  REGLAS, validarRut, validarClave, validarMonto, validarTexto, validarFecha,
  validarNumeroDocumento, validarArchivo, filtrarDigitos, filtrarRut, formatearRut, hoyISO,
} from './validaciones.js';

// ---------------------------------------------------------------
// [SECCIÓN 0] PIEZAS COMPARTIDAS
// ---------------------------------------------------------------
/** Una fila de gasto (se usa en inicio, historial y admin). */
export function filaGasto(g, { mostrarUsuario = false } = {}) {
  return html`
    <li><a class="item" href="#/gasto/${g.id}">
      <span class="icono-cat" style="--c:${g.categoria_color}">${icono(g.categoria)}</span>
      <span class="cuerpo">
        <span class="titulo">${g.proveedor}</span>
        <span class="sub">${mostrarUsuario ? g.usuario_nombre + ' · ' : ''}${fechaCorta(g.fecha)} · ${g.descripcion}</span>
      </span>
      <span class="num" style="text-align:right">
        <span class="monto">${pesos(g.monto)}</span><br>
        ${g.documentos ? html`<span class="chip" title="Documentos adjuntos">${icono('clip')}${g.documentos}</span>`
                       : html`<span class="chip">${TIPOS[g.tipo_documento]}</span>`}
      </span>
    </a></li>`;
}

export const vacio = (texto, ic = 'boleta') => html`<div class="vacio">${icono(ic)}<p>${texto}</p></div>`;

/** Campo de formulario con su etiqueta y su espacio para el error. */
const campo = (nombre, etiqueta, control, ayuda = '') => html`
  <div class="campo" data-campo="${nombre}">
    <label for="f-${nombre}">${etiqueta}</label>
    ${control}
    ${ayuda ? html`<span class="ayuda">${ayuda}</span>` : ''}
    <span class="error" aria-live="polite"></span>
  </div>`;

// ===============================================================
// [SECCIÓN 1] LOGIN
// Valida en el navegador (formato de RUT, campos llenos) y luego
// la API verifica contra MySQL. Los errores salen bajo cada campo.
// ===============================================================
export function dibujarLogin(raiz, alIngresar) {
  raiz.innerHTML = html`
    <div class="login">
      <div class="marca">
        <img src="assets/icons/icon-192.png" alt="">
        <h1>MoneyFan</h1>
        <p>Tus gastos y boletas, ordenados</p>
      </div>
      <form class="tarjeta" novalidate>
        <h2 style="margin-bottom:16px">Ingresar</h2>
        ${campo('rut', 'RUT', raw('<input class="input" id="f-rut" name="rut" inputmode="text" autocomplete="username" placeholder="12.345.678-9" maxlength="12">'))}
        ${campo('clave', 'Contraseña', html`<div class="prefijo">
            <input class="input" id="f-clave" name="clave" type="password" autocomplete="current-password" maxlength="72" style="padding-left:12px">
            <button type="button" class="btn-icono ver-clave" aria-label="Mostrar contraseña">${icono('ojo')}</button>
          </div>`)}
        <button class="btn bloque" type="submit">Ingresar</button>
      </form>
      <p class="demo">Cuentas de ejemplo · Admin: 11.111.111-1 / admin123 · Usuaria: 12.345.678-9 / 123456</p>
    </div>`;

  const form = $('form', raiz);
  filtrarRut(form.rut);
  $('.ver-clave', form).addEventListener('click', () => {
    form.clave.type = form.clave.type === 'password' ? 'text' : 'password';
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    form.rut.value = formatearRut(form.rut.value);
    const errores = { rut: validarRut(form.rut.value), clave: form.clave.value ? '' : 'Ingresa la contraseña' };
    if (!mostrarErrores(form, errores)) return;
    const boton = $('button[type=submit]', form);
    try {
      const r = await conBoton(boton, () => api.login({ rut: form.rut.value, clave: form.clave.value }));
      alIngresar(r);
    } catch (err) {
      if (Object.keys(err.campos || {}).length) mostrarErrores(form, err.campos);
      else mostrarErrores(form, { clave: err.message });
    }
  });
  setTimeout(() => form.rut.focus(), 50);
}

// ===============================================================
// [SECCIÓN 2] INICIO
// Recuento diario, semanal y mensual (como la v1) desde MySQL,
// gráfico de los últimos 7 días y gasto del mes por categoría.
// ===============================================================
export async function inicio(_p, ctx) {
  const r = await api.inicio();
  const hora = new Date().getHours();
  const saludo = hora < 12 ? 'Buenos días' : hora < 20 ? 'Buenas tardes' : 'Buenas noches';

  const serie = r.serie_7_dias.map((d, i) => ({
    etiqueta: i === 6 ? 'Hoy' : capital(diaSemana(d.fecha)).slice(0, 3),
    valor: d.total,
    tip: `${capital(fechaLarga(d.fecha))}: ${pesos(d.total)}`,
  }));
  const cats = r.categorias_mes.map((c) => ({
    nombre: c.categoria, valor: c.total, color: c.color,
    tip: `${c.categoria}: ${pesos(c.total)} en ${c.cantidad} gasto(s)`,
  }));

  return {
    titulo: 'MoneyFan',
    logo: true,
    contenido: html`
      <div class="pila">
        <p class="suave">${saludo}, <strong>${ctx.usuario.nombre.split(' ')[0]}</strong></p>

        <section class="tarjeta heroe">
          <div class="etiqueta">Gastado hoy · ${fechaLarga(r.hoy.desde)}</div>
          <div class="cifra num">${pesos(r.hoy.total)}</div>
          <div class="etiqueta">${r.hoy.cantidad} registro(s)</div>
        </section>

        <div class="cuadros">
          <div class="cuadro"><div class="etiqueta">Esta semana</div>
            <div class="cifra num">${pesos(r.semana.total)}</div>
            <div class="detalle">${r.semana.cantidad} gastos · desde el lunes</div></div>
          <div class="cuadro"><div class="etiqueta">Este mes</div>
            <div class="cifra num">${pesos(r.mes.total)}</div>
            <div class="detalle">${r.mes.cantidad} gastos · ${MESES[new Date().getMonth()]}</div></div>
        </div>

        <a class="btn bloque" href="#/nuevo">${icono('camara')} Registrar gasto con boleta</a>

        <section class="tarjeta">
          <h2>Últimos 7 días</h2>
          ${graficoColumnas(serie, { titulo: 'Gasto diario de los últimos 7 días' })}
        </section>

        <section class="tarjeta">
          <h2>Este mes por categoría</h2>
          ${cats.length ? barrasHorizontales(cats) : vacio('Aún no hay gastos este mes')}
        </section>

        <section class="tarjeta">
          <div class="cabeza"><h2>Últimos gastos</h2><a href="#/historial" class="chico">Ver historial</a></div>
          ${r.ultimos.length ? html`<ul class="lista">${r.ultimos.map((g) => filaGasto(g))}</ul>`
                             : vacio('Registra tu primer gasto con el botón +')}
        </section>
      </div>`,
  };
}

// ===============================================================
// [SECCIÓN 3] REGISTRAR / EDITAR GASTO
// - Tipo (boleta/factura/otro), proveedor, monto, descripción,
//   fecha, categoría y N° de documento opcional.
// - Documentos: "Tomar foto" abre la cámara del celular; "Subir
//   archivo" permite elegir imágenes o PDF. Se muestran miniaturas.
// - El correlativo lo asigna el servidor de forma automática.
// ===============================================================
export async function formulario([id], ctx) {
  const editando = Boolean(id);
  const g = editando ? (await api.verGasto(id)).gasto : null;
  if (g && !g.es_mio) throw new ErrorApi('Solo puedes editar tus propios gastos', 403);

  const v = g || { tipo_documento: 'boleta', fecha: hoyISO(), categoria_id: '', proveedor: '', monto: '', descripcion: '', numero_documento: '' };
  const existentes = g ? g.documentos_lista : [];
  let nuevos = [];   // archivos elegidos aún no subidos

  const contenido = html`
    <form class="pila" novalidate>
      <section class="tarjeta">
        <div class="cabeza">
          <h2>${editando ? 'Datos del gasto' : 'Nuevo gasto'}</h2>
          <span class="chip">${editando ? correlativo(g.correlativo) : 'Correlativo automático'}</span>
        </div>

        <div class="campo" data-campo="tipo_documento">
          <span class="label">Tipo de documento</span>
          <div class="segmentos" role="radiogroup">
            ${Object.entries(TIPOS).map(([k, t]) => html`
              <label><input type="radio" name="tipo_documento" value="${k}" ${raw(v.tipo_documento === k ? 'checked' : '')}><span>${t}</span></label>`)}
          </div>
          <span class="error"></span>
        </div>

        ${campo('proveedor', 'Proveedor o comercio', html`<input class="input" id="f-proveedor" name="proveedor" maxlength="60" autocomplete="organization" placeholder="Ej: Farmacia Salud+" value="${v.proveedor}">`)}

        <div class="fila-campos">
          ${campo('monto', 'Monto', html`<div class="prefijo"><span>$</span><input class="input num" id="f-monto" name="monto" inputmode="numeric" maxlength="8" placeholder="0" value="${v.monto}"></div>`)}
          ${campo('fecha', 'Fecha', html`<input class="input" id="f-fecha" name="fecha" type="date" max="${hoyISO()}" min="2000-01-01" value="${v.fecha}">`)}
        </div>

        ${campo('descripcion', 'Descripción', html`<textarea class="input" id="f-descripcion" name="descripcion" maxlength="160" placeholder="¿Qué compraste?">${v.descripcion}</textarea>`)}

        <div class="campo" data-campo="categoria_id">
          <span class="label">Categoría</span>
          <div class="segmentos cats" role="radiogroup">
            ${ctx.categorias.map((c) => html`
              <label><input type="radio" name="categoria_id" value="${c.id}" ${raw(Number(v.categoria_id) === c.id ? 'checked' : '')}>
                <span class="punto" style="--c:${c.color}"></span><span>${c.nombre}</span></label>`)}
          </div>
          <span class="error"></span>
        </div>

        ${campo('numero_documento', raw('N° de boleta / factura <span class="opcional">(opcional)</span>'),
          html`<input class="input" id="f-numero_documento" name="numero_documento" maxlength="30" placeholder="Ej: 220956" value="${v.numero_documento || ''}">`)}
      </section>

      <section class="tarjeta">
        <div class="cabeza"><h2>Documentos de respaldo</h2><span class="chico suave" id="contador-docs"></span></div>
        <div class="captura">
          <label class="btn secundario">${icono('camara')} Tomar foto
            <input type="file" id="in-camara" accept="image/*" capture="environment"></label>
          <label class="btn secundario">${icono('clip')} Subir archivo
            <input type="file" id="in-archivo" accept="image/jpeg,image/png,image/webp,application/pdf" multiple></label>
        </div>
        <div class="campo" data-campo="documentos" style="margin:0"><span class="error"></span></div>
        <div class="miniaturas" id="miniaturas"></div>
        <p class="ayuda chico suave" style="margin-top:8px">JPG, PNG, WEBP o PDF · máx. ${REGLAS.docMaxMB} MB c/u · hasta ${REGLAS.docMaxPorGasto} por gasto.
          Se guardan como <span class="num">0001_AAAA-MM-DD_proveedor</span> en carpetas por fecha.</p>
      </section>

      <div class="botones">
        <a class="btn secundario" href="${editando ? '#/gasto/' + g.id : '#/inicio'}">Cancelar</a>
        <button class="btn" type="submit">${icono('ok')} ${editando ? 'Guardar cambios' : 'Registrar gasto'}</button>
      </div>
    </form>`;

  function montar(raiz) {
    const form = $('form', raiz);
    const zona = $('#miniaturas', raiz);
    filtrarDigitos(form.monto);

    // --- Miniaturas: documentos ya guardados + nuevos por subir
    function pintarMiniaturas() {
      const total = existentes.length + nuevos.length;
      $('#contador-docs', raiz).textContent = `${total}/${REGLAS.docMaxPorGasto}`;
      zona.innerHTML = html`
        ${existentes.map((d) => html`
          <div class="mini" data-ver-existente="${d.id}" role="button" tabindex="0" aria-label="Ver ${d.nombre_archivo}">
            ${d.es_imagen ? html`<img src="${urlDocumento(d.id)}" alt="" loading="lazy">`
                          : html`<span class="pdf">${icono('archivo')}${d.nombre_archivo}</span>`}
            <button type="button" class="quitar" data-borrar-existente="${d.id}" aria-label="Eliminar documento">${icono('basura')}</button>
          </div>`)}
        ${nuevos.map((n, i) => html`
          <div class="mini">
            ${n.url ? html`<img src="${n.url}" alt="">` : html`<span class="pdf">${icono('archivo')}${n.archivo.name}</span>`}
            <span class="chip nuevo">Nuevo</span>
            <button type="button" class="quitar" data-quitar="${i}" aria-label="Quitar">${icono('cerrar')}</button>
          </div>`)}`;
    }

    async function agregar(lista) {
      limpiarErrores($('[data-campo=documentos]', raiz).parentElement);
      for (const original of lista) {
        if (existentes.length + nuevos.length >= REGLAS.docMaxPorGasto) {
          mostrarErrores(form, { documentos: `Máximo ${REGLAS.docMaxPorGasto} documentos por gasto` });
          break;
        }
        const archivo = await comprimirImagen(original);
        const error = validarArchivo(archivo);
        if (error) { mostrarErrores(form, { documentos: error }); continue; }
        nuevos.push({ archivo, url: archivo.type.startsWith('image/') ? URL.createObjectURL(archivo) : null });
      }
      pintarMiniaturas();
    }

    for (const id of ['#in-camara', '#in-archivo']) {
      $(id, raiz).addEventListener('change', async (e) => { await agregar([...e.target.files]); e.target.value = ''; });
    }

    zona.addEventListener('click', async (e) => {
      const quitar = e.target.closest('[data-quitar]');
      const borrar = e.target.closest('[data-borrar-existente]');
      const ver = e.target.closest('[data-ver-existente]');
      if (quitar) {
        const [n] = nuevos.splice(Number(quitar.dataset.quitar), 1);
        if (n.url) URL.revokeObjectURL(n.url);
        pintarMiniaturas();
      } else if (borrar) {
        if (!await confirmar('¿Eliminar documento?', 'Se borrará el archivo del servidor. Esta acción no se puede deshacer.')) return;
        try {
          await api.eliminarDocumento(borrar.dataset.borrarExistente);
          existentes.splice(existentes.findIndex((d) => d.id === Number(borrar.dataset.borrarExistente)), 1);
          pintarMiniaturas();
          avisar('Documento eliminado');
        } catch (err) { avisar(err.message, 'error'); }
      } else if (ver) {
        const d = existentes.find((x) => x.id === Number(ver.dataset.verExistente));
        abrirVisor({ url: urlDocumento(d.id), urlDescarga: urlDocumento(d.id, true), nombre: d.nombre_archivo, esImagen: d.es_imagen });
      }
    });
    pintarMiniaturas();

    // --- Envío: validación local -> FormData multipart -> API
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const datos = Object.fromEntries(new FormData(form));
      const errores = {
        tipo_documento: datos.tipo_documento ? '' : 'Elige el tipo de documento',
        proveedor: validarTexto(datos.proveedor, REGLAS.proveedor, 'Ingresa el proveedor'),
        monto: validarMonto(datos.monto),
        fecha: validarFecha(datos.fecha),
        descripcion: validarTexto(datos.descripcion, REGLAS.descripcion, 'Completa la descripción'),
        categoria_id: datos.categoria_id ? '' : 'Selecciona una categoría',
        numero_documento: validarNumeroDocumento(datos.numero_documento),
      };
      if (!mostrarErrores(form, errores)) { avisar('Revisa los campos marcados', 'error'); return; }

      const fd = new FormData();
      for (const k of ['tipo_documento', 'proveedor', 'monto', 'fecha', 'descripcion', 'categoria_id', 'numero_documento']) {
        fd.append(k, (datos[k] || '').trim());
      }
      nuevos.forEach((n) => fd.append('documentos[]', n.archivo, n.archivo.name));

      try {
        const r = await conBoton($('button[type=submit]', form),
          () => (editando ? api.editarGasto(g.id, fd) : api.crearGasto(fd)));
        nuevos.forEach((n) => n.url && URL.revokeObjectURL(n.url));
        avisar(r.mensaje);
        ctx.ir(`#/gasto/${r.gasto.id}`, { reemplazar: true });
      } catch (err) {
        if (Object.keys(err.campos || {}).length) mostrarErrores(form, err.campos);
        avisar(err.message, 'error');
      }
    });
  }

  return { titulo: editando ? `Editar ${correlativo(g.correlativo)}` : 'Registrar gasto', atras: true, contenido, montar };
}

// ===============================================================
// [SECCIÓN 4] DETALLE DEL GASTO + DOCUMENTOS
// Cada documento se pide a api/documentos.php (que revisa permisos)
// y se abre en el visor: imagen a pantalla completa o PDF.
// ===============================================================
export async function detalle([id], ctx) {
  const { gasto: g } = await api.verGasto(id);
  const filaDato = (k, val) => html`<li class="item" style="cursor:default"><span class="cuerpo suave">${k}</span><span class="monto" style="font-weight:560">${val}</span></li>`;

  const contenido = html`
    <div class="pila">
      <section class="tarjeta">
        <div class="cabeza">
          <span class="chip">${icono('boleta')} ${TIPOS[g.tipo_documento]} ${g.numero_documento ? '· ' + g.numero_documento : ''}</span>
          <span class="chip">${correlativo(g.correlativo)}</span>
        </div>
        <h1>${g.proveedor}</h1>
        <p class="cifra num" style="font-size:2rem;font-weight:700;margin:6px 0">${pesos(g.monto)}</p>
        <p class="suave">${g.descripcion}</p>
        <ul class="lista" style="margin-top:12px">
          ${filaDato('Fecha', capital(fechaLarga(g.fecha)) + ' ' + g.fecha.slice(0, 4))}
          ${filaDato('Categoría', html`<span class="punto" style="--c:${g.categoria_color};display:inline-block;margin-right:6px"></span>${g.categoria}`)}
          ${!g.es_mio ? filaDato('Usuario', `${g.usuario_nombre} (${g.usuario_rut})`) : ''}
          ${filaDato('Registrado', fechaCorta(g.creado_en) + ' ' + g.creado_en.slice(11, 16))}
        </ul>
      </section>

      <section class="tarjeta">
        <div class="cabeza"><h2>Documentos</h2><span class="chico suave">${g.documentos_lista.length}</span></div>
        ${g.documentos_lista.length ? html`
          <div class="miniaturas">
            ${g.documentos_lista.map((d) => html`
              <button type="button" class="mini" data-doc="${d.id}" aria-label="Ver ${d.nombre_archivo}">
                ${d.es_imagen ? html`<img src="${urlDocumento(d.id)}" alt="" loading="lazy">`
                              : html`<span class="pdf">${icono('archivo')}${d.nombre_archivo}</span>`}
              </button>`)}
          </div>
          <ul class="lista" style="margin-top:12px">
            ${g.documentos_lista.map((d) => html`
              <li class="item" style="cursor:default">
                <span class="cuerpo"><span class="titulo chico num">${d.nombre_archivo}</span>
                  <span class="sub">uploads/${d.ruta_relativa.split('/').slice(0, 3).join('/')}/ · ${tamano(d.tamano_bytes)}</span></span>
                <a class="btn-icono" href="${urlDocumento(d.id, true)}" aria-label="Descargar">${icono('descargar')}</a>
              </li>`)}
          </ul>` : vacio('Este gasto no tiene documentos adjuntos', 'archivo')}
      </section>

      ${g.es_mio ? html`
        <div class="botones">
          <button class="btn peligro" id="btn-eliminar">${icono('basura')} Eliminar</button>
          <a class="btn" href="#/editar/${g.id}">${icono('editar')} Editar</a>
        </div>` : ''}
    </div>`;

  function montar(raiz) {
    $$('[data-doc]', raiz).forEach((b) => b.addEventListener('click', () => {
      const d = g.documentos_lista.find((x) => x.id === Number(b.dataset.doc));
      abrirVisor({ url: urlDocumento(d.id), urlDescarga: urlDocumento(d.id, true), nombre: d.nombre_archivo, esImagen: d.es_imagen });
    }));
    $('#btn-eliminar', raiz)?.addEventListener('click', async () => {
      const n = g.documentos_lista.length;
      if (!await confirmar(`¿Eliminar ${correlativo(g.correlativo)}?`,
        `Se eliminará el gasto${n ? ` y sus ${n} documento(s)` : ''} de forma permanente.`)) return;
      try {
        const r = await api.eliminarGasto(g.id);
        avisar(r.mensaje);
        ctx.ir('#/historial', { reemplazar: true });
      } catch (err) { avisar(err.message, 'error'); }
    });
  }

  return { titulo: correlativo(g.correlativo), atras: true, contenido, montar };
}

// ===============================================================
// [SECCIÓN 5] HISTORIAL EN CARPETAS
// Año > Mes > Día, igual que la organización física de /uploads.
// Incluye buscador por proveedor, descripción o N° de documento.
// ===============================================================
const tarjetaCarpeta = (href, nombre, x) => html`
  <a class="carpeta" href="${href}">
    ${icono('carpeta')}
    <span class="nombre">${nombre}</span>
    <span class="monto num">${pesos(x.total)}</span>
    <span class="sub">${x.cantidad} gasto(s) · ${x.documentos} doc(s)</span>
  </a>`;

export async function historial(_p, ctx) {
  const q = (ctx.query.get('q') || '').trim();
  const buscador = html`
    <form class="tarjeta" id="buscar" role="search" style="padding:10px">
      <div class="prefijo"><span>${icono('buscar')}</span>
        <input class="input" name="q" type="search" placeholder="Buscar proveedor, descripción o N°" value="${q}" maxlength="60" style="padding-left:40px">
      </div>
    </form>`;
  const montar = (raiz) => $('#buscar', raiz).addEventListener('submit', (e) => {
    e.preventDefault();
    const t = e.target.q.value.trim();
    ctx.ir(t ? `#/historial?q=${encodeURIComponent(t)}` : '#/historial');
  });

  // Modo búsqueda
  if (q) {
    const { gastos } = await api.listarGastos({ q });
    return {
      titulo: 'Historial', montar,
      contenido: html`<div class="pila">${buscador}
        <section class="tarjeta">
          <div class="cabeza"><h2>Resultados para “${q}”</h2><a href="#/historial" class="chico">Limpiar</a></div>
          ${gastos.length ? html`<ul class="lista">${gastos.map((g) => filaGasto(g))}</ul>` : vacio('Sin resultados', 'buscar')}
        </section></div>`,
    };
  }

  const { carpetas } = await api.carpetas();
  return {
    titulo: 'Historial', montar,
    contenido: html`<div class="pila">${buscador}
      ${carpetas.length ? carpetas.map((a) => html`
        <section>
          <div class="cabeza"><h2>${icono('calendario')} ${a.anio}</h2>
            <span class="suave chico num">${pesos(a.total)} · ${a.cantidad} gastos</span></div>
          <div class="carpetas">
            ${a.meses.map((m) => tarjetaCarpeta(`#/historial/${a.anio}/${m.mes}`, nombreMes(m.mes), m))}
          </div>
        </section>`) : html`<section class="tarjeta">${vacio('Aún no tienes gastos registrados', 'carpeta')}</section>`}
    </div>`,
  };
}

// --- Resumen mensual + carpetas de días
export async function mes([anio, mes], _ctx) {
  const [r, { carpetas }] = await Promise.all([api.resumenMes(anio, mes), api.carpetas()]);
  const dias = carpetas.find((a) => a.anio === Number(anio))?.meses.find((m) => m.mes === Number(mes))?.dias || [];
  const variacion = r.total_mes_anterior ? Math.round(((r.total - r.total_mes_anterior) / r.total_mes_anterior) * 100) : null;

  const porDia = Array.from({ length: r.dias_mes }, (_, i) => {
    const total = r.por_dia.find((d) => d.dia === i + 1)?.total || 0;
    return { etiqueta: String(i + 1), valor: total, tip: `${i + 1} de ${MESES[mes - 1]}: ${pesos(total)}` };
  });
  const iso = (d) => `${anio}-${String(mes).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

  return {
    titulo: nombreMes(Number(mes), anio), atras: true,
    contenido: html`
      <div class="pila">
        <nav class="miga"><a href="#/historial">Historial</a> › <span>${anio}</span> › <strong>${nombreMes(Number(mes))}</strong></nav>

        <section class="tarjeta heroe">
          <div class="etiqueta">Resumen mensual · ${nombreMes(Number(mes), anio)}</div>
          <div class="cifra num">${pesos(r.total)}</div>
          <div class="etiqueta">${variacion === null ? 'Sin datos del mes anterior'
            : `${variacion > 0 ? '▲' : variacion < 0 ? '▼' : '='} ${Math.abs(variacion)}% vs. mes anterior (${pesos(r.total_mes_anterior)})`}</div>
        </section>

        <div class="cuadros">
          <div class="cuadro"><div class="etiqueta">Gastos</div><div class="cifra num">${r.cantidad}</div></div>
          <div class="cuadro"><div class="etiqueta">Documentos</div><div class="cifra num">${r.documentos}</div></div>
          <div class="cuadro"><div class="etiqueta">Promedio diario</div><div class="cifra num">${pesos(r.promedio_diario)}</div></div>
          <div class="cuadro"><div class="etiqueta">Gasto mayor</div><div class="cifra num">${pesos(r.mayor_gasto?.monto || 0)}</div>
            <div class="detalle">${r.mayor_gasto?.proveedor || '—'}</div></div>
        </div>

        ${r.cantidad ? html`
          <section class="tarjeta"><h2>Gasto por día</h2>
            ${graficoColumnas(porDia, { cadaEtiqueta: 5, titulo: 'Gasto por día del mes' })}</section>
          <section class="tarjeta"><h2>Por categoría</h2>
            ${barrasHorizontales(r.por_categoria.map((c) => ({ nombre: c.categoria, valor: c.total, color: c.color, tip: `${c.cantidad} gasto(s)` })))}
            <div class="botones" style="margin-top:14px">
              ${r.por_tipo.map((t) => html`<span class="chip">${TIPOS[t.tipo]}: ${t.cantidad} · ${pesos(t.total)}</span>`)}
            </div>
          </section>` : ''}

        <section>
          <div class="cabeza"><h2>Carpetas por día</h2></div>
          ${dias.length ? html`<div class="carpetas">
            ${dias.map((d) => tarjetaCarpeta(`#/historial/${anio}/${mes}/${d.dia}`,
              `${String(d.dia).padStart(2, '0')} · ${capital(diaSemana(iso(d.dia)))}`, d))}
          </div>` : html`<div class="tarjeta">${vacio('No hay gastos este mes', 'carpeta')}</div>`}
        </section>
      </div>`,
  };
}

// --- Gastos de un día
export async function dia([anio, mes, d]) {
  const { gastos } = await api.listarGastos({ anio, mes, dia: d });
  const iso = `${anio}-${String(mes).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  const total = gastos.reduce((s, g) => s + g.monto, 0);
  return {
    titulo: capital(fechaLarga(iso)), atras: true,
    contenido: html`
      <div class="pila">
        <nav class="miga"><a href="#/historial">Historial</a> › <span>${anio}</span> ›
          <a href="#/historial/${anio}/${mes}">${nombreMes(Number(mes))}</a> › <strong>${String(d).padStart(2, '0')}</strong></nav>
        <section class="tarjeta">
          <div class="cabeza"><h2>${capital(fechaLarga(iso))}</h2><span class="monto num" style="font-weight:700">${pesos(total)}</span></div>
          ${gastos.length ? html`<ul class="lista">${gastos.map((g) => filaGasto(g))}</ul>` : vacio('Sin gastos este día')}
        </section>
      </div>`,
  };
}

// ===============================================================
// [SECCIÓN 6] PERFIL
// Datos de la cuenta, cambio de contraseña (pide la actual),
// instalación como PWA y cierre de sesión.
// ===============================================================
export async function perfil(_p, ctx) {
  const u = ctx.usuario;
  const esIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const instalada = matchMedia('(display-mode: standalone)').matches;

  const contenido = html`
    <div class="pila">
      <section class="tarjeta">
        <div class="item" style="cursor:default;padding:0">
          <span class="icono-cat" style="--c:var(--primario)">${icono(u.rol === 'admin' ? 'escudo' : 'usuario')}</span>
          <span class="cuerpo"><span class="titulo">${u.nombre}</span>
            <span class="sub">RUT ${u.rut} · ${u.rol === 'admin' ? 'Administrador' : 'Usuario'}</span></span>
        </div>
      </section>

      <section class="tarjeta">
        <h2>${icono('instalar')} Instalar MoneyFan</h2>
        <p class="suave chico" style="margin-bottom:12px">${instalada ? 'La app ya está instalada en este dispositivo.'
          : esIOS ? 'En iPhone: toca Compartir (□↑) en Safari y luego “Agregar a inicio”.'
          : 'Instálala en tu celular o computador para abrirla como una app.'}</p>
        ${!instalada && !esIOS ? html`<button class="btn secundario bloque" id="btn-instalar" ${raw(ctx.puedeInstalar() ? '' : 'disabled')}>
          ${icono('instalar')} Instalar app</button>` : ''}
      </section>

      <form class="tarjeta" id="form-clave" novalidate>
        <h2>${icono('llave')} Cambiar contraseña</h2>
        ${campo('clave_actual', 'Contraseña actual', raw('<input class="input" id="f-clave_actual" name="clave_actual" type="password" autocomplete="current-password" maxlength="72">'))}
        ${campo('clave_nueva', 'Nueva contraseña', raw('<input class="input" id="f-clave_nueva" name="clave_nueva" type="password" autocomplete="new-password" maxlength="72">'), 'Mínimo 6 caracteres, sin espacios')}
        ${campo('clave_repite', 'Repite la nueva contraseña', raw('<input class="input" id="f-clave_repite" name="clave_repite" type="password" autocomplete="new-password" maxlength="72">'))}
        <button class="btn bloque" type="submit">Actualizar contraseña</button>
      </form>

      <button class="btn peligro bloque" id="btn-salir">${icono('salir')} Cerrar sesión</button>
    </div>`;

  function montar(raiz) {
    $('#btn-instalar', raiz)?.addEventListener('click', () => ctx.instalar());
    $('#btn-salir', raiz).addEventListener('click', () => ctx.salir());

    const form = $('#form-clave', raiz);
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = Object.fromEntries(new FormData(form));
      const errores = {
        clave_actual: d.clave_actual ? '' : 'Ingresa tu contraseña actual',
        clave_nueva: validarClave(d.clave_nueva),
        clave_repite: d.clave_nueva !== d.clave_repite ? 'Las contraseñas no coinciden' : '',
      };
      if (!mostrarErrores(form, errores)) return;
      try {
        const r = await conBoton($('button', form), () => api.cambiarClave(d));
        form.reset();
        avisar(r.mensaje);
      } catch (err) {
        mostrarErrores(form, err.campos || {});
        avisar(err.message, 'error');
      }
    });
  }

  return { titulo: 'Mi perfil', contenido, montar };
}
