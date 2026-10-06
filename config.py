"""
=====================================================================
 MoneyFan · config.py
---------------------------------------------------------------------
 [QUÉ HACE ESTE ARCHIVO]
 Centraliza TODA la configuración de la demo: rutas a los archivos
 JSON, paleta de colores, categorías, reglas de validación y el
 tamaño de la "pantalla de teléfono" simulada.
 Si quieres cambiar un color o una regla, cámbiala aquí y se
 actualiza en toda la app.
=====================================================================
"""
import os

# ---------------------------------------------------------------
# [SECCIÓN 1] RUTAS DEL PROYECTO
# Se calculan a partir de la ubicación de este archivo para que la
# app funcione sin importar desde qué carpeta se ejecute en VS Code.
# ---------------------------------------------------------------
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, "data")

RUTA_KV = os.path.join(BASE_DIR, "interfaz.kv")                       # Diseño visual (lenguaje kv)
RUTA_USUARIOS = os.path.join(DATA_DIR, "usuarios.json")               # Usuarios demo (RUT + clave)
RUTA_CORREOS = os.path.join(DATA_DIR, "correos_simulados.json")       # Bandeja Gmail simulada
RUTA_GASTOS_MANUALES = os.path.join(DATA_DIR, "gastos_manuales.json") # Gastos ingresados a mano
RUTA_ESTADO = os.path.join(DATA_DIR, "estado_app.json")               # Estado (Gmail conectado, etc.)

# ---------------------------------------------------------------
# [SECCIÓN 2] PALETA DE COLORES (tonos suaves y formales)
# Azul petróleo como color principal + verde salvia y arena como
# acentos. Fondo gris muy claro para máxima legibilidad.
# ---------------------------------------------------------------
PALETA = {
    "primario": "#2F4858",      # Azul petróleo (barras superiores, botones)
    "secundario": "#5B7C8D",    # Azul grisáceo (textos secundarios, iconos)
    "acento": "#86A397",        # Verde salvia (éxito, gráficos)
    "acento2": "#C9A66B",       # Arena (destacados)
    "fondo": "#F3F5F7",         # Fondo general de las pantallas
    "tarjeta": "#FFFFFF",       # Fondo de las tarjetas
    "borde": "#E1E6EA",         # Bordes suaves de tarjetas
    "texto": "#263238",         # Texto principal
    "texto_suave": "#6B7F8A",   # Texto secundario
    "error": "#B5524C",         # Rojo apagado para errores
}

# ---------------------------------------------------------------
# [SECCIÓN 3] CATEGORÍAS DE MICROGASTOS
# Cada categoría tiene un icono (Material Design Icons) y un color
# propio que se usa en listas y gráficos.
# ---------------------------------------------------------------
CATEGORIAS = {
    "Snacks":     {"icono": "cookie",                         "color": "#C9A66B"},
    "Farmacia":   {"icono": "pill",                           "color": "#86A397"},
    "Café":       {"icono": "coffee",                         "color": "#9C7A6B"},
    "Transporte": {"icono": "bus",                            "color": "#5B7C8D"},
    "Otros":      {"icono": "dots-horizontal-circle-outline", "color": "#9A8FB0"},
}

# ---------------------------------------------------------------
# [SECCIÓN 4] REGLAS DE NEGOCIO / VALIDACIÓN
# ---------------------------------------------------------------
LARGO_CLAVE = 6                    # La contraseña debe tener exactamente 6 caracteres
VALIDAR_DIGITO_VERIFICADOR = False # True = exige que el dígito verificador del RUT sea real (módulo 11).
                                   # Está en False porque el RUT de ejemplo 12.345.678-9 no tiene DV real.
MONTO_MINIMO = 1                   # Un microgasto debe ser al menos $1
MONTO_MAXIMO = 100_000             # Tope para considerarlo "micro" gasto
DESC_MIN = 3                       # Largo mínimo de la descripción
DESC_MAX = 30                      # Largo máximo de la descripción

# ---------------------------------------------------------------
# [SECCIÓN 5] AVISO DIARIO
# ---------------------------------------------------------------
HORA_AVISO = 20    # 20:00 hrs
MINUTO_AVISO = 0

# ---------------------------------------------------------------
# [SECCIÓN 6] SIMULACIÓN DE TELÉFONO
# Tamaño de la ventana en el computador (ancho x alto en píxeles),
# proporción similar a un smartphone moderno.
# ---------------------------------------------------------------
TAMANO_TELEFONO = (400, 800)

# ---------------------------------------------------------------
# [SECCIÓN 7] TEXTOS DE FECHAS EN ESPAÑOL
# (Se definen a mano para no depender del idioma del sistema).
# ---------------------------------------------------------------
DIAS_SEMANA = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"]
DIAS_CORTOS = ["L", "M", "X", "J", "V", "S", "D"]
MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio",
         "agosto", "septiembre", "octubre", "noviembre", "diciembre"]
