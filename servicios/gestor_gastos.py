"""
=====================================================================
 MoneyFan · servicios/gestor_gastos.py
---------------------------------------------------------------------
 [QUÉ HACE ESTE ARCHIVO]
 Es el "cerebro" de los números:
   - Guarda la lista de microgastos (de Gmail + manuales).
   - Suma los totales DIARIO, SEMANAL y MENSUAL.
   - Prepara las series de datos para los gráficos.
   - Persiste los gastos manuales en un archivo JSON (sin base de datos).
=====================================================================
"""
import json
import os
import uuid
from datetime import datetime, timedelta

from config import DIAS_CORTOS


def formato_pesos(valor: int) -> str:
    """[UTILIDAD] 12345 -> '$12.345' (formato chileno)."""
    return "$" + f"{int(valor):,}".replace(",", ".")


class GestorGastos:
    # -----------------------------------------------------------
    # [SECCIÓN 1] CONSTRUCTOR
    # Cada usuario (RUT) tiene su propia lista de gastos manuales.
    # -----------------------------------------------------------
    def __init__(self, rut: str, ruta_manuales: str):
        self.rut = rut
        self.ruta_manuales = ruta_manuales
        self.gastos = []           # Lista de diccionarios (todos los gastos)
        self._cargar_manuales()

    # -----------------------------------------------------------
    # [SECCIÓN 2] PERSISTENCIA EN JSON (gastos manuales)
    # El archivo guarda { "rut": [gastos...] }. Las fechas se
    # guardan como texto "AAAA-MM-DD HH:MM".
    # -----------------------------------------------------------
    def _leer_archivo(self) -> dict:
        if not os.path.exists(self.ruta_manuales):
            return {}
        try:
            with open(self.ruta_manuales, encoding="utf-8") as archivo:
                return json.load(archivo)
        except (json.JSONDecodeError, OSError):
            return {}

    def _cargar_manuales(self):
        for g in self._leer_archivo().get(self.rut, []):
            g = dict(g)
            g["fecha"] = datetime.strptime(g["fecha"], "%Y-%m-%d %H:%M")
            self.gastos.append(g)

    def _guardar_manuales(self):
        datos = self._leer_archivo()
        datos[self.rut] = [
            {**g, "fecha": g["fecha"].strftime("%Y-%m-%d %H:%M")}
            for g in self.gastos if g["origen"] == "manual"
        ]
        with open(self.ruta_manuales, "w", encoding="utf-8") as archivo:
            json.dump(datos, archivo, ensure_ascii=False, indent=2)

    # -----------------------------------------------------------
    # [SECCIÓN 3] AGREGAR GASTOS
    # -----------------------------------------------------------
    def agregar_manual(self, descripcion: str, monto: int, categoria: str) -> dict:
        """Registra un microgasto pagado (por ejemplo) en efectivo."""
        gasto = {
            "id": "MAN-" + uuid.uuid4().hex[:8].upper(),
            "fecha": datetime.now().replace(second=0, microsecond=0),
            "descripcion": descripcion.strip(),
            "comercio": "Registro manual",
            "categoria": categoria,
            "monto": int(monto),
            "origen": "manual",
        }
        self.gastos.append(gasto)
        self._guardar_manuales()
        return gasto

    def importar_boletas(self, boletas: list) -> int:
        """Agrega boletas de Gmail evitando duplicados. Devuelve cuántas son nuevas."""
        existentes = {g["id"] for g in self.gastos}
        nuevas = [b for b in boletas if b["id"] not in existentes]
        self.gastos.extend(nuevas)
        return len(nuevas)

    def quitar_boletas_gmail(self):
        """Al desconectar Gmail se quitan sus boletas (los manuales se mantienen)."""
        self.gastos = [g for g in self.gastos if g["origen"] != "gmail"]

    # -----------------------------------------------------------
    # [SECCIÓN 4] FILTROS POR PERIODO
    # Semana = lunes a domingo de la semana actual.
    # Mes    = mes calendario actual.
    # -----------------------------------------------------------
    def _ordenar(self, lista):
        return sorted(lista, key=lambda g: g["fecha"], reverse=True)

    def gastos_del_dia(self, dia=None):
        dia = dia or datetime.now().date()
        return self._ordenar([g for g in self.gastos if g["fecha"].date() == dia])

    def gastos_de_la_semana(self):
        hoy = datetime.now().date()
        lunes = hoy - timedelta(days=hoy.weekday())
        return self._ordenar([g for g in self.gastos if lunes <= g["fecha"].date() <= hoy])

    def gastos_del_mes(self):
        hoy = datetime.now().date()
        return self._ordenar([g for g in self.gastos
                              if g["fecha"].year == hoy.year and g["fecha"].month == hoy.month
                              and g["fecha"].date() <= hoy])

    def gastos_por_periodo(self, periodo: str):
        return {"diario": self.gastos_del_dia,
                "semanal": self.gastos_de_la_semana,
                "mensual": self.gastos_del_mes}[periodo]()

    # -----------------------------------------------------------
    # [SECCIÓN 5] TOTALES (recuento diario / semanal / mensual)
    # -----------------------------------------------------------
    @staticmethod
    def total(lista) -> int:
        return sum(g["monto"] for g in lista)

    def total_dia(self):
        return self.total(self.gastos_del_dia())

    def total_semana(self):
        return self.total(self.gastos_de_la_semana())

    def total_mes(self):
        return self.total(self.gastos_del_mes())

    # -----------------------------------------------------------
    # [SECCIÓN 6] SERIES PARA GRÁFICOS DE BARRAS
    # Cada serie es una lista de [etiqueta, valor].
    # -----------------------------------------------------------
    def serie_diaria(self):
        """Una barra por CADA microgasto de hoy (etiqueta = hora)."""
        lista = sorted(self.gastos_del_dia(), key=lambda g: g["fecha"])
        return [[g["fecha"].strftime("%H:%M"), g["monto"]] for g in lista]

    def serie_semanal(self):
        """Una barra por día de la semana (L M X J V S D)."""
        hoy = datetime.now().date()
        lunes = hoy - timedelta(days=hoy.weekday())
        serie = []
        for i in range(7):
            dia = lunes + timedelta(days=i)
            serie.append([DIAS_CORTOS[i], self.total(self.gastos_del_dia(dia)) if dia <= hoy else 0])
        return serie

    def serie_mensual(self):
        """Una barra por día del mes (del 1 hasta hoy)."""
        hoy = datetime.now().date()
        serie = []
        for d in range(1, hoy.day + 1):
            dia = hoy.replace(day=d)
            serie.append([str(d), self.total(self.gastos_del_dia(dia))])
        return serie

    def serie_por_periodo(self, periodo: str):
        return {"diario": self.serie_diaria,
                "semanal": self.serie_semanal,
                "mensual": self.serie_mensual}[periodo]()

    # -----------------------------------------------------------
    # [SECCIÓN 7] DISTRIBUCIÓN POR CATEGORÍA (gráfico de dona)
    # -----------------------------------------------------------
    def por_categoria(self, lista) -> dict:
        resultado = {}
        for g in lista:
            resultado[g["categoria"]] = resultado.get(g["categoria"], 0) + g["monto"]
        return dict(sorted(resultado.items(), key=lambda kv: kv[1], reverse=True))
