r"""
=====================================================================
  __  __                        _____
 |  \/  | ___  _ __   ___ _   _|  ___|_ _ _ __
 | |\/| |/ _ \| '_ \ / _ \ | | | |_ / _` | '_ \
 | |  | | (_) | | | |  __/ |_| |  _| (_| | | | |
 |_|  |_|\___/|_| |_|\___|\__, |_|  \__,_|_| |_|
                          |___/
 MoneyFan · main.py  (PUNTO DE ENTRADA DE LA APP)
---------------------------------------------------------------------
 [QUÉ HACE ESTE ARCHIVO]
 1. Configura la ventana para que parezca un TELÉFONO.
 2. Define las clases de cada pantalla:
      - LoginScreen    -> ingreso con RUT + contraseña (6 caracteres)
      - HomeScreen     -> PANTALLA 1: suma diaria + agregar microgasto
      - GraficosScreen -> PANTALLA 2: gráficos diario/semanal/mensual
 3. Define la clase MoneyFanApp, que conecta la interfaz (interfaz.kv)
    con la lógica (carpeta servicios/).

 Tecnologías: Python + Kivy + KivyMD + lenguaje kv + JSON.
 SIN base de datos ni backend: todo es simulado / hardcodeado.

 Ejecutar:  python main.py
=====================================================================
"""

# =====================================================================
# [SECCIÓN 0] CONFIGURACIÓN DE LA VENTANA (antes de importar Window)
# Kivy lee esta configuración al crear la ventana, por eso va primero.
# =====================================================================
import json
import os
from datetime import datetime

from kivy.config import Config

from config import TAMANO_TELEFONO

Config.set("graphics", "width", str(TAMANO_TELEFONO[0]))
Config.set("graphics", "height", str(TAMANO_TELEFONO[1]))
Config.set("graphics", "resizable", "0")                      # tamaño fijo tipo teléfono
Config.set("input", "mouse", "mouse,multitouch_on_demand")   # evita los "puntos rojos" del clic derecho

# =====================================================================
# [SECCIÓN 1] IMPORTACIONES
# =====================================================================
from kivy.clock import Clock
from kivy.core.window import Window
from kivy.lang import Builder
from kivy.metrics import dp
from kivy.properties import (BooleanProperty, ColorProperty, ObjectProperty,
                             StringProperty)
from kivy.uix.behaviors import ButtonBehavior
from kivy.utils import get_color_from_hex
from kivymd.app import MDApp
from kivymd.toast import toast
from kivymd.uix.boxlayout import MDBoxLayout
from kivymd.uix.button import MDFlatButton, MDRaisedButton, MDRectangleFlatIconButton
from kivymd.uix.dialog import MDDialog
from kivymd.uix.label import MDLabel
from kivymd.uix.screen import MDScreen
from kivymd.uix.textfield import MDTextField

from config import (CATEGORIAS, DIAS_SEMANA, HORA_AVISO, MESES, MINUTO_AVISO,
                    PALETA, RUTA_CORREOS, RUTA_ESTADO, RUTA_GASTOS_MANUALES,
                    RUTA_KV, RUTA_USUARIOS)
from servicios.gestor_gastos import GestorGastos, formato_pesos
from servicios.gmail_simulado import GmailSimulado
from servicios.notificador import NotificadorDiario
from servicios.validaciones import (filtro_digitos, filtro_rut, formatear_rut,
                                    validar_categoria, validar_clave,
                                    validar_descripcion, validar_monto,
                                    validar_rut)
from widgets.graficos import GraficoBarras, GraficoDona  # noqa: F401  (se usan en interfaz.kv)


def hex_a_color(nombre: str):
    """[UTILIDAD] Convierte un color de la PALETA (hex) al formato RGBA de Kivy."""
    return get_color_from_hex(PALETA[nombre])


def marcar_campo(campo, es_valido: bool, mensaje: str):
    """[UTILIDAD] Pinta un MDTextField en rojo con su mensaje si es inválido."""
    campo.error = not es_valido
    campo.helper_text = mensaje


def fecha_larga(fecha: datetime) -> str:
    """[UTILIDAD] 'Martes 6 de octubre'."""
    return f"{DIAS_SEMANA[fecha.weekday()]} {fecha.day} de {MESES[fecha.month - 1]}"


# =====================================================================
# [SECCIÓN 2] COMPONENTES VISUALES REUTILIZABLES
# (Su apariencia está definida en interfaz.kv; aquí solo sus datos).
# =====================================================================
class BarraEstado(MDBoxLayout):
    """Barra superior falsa de teléfono (hora, señal, batería)."""
    hora = StringProperty("")

    def __init__(self, **kwargs):
        super().__init__(**kwargs)
        self._actualizar_hora()
        Clock.schedule_interval(self._actualizar_hora, 10)

    def _actualizar_hora(self, *_):
        self.hora = datetime.now().strftime("%H:%M")


class BotonNav(ButtonBehavior, MDBoxLayout):
    """Botón de la barra de navegación inferior (Inicio / Gráficos)."""
    icono = StringProperty("")
    texto = StringProperty("")
    activo = BooleanProperty(False)


class BarraNavegacion(MDBoxLayout):
    """Barra inferior que permite moverse entre la Pantalla 1 y la 2."""
    activa = StringProperty("home")


class BotonSegmento(ButtonBehavior, MDBoxLayout):
    """Botón tipo 'pestaña' para elegir Diario / Semanal / Mensual."""
    texto = StringProperty("")
    activo = BooleanProperty(False)


class FilaGasto(ButtonBehavior, MDBoxLayout):
    """Una fila de la lista de microgastos. Al tocarla muestra la boleta."""
    titulo = StringProperty("")
    subtitulo = StringProperty("")
    monto_txt = StringProperty("")
    icono = StringProperty("cash")
    color_icono = ColorProperty([0, 0, 0, 1])
    origen_icono = StringProperty("")
    gasto = ObjectProperty(None, allownone=True)

    @classmethod
    def desde_gasto(cls, gasto: dict, mostrar_fecha=False):
        """Crea la fila a partir del diccionario de un gasto."""
        info = CATEGORIAS.get(gasto["categoria"], CATEGORIAS["Otros"])
        cuando = gasto["fecha"].strftime("%d/%m %H:%M" if mostrar_fecha else "%H:%M")
        return cls(
            titulo=gasto["descripcion"],
            subtitulo=f"{gasto['comercio']} · {cuando}",
            monto_txt=formato_pesos(gasto["monto"]),
            icono=info["icono"],
            color_icono=get_color_from_hex(info["color"]),
            origen_icono="gmail" if gasto["origen"] == "gmail" else "pencil-outline",
            gasto=gasto,
        )


class FilaLeyenda(MDBoxLayout):
    """Fila de la leyenda del gráfico de dona (color + categoría + %)."""
    texto = StringProperty("")
    valor_txt = StringProperty("")
    color = ColorProperty([0, 0, 0, 1])


class TextoVacio(MDLabel):
    """Mensaje gris para listas vacías."""


# =====================================================================
# [SECCIÓN 3] PANTALLA DE INGRESO (RUT + CONTRASEÑA)
# =====================================================================
class LoginScreen(MDScreen):

    # [3.1] Al salir del campo RUT se le da formato automáticamente
    def on_rut_focus(self, enfocado: bool):
        campo = self.ids.rut
        if not enfocado and campo.text.strip():
            campo.text = formatear_rut(campo.text)

    # [3.2] Botón INGRESAR: valida campos y busca el usuario demo
    def ingresar(self):
        app = MDApp.get_running_app()
        campo_rut, campo_clave = self.ids.rut, self.ids.clave
        if campo_rut.text.strip():
            campo_rut.text = formatear_rut(campo_rut.text)

        ok_rut, msg_rut = validar_rut(campo_rut.text)
        ok_clave, msg_clave = validar_clave(campo_clave.text)
        marcar_campo(campo_rut, ok_rut, msg_rut)
        marcar_campo(campo_clave, ok_clave, msg_clave)
        self.ids.error_login.text = ""
        if not (ok_rut and ok_clave):
            return

        usuario = app.buscar_usuario(campo_rut.text, campo_clave.text)
        if usuario is None:
            self.ids.error_login.text = "RUT o contraseña incorrectos"
            return
        self.limpiar()
        app.iniciar_sesion(usuario)

    # [3.3] Deja el formulario en blanco (al cerrar sesión)
    def limpiar(self):
        for campo in (self.ids.rut, self.ids.clave):
            campo.text = ""
            marcar_campo(campo, True, "")
        self.ids.error_login.text = ""


# =====================================================================
# [SECCIÓN 4] PANTALLA 1 · HOME (suma diaria + agregar microgasto)
# =====================================================================
class HomeScreen(MDScreen):
    menu_categorias = None

    # [4.1] Selector de categorías (diálogo con un botón por categoría)
    def abrir_menu_categorias(self):
        app = MDApp.get_running_app()
        contenido = MDBoxLayout(orientation="vertical", adaptive_height=True, spacing=dp(6),
                                padding=[0, dp(8), 0, 0])
        for nombre, info in CATEGORIAS.items():
            contenido.add_widget(MDRectangleFlatIconButton(
                text=nombre, icon=info["icono"], size_hint_x=1,
                theme_text_color="Custom", text_color=app.c_texto,
                theme_icon_color="Custom", icon_color=get_color_from_hex(info["color"]),
                line_color=app.c_borde,
                on_release=lambda _b, n=nombre: self.elegir_categoria(n)))
        self.menu_categorias = MDDialog(title="Elige una categoría", type="custom",
                                        content_cls=contenido)
        self.menu_categorias.bind(on_dismiss=lambda *_: setattr(self.ids.categoria, "focus", False))
        self.menu_categorias.open()

    def elegir_categoria(self, nombre: str):
        self.ids.categoria.text = nombre
        marcar_campo(self.ids.categoria, True, "")
        self.menu_categorias.dismiss()

    # [4.2] Botón AGREGAR: valida y registra el microgasto manual
    def agregar_gasto(self):
        campo_desc, campo_monto, campo_cat = self.ids.descripcion, self.ids.monto, self.ids.categoria

        ok_desc, msg_desc = validar_descripcion(campo_desc.text)
        ok_monto, msg_monto, valor = validar_monto(campo_monto.text)
        ok_cat, msg_cat = validar_categoria(campo_cat.text)
        marcar_campo(campo_desc, ok_desc, msg_desc)
        marcar_campo(campo_monto, ok_monto, msg_monto)
        marcar_campo(campo_cat, ok_cat, msg_cat)
        if not (ok_desc and ok_monto and ok_cat):
            return

        MDApp.get_running_app().agregar_gasto_manual(campo_desc.text, valor, campo_cat.text)
        for campo in (campo_desc, campo_monto, campo_cat):
            campo.text = ""
            marcar_campo(campo, True, "")
            campo.focus = False

    # [4.3] Lista "Microgastos de hoy"
    def refrescar_lista(self, gastos: list):
        contenedor = self.ids.lista_hoy
        contenedor.clear_widgets()
        if not gastos:
            contenedor.add_widget(TextoVacio(text="Aún no registras microgastos hoy."))
            return
        for gasto in gastos[:6]:
            contenedor.add_widget(FilaGasto.desde_gasto(gasto))
        if len(gastos) > 6:
            contenedor.add_widget(TextoVacio(text=f"+ {len(gastos) - 6} más en Gráficos"))


# =====================================================================
# [SECCIÓN 5] PANTALLA 2 · GRÁFICOS (diario / semanal / mensual)
# =====================================================================
class GraficosScreen(MDScreen):
    periodo = StringProperty("diario")
    txt_titulo = StringProperty("")
    txt_total = StringProperty("")
    txt_detalle = StringProperty("")
    txt_titulo_barras = StringProperty("")

    # [5.1] Cada vez que se entra a la pantalla se recalcula todo
    def on_pre_enter(self, *_):
        self.actualizar()

    def cambiar_periodo(self, periodo: str):
        self.periodo = periodo
        self.actualizar()

    # [5.2] Recalcula totales, gráficos, leyenda y lista
    def actualizar(self):
        gestor = MDApp.get_running_app().gestor
        if gestor is None:
            return
        lista = gestor.gastos_por_periodo(self.periodo)
        total = gestor.total(lista)
        hoy = datetime.now()

        # -- Textos de resumen
        if self.periodo == "diario":
            self.txt_titulo = f"Hoy · {fecha_larga(hoy)}"
            self.txt_titulo_barras = "Cada microgasto de hoy (por hora)"
            dias = 1
        elif self.periodo == "semanal":
            self.txt_titulo = "Esta semana (lunes a hoy)"
            self.txt_titulo_barras = "Total por día de la semana"
            dias = hoy.weekday() + 1
        else:
            self.txt_titulo = f"Mes de {MESES[hoy.month - 1]}"
            self.txt_titulo_barras = "Total por día del mes"
            dias = hoy.day
        self.txt_total = formato_pesos(total)
        promedio = formato_pesos(total // len(lista)) if lista else "$0"
        self.txt_detalle = f"{len(lista)} microgastos · promedio {promedio} c/u"
        if dias > 1:
            self.txt_detalle += f" · {formato_pesos(total // dias)} por día"

        # -- Gráfico de barras
        barras = self.ids.barras
        barras.etiqueta_cada = 5 if self.periodo == "mensual" else 1
        barras.datos = gestor.serie_por_periodo(self.periodo)

        # -- Gráfico de dona + leyenda
        categorias = gestor.por_categoria(lista)
        self.ids.dona.datos = [[c, v, CATEGORIAS[c]["color"]] for c, v in categorias.items()]
        self.ids.dona.texto_centro = formato_pesos(total)
        leyenda = self.ids.leyenda
        leyenda.clear_widgets()
        if not categorias:
            leyenda.add_widget(TextoVacio(text="Sin datos"))
        for nombre, valor in categorias.items():
            leyenda.add_widget(FilaLeyenda(
                texto=nombre,
                valor_txt=f"{formato_pesos(valor)} · {round(valor * 100 / total)}%",
                color=get_color_from_hex(CATEGORIAS[nombre]["color"])))

        # -- Lista detallada (toca una fila para ver su boleta)
        contenedor = self.ids.lista
        contenedor.clear_widgets()
        if not lista:
            contenedor.add_widget(TextoVacio(text="No hay microgastos en este periodo."))
        for gasto in lista[:60]:
            contenedor.add_widget(FilaGasto.desde_gasto(gasto, mostrar_fecha=self.periodo != "diario"))


# =====================================================================
# [SECCIÓN 6] APLICACIÓN PRINCIPAL
# =====================================================================
class MoneyFanApp(MDApp):

    # -----------------------------------------------------------------
    # [6.1] PROPIEDADES QUE USA LA INTERFAZ (interfaz.kv las "escucha")
    # Al cambiar su valor, la pantalla se actualiza sola.
    # -----------------------------------------------------------------
    nombre_usuario = StringProperty("")
    txt_total_hoy = StringProperty("$0")
    txt_cant_hoy = StringProperty("")
    txt_fecha_hoy = StringProperty("")
    txt_total_semana = StringProperty("$0")
    txt_total_mes = StringProperty("$0")
    txt_estado_gmail = StringProperty("")
    txt_proximo_aviso = StringProperty("")
    gmail_conectado = BooleanProperty(False)

    # Colores de la paleta disponibles en interfaz.kv como app.c_<nombre>
    c_primario = ColorProperty(hex_a_color("primario"))
    c_secundario = ColorProperty(hex_a_color("secundario"))
    c_acento = ColorProperty(hex_a_color("acento"))
    c_acento2 = ColorProperty(hex_a_color("acento2"))
    c_fondo = ColorProperty(hex_a_color("fondo"))
    c_tarjeta = ColorProperty(hex_a_color("tarjeta"))
    c_borde = ColorProperty(hex_a_color("borde"))
    c_texto = ColorProperty(hex_a_color("texto"))
    c_texto_suave = ColorProperty(hex_a_color("texto_suave"))
    c_error = ColorProperty(hex_a_color("error"))

    # Filtros de escritura usados por los campos de texto en interfaz.kv
    filtro_digitos = staticmethod(filtro_digitos)
    filtro_rut = staticmethod(filtro_rut)

    # -----------------------------------------------------------------
    # [6.2] CONSTRUCCIÓN DE LA APP
    # -----------------------------------------------------------------
    def build(self):
        self.title = "MoneyFan"
        self.theme_cls.theme_style = "Light"
        self.theme_cls.primary_palette = "BlueGray"
        self.theme_cls.accent_palette = "Teal"
        Window.clearcolor = self.c_fondo

        # Servicios (lógica sin interfaz)
        with open(RUTA_USUARIOS, encoding="utf-8") as archivo:
            self.usuarios = json.load(archivo)["usuarios"]
        self.gmail = GmailSimulado(RUTA_CORREOS)
        self.gestor = None
        self.usuario = None
        self.ultima_sync = None
        self.dialogo = None
        self.notificador = NotificadorDiario(self.mostrar_aviso_diario, HORA_AVISO, MINUTO_AVISO)

        return Builder.load_file(RUTA_KV)

    # -----------------------------------------------------------------
    # [6.3] NAVEGACIÓN ENTRE PANTALLAS
    # -----------------------------------------------------------------
    def ir_a(self, nombre: str):
        sm = self.root.ids.sm
        orden = ["login", "home", "graficos"]
        sm.transition.direction = "left" if orden.index(nombre) > orden.index(sm.current) else "right"
        sm.current = nombre

    def pantalla(self, nombre: str):
        return self.root.ids.sm.get_screen(nombre)

    # -----------------------------------------------------------------
    # [6.4] SESIÓN DE USUARIO (login / logout)
    # -----------------------------------------------------------------
    def buscar_usuario(self, rut: str, clave: str):
        for usuario in self.usuarios:
            if usuario["rut"].upper() == rut.upper() and usuario["clave"] == clave:
                return usuario
        return None

    def iniciar_sesion(self, usuario: dict):
        self.usuario = usuario
        self.nombre_usuario = usuario["nombre"]
        self.gestor = GestorGastos(usuario["rut"], RUTA_GASTOS_MANUALES)

        # Si este usuario ya había conectado Gmail, se reconecta solo
        correo = self._leer_estado().get(usuario["rut"], {}).get("gmail")
        if correo and self.gmail.conectar(correo)[0]:
            self.gestor.importar_boletas(self.gmail.obtener_boletas())
            self.ultima_sync = datetime.now()

        self._actualizar_estado_gmail()
        self.actualizar_resumen()
        self.notificador.iniciar()
        self._evento_aviso = Clock.schedule_interval(self._actualizar_texto_aviso, 30)
        self.ir_a("home")

    def cerrar_sesion(self):
        self.notificador.detener()
        if getattr(self, "_evento_aviso", None):
            self._evento_aviso.cancel()
        self.gmail.desconectar()
        self.gestor = None
        self.usuario = None
        self.pantalla("login").limpiar()
        self.ir_a("login")

    # -----------------------------------------------------------------
    # [6.5] ESTADO PERSISTENTE (qué Gmail tiene conectado cada RUT)
    # -----------------------------------------------------------------
    def _leer_estado(self) -> dict:
        try:
            with open(RUTA_ESTADO, encoding="utf-8") as archivo:
                return json.load(archivo)
        except (OSError, json.JSONDecodeError):
            return {}

    def _guardar_estado(self, correo):
        estado = self._leer_estado()
        estado.setdefault(self.usuario["rut"], {})["gmail"] = correo
        with open(RUTA_ESTADO, "w", encoding="utf-8") as archivo:
            json.dump(estado, archivo, ensure_ascii=False, indent=2)

    # -----------------------------------------------------------------
    # [6.6] RESUMEN (recuento diario / semanal / mensual del Home)
    # -----------------------------------------------------------------
    def actualizar_resumen(self):
        if self.gestor is None:
            return
        hoy = self.gestor.gastos_del_dia()
        self.txt_total_hoy = formato_pesos(self.gestor.total(hoy))
        self.txt_cant_hoy = f"{len(hoy)} microgasto{'s' if len(hoy) != 1 else ''} registrado{'s' if len(hoy) != 1 else ''} hoy"
        self.txt_fecha_hoy = fecha_larga(datetime.now())
        self.txt_total_semana = formato_pesos(self.gestor.total_semana())
        self.txt_total_mes = formato_pesos(self.gestor.total_mes())
        self._actualizar_texto_aviso()
        self.pantalla("home").refrescar_lista(hoy)
        if self.root.ids.sm.current == "graficos":
            self.pantalla("graficos").actualizar()

    def _actualizar_texto_aviso(self, *_):
        self.txt_proximo_aviso = self.notificador.texto_proximo_aviso()

    # -----------------------------------------------------------------
    # [6.7] GMAIL SIMULADO (conectar, sincronizar, desconectar)
    # -----------------------------------------------------------------
    def _actualizar_estado_gmail(self):
        self.gmail_conectado = self.gmail.conectado
        if self.gmail.conectado:
            hora = self.ultima_sync.strftime("%H:%M") if self.ultima_sync else "--:--"
            self.txt_estado_gmail = f"{self.gmail.cuenta}\nÚltima sincronización: {hora}"
        else:
            self.txt_estado_gmail = "Conecta tu Gmail para importar tus boletas electrónicas automáticamente."

    def accion_gmail(self):
        if self.gmail.conectado:
            self.sincronizar_gmail()
        else:
            self.abrir_dialogo_gmail()

    def abrir_dialogo_gmail(self):
        campo = MDTextField(hint_text="tu.correo@gmail.com", mode="rectangle",
                            helper_text_mode="on_error", icon_left="email-outline")
        contenido = MDBoxLayout(campo, orientation="vertical", size_hint_y=None,
                                height=dp(84), padding=[0, dp(12), 0, 0])
        self.dialogo = MDDialog(
            title="Conectar Gmail",
            type="custom",
            content_cls=contenido,
            buttons=[
                MDFlatButton(text="CANCELAR", theme_text_color="Custom", text_color=self.c_secundario,
                             on_release=lambda *_: self.dialogo.dismiss()),
                MDRaisedButton(text="CONECTAR", md_bg_color=self.c_primario,
                               on_release=lambda *_: self._confirmar_gmail(campo)),
            ],
        )
        self.dialogo.open()

    def _confirmar_gmail(self, campo):
        ok, mensaje = self.gmail.conectar(campo.text)
        if not ok:
            marcar_campo(campo, False, mensaje)
            return
        self.dialogo.dismiss()
        self._guardar_estado(self.gmail.cuenta)
        self.sincronizar_gmail()

    def sincronizar_gmail(self):
        """Simula la lectura de la bandeja (pequeña espera para que se vea real)."""
        self.dialogo = MDDialog(title="Sincronizando…",
                                text=f"Buscando boletas electrónicas en {self.gmail.cuenta}")
        self.dialogo.open()
        Clock.schedule_once(self._terminar_sincronizacion, 1.2)

    def _terminar_sincronizacion(self, _dt):
        nuevas = self.gestor.importar_boletas(self.gmail.obtener_boletas())
        self.ultima_sync = datetime.now()
        self.dialogo.dismiss()
        self._actualizar_estado_gmail()
        self.actualizar_resumen()
        toast(f"{nuevas} boleta{'s' if nuevas != 1 else ''} nueva{'s' if nuevas != 1 else ''} importada{'s' if nuevas != 1 else ''}")

    def desconectar_gmail(self):
        self.gmail.desconectar()
        self.gestor.quitar_boletas_gmail()
        self._guardar_estado(None)
        self.ultima_sync = None
        self._actualizar_estado_gmail()
        self.actualizar_resumen()
        toast("Gmail desconectado")

    # -----------------------------------------------------------------
    # [6.8] MICROGASTO MANUAL (compras en efectivo)
    # -----------------------------------------------------------------
    def agregar_gasto_manual(self, descripcion: str, monto: int, categoria: str):
        gasto = self.gestor.agregar_manual(descripcion, monto, categoria)
        self.actualizar_resumen()
        toast(f"Agregado: {gasto['descripcion']} {formato_pesos(gasto['monto'])}")

    # -----------------------------------------------------------------
    # [6.9] DOCUMENTO DE RESPALDO (boleta visible al tocar un gasto)
    # -----------------------------------------------------------------
    def mostrar_boleta(self, gasto: dict):
        fecha = gasto["fecha"].strftime("%d/%m/%Y %H:%M")
        if gasto["origen"] == "gmail":
            lineas = [
                f"[b]{gasto['comercio']}[/b]",
                f"Boleta electrónica N° {gasto['folio']}",
                f"Fecha: {fecha}",
                "",
            ]
            for d in gasto["detalle"]:
                lineas.append(f"{d['cantidad']} x {d['item']}  {formato_pesos(d['precio_unitario'])}")
            lineas += ["", f"[b]TOTAL: {formato_pesos(gasto['monto'])}[/b]", "",
                       f"[size=12sp]Recibido de: {gasto['remitente']}[/size]"]
            titulo = "Boleta electrónica"
        else:
            lineas = [
                f"[b]{gasto['descripcion']}[/b]",
                f"Categoría: {gasto['categoria']}",
                f"Fecha: {fecha}",
                "",
                f"[b]TOTAL: {formato_pesos(gasto['monto'])}[/b]",
                "",
                "[size=12sp]Registro manual (compra en efectivo, sin boleta electrónica).[/size]",
            ]
            titulo = "Registro manual"
        self.dialogo = MDDialog(
            title=titulo, text="\n".join(lineas),
            buttons=[MDRaisedButton(text="CERRAR", md_bg_color=self.c_primario,
                                    on_release=lambda *_: self.dialogo.dismiss())])
        self.dialogo.open()

    # -----------------------------------------------------------------
    # [6.10] AVISO DIARIO DE LAS 20:00
    # -----------------------------------------------------------------
    def mostrar_aviso_diario(self):
        if self.gestor is None:
            return
        hoy = self.gestor.gastos_del_dia()
        total = formato_pesos(self.gestor.total(hoy))
        mensaje = (f"Hoy gastaste {total} en {len(hoy)} microgasto{'s' if len(hoy) != 1 else ''}.\n"
                   f"Semana: {self.txt_total_semana}  ·  Mes: {self.txt_total_mes}")
        NotificadorDiario.notificar_sistema("MoneyFan · Resumen del día", mensaje)

        def ver_graficos(*_):
            self.dialogo.dismiss()
            self.ir_a("graficos")

        self.dialogo = MDDialog(
            title="Resumen del día · 20:00", text=mensaje,
            buttons=[
                MDFlatButton(text="VER GRÁFICOS", theme_text_color="Custom",
                             text_color=self.c_secundario, on_release=ver_graficos),
                MDRaisedButton(text="ENTENDIDO", md_bg_color=self.c_primario,
                               on_release=lambda *_: self.dialogo.dismiss()),
            ])
        self.dialogo.open()


# =====================================================================
# [SECCIÓN 7] ARRANQUE
# =====================================================================
if __name__ == "__main__":
    # Asegura que las importaciones relativas funcionen desde cualquier carpeta
    os.chdir(os.path.dirname(os.path.abspath(__file__)))
    MoneyFanApp().run()
