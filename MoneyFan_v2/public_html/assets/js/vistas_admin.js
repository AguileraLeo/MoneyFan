/* =====================================================================
   MoneyFan v2 · assets/js/vistas_admin.js
   ---------------------------------------------------------------------
   [QUÉ HACE ESTE ARCHIVO]
   Pantallas EXCLUSIVAS del perfil Administrador:
     1. Panel: totales, cantidad de documentos, usuarios, exportar.
     2. Usuarios: ver todos, crear, activar/desactivar, cambiar clave.
     3. Gastos: ver los gastos de todos con filtros + exportar CSV/Excel.
   (El backend vuelve a verificar el rol en cada petición: ocultar el
   menú no es la seguridad, solo la comodidad.)
   ===================================================================== */

import { api, urlExportar } from './api.js';
import {
  html, raw, $, $$, icono, pesos, fechaCorta, correlativo, TIPOS, tamano,
  avisar, abrirModal, mostrarErrores, conBoton, barrasHorizontales,
} from './ui.js';
import { validarRut, validarClave, validarTexto, validarEmailOpcional, filtrarRut, formatearRut, REGLAS } from './validaciones.js';
import { filaGasto, vacio } from './vistas_usuario.js';

const pestañas = (activa) => html`
  <div class="segmentos" style="margin-bottom:16px">
    ${[['#/admin', 'Panel', 'escudo'], ['#/admin/usuarios', 'Usuarios', 'usuarios'], ['#/admin/gastos', 'Gastos', 'tabla']].map(([h, t, i]) => html`
      <a class="btn ${activa === h ? '' : 'secundario'} chico" href="${h}">${icono(i)} ${t}</a>`)}
  </div>`;

const campo = (nombre, etiqueta, control, ayuda = '') => html`
  <div class="campo" data-campo="${nombre}">
    <label for="a-${nombre}">${etiqueta}</label>${control}
    ${ayuda ? html`<span class="ayuda">${ayuda}</span>` : ''}
    <span class="error" aria-live="polite"></span>
  </div>`;

// ===============================================================
// [SECCIÓN 1] PANEL GENERAL
// ===============================================================
export async function panel() {
  const { totales: t, por_usuario, por_categoria } = await api.panel();
  return {
    titulo: 'Administración', ancho: true,
    contenido: html`
      ${pestañas('#/admin')}
      <div class="pila">
        <div class="cuadros c4">
          <div class="cuadro"><div class="etiqueta">Total registrado</div><div class="cifra num">${pesos(t.total)}</div>
            <div class="detalle">${t.gastos} gastos</div></div>
          <div class="cuadro"><div class="etiqueta">Este mes</div><div class="cifra num">${pesos(t.total_mes)}</div>
            <div class="detalle">${t.gastos_mes} gastos</div></div>
          <div class="cuadro"><div class="etiqueta">Documentos</div><div class="cifra num">${t.documentos}</div>
            <div class="detalle">${tamano(t.bytes_documentos)} en uploads/</div></div>
          <div class="cuadro"><div class="etiqueta">Usuarios</div><div class="cifra num">${t.usuarios}</div>
            <div class="detalle">${t.usuarios_activos} activos</div></div>
        </div>

        <div class="cuadros" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
          <section class="tarjeta"><h2>Total por usuario</h2>
            ${barrasHorizontales(por_usuario.map((u) => ({ nombre: u.nombre, valor: u.total, color: 'var(--secundario)', tip: `${u.rut} · ${u.gastos} gastos` })))}
          </section>
          <section class="tarjeta"><h2>Total por categoría</h2>
            ${por_categoria.length ? barrasHorizontales(por_categoria.map((c) => ({ nombre: c.categoria, valor: c.total, color: c.color })))
                                   : vacio('Sin gastos registrados')}
          </section>
        </div>

        <section class="tarjeta">
          <h2>Exportar todos los gastos</h2>
          <p class="suave chico" style="margin-bottom:12px">Para exportar con filtros (usuario o fechas) usa la pestaña Gastos.</p>
          <div class="botones">
            <a class="btn secundario" href="${urlExportar('csv')}" download>${icono('descargar')} CSV</a>
            <a class="btn" href="${urlExportar('xlsx')}" download>${icono('tabla')} Excel (.xlsx)</a>
          </div>
        </section>
      </div>`,
  };
}

// ===============================================================
// [SECCIÓN 2] USUARIOS
// ===============================================================
export async function usuarios(_p, ctx) {
  const { usuarios: lista } = await api.usuarios();

  const contenido = html`
    ${pestañas('#/admin/usuarios')}
    <div class="pila">
      <div class="cabeza" style="margin:0"><h2>${lista.length} usuario(s)</h2>
        <button class="btn chico" id="btn-nuevo">${icono('mas')} Crear usuario</button></div>
      <section class="tarjeta" style="padding:4px 16px">
        <ul class="lista">
          ${lista.map((u) => html`
            <li class="item" style="cursor:default;flex-wrap:wrap">
              <span class="icono-cat" style="--c:${u.activo ? 'var(--acento)' : 'var(--borde)'}">${icono(u.rol === 'admin' ? 'escudo' : 'usuario')}</span>
              <span class="cuerpo">
                <span class="titulo">${u.nombre} ${u.soy_yo ? html`<span class="chip">Tú</span>` : ''}</span>
                <span class="sub">${u.rut} · ${u.rol === 'admin' ? 'Admin' : 'Usuario'} · ${u.gastos} gastos · ${u.documentos} docs · ${pesos(u.total)}</span>
                <span class="sub">${u.ultimo_acceso ? 'Último acceso ' + fechaCorta(u.ultimo_acceso) + ' ' + u.ultimo_acceso.slice(11, 16) : 'Nunca ha ingresado'}</span>
              </span>
              <button class="btn secundario chico" data-clave="${u.id}" data-nombre="${u.nombre}">${icono('llave')} Clave</button>
              <label class="interruptor" title="${u.activo ? 'Activo' : 'Inactivo'}">
                <input type="checkbox" data-estado="${u.id}" ${raw(u.activo ? 'checked' : '')} ${raw(u.soy_yo ? 'disabled' : '')} aria-label="Cuenta activa de ${u.nombre}">
                <span></span>
              </label>
            </li>`)}
        </ul>
      </section>
      <p class="suave chico">Desactivar una cuenta impide su ingreso pero conserva sus gastos y documentos.</p>
    </div>`;

  function montar(raiz) {
    // Activar / desactivar
    $$('[data-estado]', raiz).forEach((chk) => chk.addEventListener('change', async () => {
      try {
        avisar((await api.estadoUsuario(Number(chk.dataset.estado), chk.checked)).mensaje);
        ctx.recargar();
      } catch (err) { chk.checked = !chk.checked; avisar(err.message, 'error'); }
    }));

    // Cambiar contraseña de un usuario
    $$('[data-clave]', raiz).forEach((b) => b.addEventListener('click', () => {
      const { el, cerrar } = abrirModal(html`
        <form novalidate>
          <h2>Nueva contraseña</h2><p>Para ${b.dataset.nombre}</p>
          ${campo('clave', 'Contraseña', raw('<input class="input" id="a-clave" name="clave" type="text" autocomplete="off" maxlength="72">'), 'Mínimo 6 caracteres, sin espacios')}
          <div class="botones"><button type="button" class="btn secundario" data-cerrar>Cancelar</button>
            <button class="btn" type="submit">Guardar</button></div>
        </form>`);
      const form = $('form', el);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!mostrarErrores(form, { clave: validarClave(form.clave.value) })) return;
        try {
          const r = await conBoton($('button[type=submit]', form), () => api.claveUsuario(Number(b.dataset.clave), form.clave.value));
          cerrar(); avisar(r.mensaje);
        } catch (err) { mostrarErrores(form, err.campos || { clave: err.message }); }
      });
    }));

    // Crear usuario
    $('#btn-nuevo', raiz).addEventListener('click', () => {
      const { el, cerrar } = abrirModal(html`
        <form novalidate>
          <h2>Crear usuario</h2>
          ${campo('rut', 'RUT', raw('<input class="input" id="a-rut" name="rut" placeholder="12.345.678-9" maxlength="12" autocomplete="off">'))}
          ${campo('nombre', 'Nombre', raw('<input class="input" id="a-nombre" name="nombre" maxlength="60" autocomplete="off">'))}
          ${campo('email', raw('Correo <span class="opcional">(opcional)</span>'), raw('<input class="input" id="a-email" name="email" type="email" maxlength="120" autocomplete="off">'))}
          ${campo('clave', 'Contraseña inicial', raw('<input class="input" id="a-clave" name="clave" type="text" maxlength="72" autocomplete="off">'), 'Mínimo 6 caracteres, sin espacios')}
          <div class="campo" data-campo="rol"><span class="label">Rol</span>
            <div class="segmentos">
              <label><input type="radio" name="rol" value="usuario" checked><span>Usuario</span></label>
              <label><input type="radio" name="rol" value="admin"><span>Administrador</span></label>
            </div><span class="error"></span></div>
          <div class="botones"><button type="button" class="btn secundario" data-cerrar>Cancelar</button>
            <button class="btn" type="submit">Crear</button></div>
        </form>`);
      const form = $('form', el);
      filtrarRut(form.rut);
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        form.rut.value = formatearRut(form.rut.value);
        const d = Object.fromEntries(new FormData(form));
        const errores = {
          rut: validarRut(d.rut),
          nombre: validarTexto(d.nombre, REGLAS.nombre, 'Ingresa el nombre'),
          email: validarEmailOpcional(d.email),
          clave: validarClave(d.clave),
        };
        if (!mostrarErrores(form, errores)) return;
        try {
          const r = await conBoton($('button[type=submit]', form), () => api.crearUsuario(d));
          cerrar(); avisar(r.mensaje); ctx.recargar();
        } catch (err) { mostrarErrores(form, err.campos || {}); avisar(err.message, 'error'); }
      });
    });
  }

  return { titulo: 'Usuarios', ancho: true, contenido, montar };
}

// ===============================================================
// [SECCIÓN 3] TODOS LOS GASTOS (filtros en una sola fila)
// Los filtros viajan en la URL (#/admin/gastos?usuario_id=2&desde=…)
// y se reutilizan para exportar exactamente lo que se ve.
// ===============================================================
export async function gastos(_p, ctx) {
  const f = {
    usuario_id: ctx.query.get('usuario_id') || '',
    desde: ctx.query.get('desde') || '',
    hasta: ctx.query.get('hasta') || '',
    q: ctx.query.get('q') || '',
  };
  const [{ gastos: lista, total, cantidad }, { usuarios: us }] = await Promise.all([api.gastosAdmin(f), api.usuarios()]);

  const contenido = html`
    ${pestañas('#/admin/gastos')}
    <div class="pila">
      <form class="tarjeta filtros" id="filtros">
        <div class="campo"><label for="g-u">Usuario</label>
          <select class="input" id="g-u" name="usuario_id"><option value="">Todos</option>
            ${us.map((u) => html`<option value="${u.id}" ${raw(String(u.id) === f.usuario_id ? 'selected' : '')}>${u.nombre}</option>`)}
          </select></div>
        <div class="campo"><label for="g-d">Desde</label><input class="input" id="g-d" type="date" name="desde" value="${f.desde}"></div>
        <div class="campo"><label for="g-h">Hasta</label><input class="input" id="g-h" type="date" name="hasta" value="${f.hasta}"></div>
        <div class="campo"><label for="g-q">Buscar</label><input class="input" id="g-q" type="search" name="q" value="${f.q}" placeholder="Proveedor, descripción…"></div>
        <button class="btn" type="submit">${icono('buscar')} Filtrar</button>
      </form>

      <div class="cuadros">
        <div class="cuadro"><div class="etiqueta">Total filtrado</div><div class="cifra num">${pesos(total)}</div></div>
        <div class="cuadro"><div class="etiqueta">Gastos</div><div class="cifra num">${cantidad}</div>
          <div class="detalle">${cantidad > lista.length ? `mostrando los ${lista.length} más recientes` : ''}</div></div>
      </div>

      <div class="botones">
        <a class="btn secundario" href="${urlExportar('csv', f)}" download>${icono('descargar')} Exportar CSV</a>
        <a class="btn secundario" href="${urlExportar('xlsx', f)}" download>${icono('tabla')} Exportar Excel</a>
      </div>

      <section class="tarjeta">
        ${!lista.length ? vacio('No hay gastos con esos filtros', 'buscar') : html`
          <div class="tabla-env oculto-movil">
            <table class="tabla">
              <thead><tr><th>N°</th><th>Fecha</th><th>Usuario</th><th>Tipo</th><th>Proveedor</th><th>Categoría</th><th class="der">Docs</th><th class="der">Monto</th></tr></thead>
              <tbody>${lista.map((g) => html`
                <tr class="clic" data-ir="#/gasto/${g.id}" tabindex="0">
                  <td class="num">${correlativo(g.correlativo).slice(3)}</td><td class="num">${fechaCorta(g.fecha)}</td>
                  <td>${g.usuario_nombre}</td><td>${TIPOS[g.tipo_documento]}</td><td>${g.proveedor}</td>
                  <td><span class="punto" style="--c:${g.categoria_color};display:inline-block;margin-right:6px"></span>${g.categoria}</td>
                  <td class="der num">${g.documentos}</td><td class="der num"><strong>${pesos(g.monto)}</strong></td>
                </tr>`)}</tbody>
            </table>
          </div>
          <ul class="lista solo-movil">${lista.map((g) => filaGasto(g, { mostrarUsuario: true }))}</ul>`}
      </section>
    </div>`;

  function montar(raiz) {
    $('#filtros', raiz).addEventListener('submit', (e) => {
      e.preventDefault();
      const p = new URLSearchParams();
      for (const [k, v] of new FormData(e.target)) if (v) p.set(k, v);
      ctx.ir('#/admin/gastos' + (p.size ? '?' + p : ''));
    });
    $$('[data-ir]', raiz).forEach((tr) => {
      tr.addEventListener('click', () => ctx.ir(tr.dataset.ir));
      tr.addEventListener('keydown', (e) => { if (e.key === 'Enter') ctx.ir(tr.dataset.ir); });
    });
  }

  return { titulo: 'Gastos de todos', ancho: true, contenido, montar };
}
