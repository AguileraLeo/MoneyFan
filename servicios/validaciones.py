"""
=====================================================================
 MoneyFan · servicios/validaciones.py
---------------------------------------------------------------------
 [QUÉ HACE ESTE ARCHIVO]
 Contiene las RESTRICCIONES BÁSICAS de la demo:
   - RUT chileno con formato 12.345.678-9
   - Contraseña de exactamente 6 caracteres
   - Correo Gmail válido
   - Descripción, monto (entero positivo) y categoría del microgasto
 Cada función devuelve una tupla (es_valido, mensaje) para que la
 pantalla pueda mostrar el error junto al campo correspondiente.
=====================================================================
"""
import re

from config import (CATEGORIAS, DESC_MAX, DESC_MIN, LARGO_CLAVE,
                    MONTO_MAXIMO, MONTO_MINIMO, VALIDAR_DIGITO_VERIFICADOR)

# ---------------------------------------------------------------
# [SECCIÓN 1] EXPRESIONES REGULARES
# ---------------------------------------------------------------
PATRON_RUT = re.compile(r"^\d{1,2}\.\d{3}\.\d{3}-[\dkK]$")       # 12.345.678-9 / 9.876.543-K
PATRON_GMAIL = re.compile(r"^[a-zA-Z0-9._%+-]+@gmail\.com$")      # algo@gmail.com
PATRON_ENTERO_POSITIVO = re.compile(r"^[1-9]\d*$")                # 1, 25, 1500 (sin ceros a la izquierda)


# ---------------------------------------------------------------
# [SECCIÓN 2] UTILIDADES DE RUT
# ---------------------------------------------------------------
def limpiar_rut(rut: str) -> str:
    """Quita puntos, guion y espacios: '12.345.678-9' -> '123456789'."""
    return rut.replace(".", "").replace("-", "").replace(" ", "").upper()


def formatear_rut(rut: str) -> str:
    """
    Da formato chileno a un RUT escrito "a la rápida".
    Ej: '123456789' -> '12.345.678-9'. Si no se puede, lo devuelve tal cual.
    """
    limpio = limpiar_rut(rut)
    if len(limpio) < 2 or not limpio[:-1].isdigit():
        return rut
    cuerpo, dv = limpio[:-1], limpio[-1]
    cuerpo_con_puntos = f"{int(cuerpo):,}".replace(",", ".")
    return f"{cuerpo_con_puntos}-{dv}"


def calcular_dv(cuerpo: str) -> str:
    """Calcula el dígito verificador con el algoritmo módulo 11."""
    suma, multiplicador = 0, 2
    for digito in reversed(cuerpo):
        suma += int(digito) * multiplicador
        multiplicador = 2 if multiplicador == 7 else multiplicador + 1
    resto = 11 - (suma % 11)
    return "0" if resto == 11 else "K" if resto == 10 else str(resto)


# ---------------------------------------------------------------
# [SECCIÓN 3] VALIDACIONES DEL LOGIN
# ---------------------------------------------------------------
def validar_rut(rut: str):
    rut = rut.strip()
    if not rut:
        return False, "Ingresa tu RUT"
    if not PATRON_RUT.match(rut):
        return False, "Formato inválido. Ej: 12.345.678-9"
    if VALIDAR_DIGITO_VERIFICADOR:
        limpio = limpiar_rut(rut)
        if calcular_dv(limpio[:-1]) != limpio[-1]:
            return False, "El dígito verificador no es válido"
    return True, ""


def validar_clave(clave: str):
    if not clave:
        return False, "Ingresa tu contraseña"
    if " " in clave:
        return False, "La contraseña no puede tener espacios"
    if len(clave) != LARGO_CLAVE:
        return False, f"La contraseña debe tener {LARGO_CLAVE} caracteres"
    return True, ""


def validar_gmail(correo: str):
    correo = correo.strip()
    if not correo:
        return False, "Ingresa tu correo Gmail"
    if not PATRON_GMAIL.match(correo):
        return False, "Debe ser una cuenta @gmail.com válida"
    return True, ""


# ---------------------------------------------------------------
# [SECCIÓN 4] VALIDACIONES DEL MICROGASTO MANUAL
# ---------------------------------------------------------------
def validar_descripcion(texto: str):
    texto = texto.strip()
    if not texto:
        return False, "Completa la descripción"
    if len(texto) < DESC_MIN:
        return False, f"Mínimo {DESC_MIN} caracteres"
    if len(texto) > DESC_MAX:
        return False, f"Máximo {DESC_MAX} caracteres"
    return True, ""


def validar_monto(texto: str):
    """Devuelve (es_valido, mensaje, valor_entero)."""
    texto = texto.strip()
    if not texto:
        return False, "Ingresa el monto", 0
    if not PATRON_ENTERO_POSITIVO.match(texto):
        return False, "Solo números enteros positivos (sin puntos)", 0
    valor = int(texto)
    if valor < MONTO_MINIMO:
        return False, f"El monto mínimo es ${MONTO_MINIMO}", 0
    if valor > MONTO_MAXIMO:
        return False, f"Un microgasto no supera ${MONTO_MAXIMO:,}".replace(",", "."), 0
    return True, "", valor


def validar_categoria(categoria: str):
    if not categoria:
        return False, "Selecciona una categoría"
    if categoria not in CATEGORIAS:
        return False, "Categoría no válida"
    return True, ""


# ---------------------------------------------------------------
# [SECCIÓN 5] FILTROS DE ESCRITURA (se usan en los MDTextField)
# Se ejecutan MIENTRAS el usuario escribe y bloquean caracteres
# no permitidos antes de que lleguen al campo.
# ---------------------------------------------------------------
def filtro_digitos(substring: str, from_undo: bool) -> str:
    """Deja pasar solo dígitos 0-9 (para el monto)."""
    return "".join(c for c in substring if c.isdigit())


def filtro_rut(substring: str, from_undo: bool) -> str:
    """Deja pasar dígitos, punto, guion y la letra K (para el RUT)."""
    return "".join(c for c in substring if c.isdigit() or c in ".-kK")
