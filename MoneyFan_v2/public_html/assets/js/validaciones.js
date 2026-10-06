/* =====================================================================
   MoneyFan v2 · assets/js/validaciones.js
   ---------------------------------------------------------------------
   [QUÉ HACE ESTE ARCHIVO]
   Validaciones en el NAVEGADOR: avisan al instante, antes de enviar.
   Son las MISMAS reglas que valida el servidor (api/_lib/validaciones.php);
   el servidor vuelve a revisar todo porque nunca confía en el cliente.
   Cada función devuelve un mensaje de error o '' si está correcto.
   ===================================================================== */

// ---------------------------------------------------------------
// [SECCIÓN 1] REGLAS (iguales a las del backend)
// ---------------------------------------------------------------
export const REGLAS = {
  claveMin: 6, claveMax: 72,
  montoMax: 50_000_000,
  proveedor: [2, 60],
  descripcion: [3, 160],
  nombre: [3, 60],
  docMaxMB: 5,
  docMaxPorGasto: 5,
  tiposDoc: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
};

// ---------------------------------------------------------------
// [SECCIÓN 2] RUT (mismo comportamiento que la v1)
// ---------------------------------------------------------------
export const limpiarRut = (rut) => rut.replace(/[.\-\s]/g, '').toUpperCase();

/** '123456789' -> '12.345.678-9' */
export function formatearRut(rut) {
  const limpio = limpiarRut(rut);
  if (limpio.length < 2 || !/^\d+$/.test(limpio.slice(0, -1))) return rut.trim();
  const cuerpo = limpio.slice(0, -1).replace(/^0+/, '') || '0';
  return cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + limpio.slice(-1);
}

export function validarRut(rut) {
  rut = rut.trim();
  if (!rut) return 'Ingresa tu RUT';
  if (!/^\d{1,2}\.\d{3}\.\d{3}-[\dkK]$/.test(rut)) return 'Formato inválido. Ej: 12.345.678-9';
  return '';
}

// ---------------------------------------------------------------
// [SECCIÓN 3] CONTRASEÑA, NOMBRE Y TEXTOS
// ---------------------------------------------------------------
export function validarClave(clave) {
  if (!clave) return 'Ingresa la contraseña';
  if (/\s/.test(clave)) return 'La contraseña no puede tener espacios';
  if (clave.length < REGLAS.claveMin) return `Mínimo ${REGLAS.claveMin} caracteres`;
  if (clave.length > REGLAS.claveMax) return `Máximo ${REGLAS.claveMax} caracteres`;
  return '';
}

export function validarTexto(texto, [min, max], vacio) {
  texto = (texto || '').trim();
  if (!texto) return vacio;
  if (texto.length < min) return `Mínimo ${min} caracteres`;
  if (texto.length > max) return `Máximo ${max} caracteres`;
  return '';
}

export function validarEmailOpcional(email) {
  email = (email || '').trim();
  if (!email) return '';
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email) ? '' : 'Correo no válido';
}

// ---------------------------------------------------------------
// [SECCIÓN 4] DATOS DEL GASTO
// ---------------------------------------------------------------
/** Solo enteros positivos, sin puntos ni decimales (igual que la v1). */
export function validarMonto(texto) {
  texto = String(texto ?? '').trim();
  if (!texto) return 'Ingresa el monto';
  if (!/^[1-9]\d*$/.test(texto)) return 'Solo números enteros positivos (sin puntos)';
  if (Number(texto) > REGLAS.montoMax) return 'El monto máximo es $50.000.000';
  return '';
}

export function validarFecha(fecha) {
  if (!fecha) return 'Ingresa la fecha';
  const d = new Date(fecha + 'T00:00:00');
  if (Number.isNaN(d.getTime())) return 'Fecha no válida';
  if (fecha > hoyISO()) return 'La fecha no puede ser futura';
  if (d.getFullYear() < 2000) return 'La fecha debe ser desde el año 2000';
  return '';
}

export function validarNumeroDocumento(numero) {
  numero = (numero || '').trim();
  if (!numero) return '';
  return /^[A-Za-z0-9\-/ ]{1,30}$/.test(numero) ? '' : 'Solo letras, números y guiones (máx. 30)';
}

export function validarArchivo(archivo) {
  if (!REGLAS.tiposDoc.includes(archivo.type)) return `"${archivo.name}" debe ser imagen (JPG/PNG/WEBP) o PDF`;
  if (archivo.size > REGLAS.docMaxMB * 1024 * 1024) return `"${archivo.name}" supera ${REGLAS.docMaxMB} MB`;
  return '';
}

// ---------------------------------------------------------------
// [SECCIÓN 5] FILTROS DE ESCRITURA
// Bloquean caracteres no permitidos MIENTRAS se escribe.
// ---------------------------------------------------------------
export function filtrarDigitos(input) {
  input.addEventListener('input', () => {
    const limpio = input.value.replace(/\D/g, '').replace(/^0+/, '');
    if (limpio !== input.value) input.value = limpio;
  });
}

export function filtrarRut(input) {
  input.addEventListener('input', () => {
    const limpio = input.value.replace(/[^\dkK.\-]/g, '');
    if (limpio !== input.value) input.value = limpio;
  });
  // Al salir del campo se le da formato (como en la v1)
  input.addEventListener('blur', () => { if (input.value) input.value = formatearRut(input.value); });
}

/** Fecha de hoy en formato AAAA-MM-DD según la hora local. */
export function hoyISO() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
