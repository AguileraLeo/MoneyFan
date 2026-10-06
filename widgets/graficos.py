"""
=====================================================================
 MoneyFan · widgets/graficos.py
---------------------------------------------------------------------
 [QUÉ HACE ESTE ARCHIVO]
 Define 2 gráficos dibujados "a mano" con el canvas de Kivy, así no
 se necesitan librerías extra (matplotlib, garden, etc.):
   - GraficoBarras : una barra por microgasto / día.
   - GraficoDona   : distribución del gasto por categoría.
 Ambos se redibujan solos cuando cambian sus datos o su tamaño.
=====================================================================
"""

from kivy.clock import Clock
from kivy.core.text import Label as CoreLabel
from kivy.graphics import Color, Ellipse, Line, Rectangle, RoundedRectangle
from kivy.metrics import dp, sp
from kivy.properties import ColorProperty, ListProperty, NumericProperty, StringProperty
from kivy.uix.widget import Widget
from kivy.utils import get_color_from_hex


# ---------------------------------------------------------------
# [SECCIÓN 1] FUNCIONES DE APOYO
# ---------------------------------------------------------------
def _textura_texto(texto, tamano=10, color=(0.4, 0.45, 0.5, 1), negrita=False):
    """Crea una 'imagen' de texto que se puede pintar en el canvas."""
    etiqueta = CoreLabel(text=str(texto), font_size=sp(tamano), color=color, bold=negrita)
    etiqueta.refresh()
    return etiqueta.texture


def _monto_corto(valor):
    """1500 -> '1,5k' ; 900 -> '900' (para que quepa sobre la barra)."""
    if valor >= 1000:
        texto = f"{valor / 1000:.1f}".rstrip("0").rstrip(".").replace(".", ",")
        return texto + "k"
    return str(int(valor))


# ---------------------------------------------------------------
# [SECCIÓN 2] GRÁFICO DE BARRAS
# datos = [[etiqueta, valor], ...]
# ---------------------------------------------------------------
class GraficoBarras(Widget):
    datos = ListProperty([])
    color_barra = ColorProperty(get_color_from_hex("#2F4858"))
    color_destacado = ColorProperty(get_color_from_hex("#C9A66B"))  # barra más alta
    color_texto = ColorProperty(get_color_from_hex("#6B7F8A"))
    color_guia = ColorProperty(get_color_from_hex("#E1E6EA"))
    etiqueta_cada = NumericProperty(1)        # mostrar 1 de cada N etiquetas (útil en mensual)
    texto_vacio = StringProperty("Sin microgastos en este periodo")

    def __init__(self, **kwargs):
        super().__init__(**kwargs)
        # [2.1] Clock.create_trigger agrupa varios cambios en un solo redibujo
        self._redibujar = Clock.create_trigger(self.dibujar, 0)
        self.bind(pos=self._redibujar, size=self._redibujar, datos=self._redibujar,
                  etiqueta_cada=self._redibujar)

    def dibujar(self, *_):
        self.canvas.clear()
        x0, y0, ancho, alto = self.x, self.y, self.width, self.height
        margen_inf, margen_sup = dp(20), dp(18)
        alto_util = max(alto - margen_inf - margen_sup, 1)

        with self.canvas:
            # [2.2] Líneas guía horizontales
            Color(*self.color_guia)
            for i in range(4):
                y = y0 + margen_inf + alto_util * i / 3
                Line(points=[x0, y, x0 + ancho, y], width=1)

            # [2.3] Mensaje cuando no hay datos
            if not self.datos or max(v for _, v in self.datos) == 0:
                tex = _textura_texto(self.texto_vacio, 12, self.color_texto)
                Color(1, 1, 1, 1)
                Rectangle(texture=tex, size=tex.size,
                          pos=(x0 + (ancho - tex.width) / 2, y0 + (alto - tex.height) / 2))
                return

            # [2.4] Barras: ancho proporcional a la cantidad de datos
            n = len(self.datos)
            maximo = max(v for _, v in self.datos)
            espacio = ancho / n
            ancho_barra = min(espacio * 0.6, dp(34))
            mostrar_montos = n <= 10  # en el mensual no caben los montos

            for i, (etiqueta, valor) in enumerate(self.datos):
                cx = x0 + espacio * i + espacio / 2
                alto_barra = alto_util * (valor / maximo) if maximo else 0
                Color(*(self.color_destacado if valor == maximo else self.color_barra))
                if alto_barra > 0:
                    RoundedRectangle(pos=(cx - ancho_barra / 2, y0 + margen_inf),
                                     size=(ancho_barra, max(alto_barra, dp(3))),
                                     radius=[dp(5), dp(5), dp(2), dp(2)])
                # [2.5] Monto sobre la barra
                if mostrar_montos and valor > 0:
                    tex = _textura_texto(_monto_corto(valor), 9, self.color_texto, True)
                    Color(1, 1, 1, 1)
                    Rectangle(texture=tex, size=tex.size,
                              pos=(cx - tex.width / 2, y0 + margen_inf + alto_barra + dp(2)))
                # [2.6] Etiqueta del eje X (hora / día)
                if i % max(int(self.etiqueta_cada), 1) == 0 or i == n - 1:
                    tex = _textura_texto(etiqueta, 9, self.color_texto)
                    Color(1, 1, 1, 1)
                    Rectangle(texture=tex, size=tex.size, pos=(cx - tex.width / 2, y0 + dp(2)))


# ---------------------------------------------------------------
# [SECCIÓN 3] GRÁFICO DE DONA (por categoría)
# datos = [[etiqueta, valor, color_hex], ...]
# ---------------------------------------------------------------
class GraficoDona(Widget):
    datos = ListProperty([])
    texto_centro = StringProperty("")
    color_fondo = ColorProperty(get_color_from_hex("#FFFFFF"))
    color_texto = ColorProperty(get_color_from_hex("#263238"))
    color_vacio = ColorProperty(get_color_from_hex("#E1E6EA"))

    def __init__(self, **kwargs):
        super().__init__(**kwargs)
        self._redibujar = Clock.create_trigger(self.dibujar, 0)
        self.bind(pos=self._redibujar, size=self._redibujar, datos=self._redibujar,
                  texto_centro=self._redibujar)

    def dibujar(self, *_):
        self.canvas.clear()
        diametro = min(self.width, self.height) - dp(6)
        if diametro <= 0:
            return
        cx, cy = self.center
        pos = (cx - diametro / 2, cy - diametro / 2)
        total = sum(d[1] for d in self.datos)

        with self.canvas:
            # [3.1] Sectores: cada categoría ocupa un ángulo proporcional
            if total <= 0:
                Color(*self.color_vacio)
                Ellipse(pos=pos, size=(diametro, diametro))
            else:
                angulo = 0
                for _, valor, color_hex in self.datos:
                    barrido = 360 * valor / total
                    Color(*get_color_from_hex(color_hex))
                    Ellipse(pos=pos, size=(diametro, diametro),
                            angle_start=angulo, angle_end=angulo + barrido)
                    angulo += barrido

            # [3.2] Círculo interior (convierte el "pie" en "dona")
            interior = diametro * 0.62
            Color(*self.color_fondo)
            Ellipse(pos=(cx - interior / 2, cy - interior / 2), size=(interior, interior))

            # [3.3] Texto central (total del periodo)
            if self.texto_centro:
                tex = _textura_texto(self.texto_centro, 12, self.color_texto, True)
                Color(1, 1, 1, 1)
                Rectangle(texture=tex, size=tex.size, pos=(cx - tex.width / 2, cy - tex.height / 2))
