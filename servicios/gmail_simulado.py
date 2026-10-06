"""
=====================================================================
 MoneyFan · servicios/gmail_simulado.py
---------------------------------------------------------------------
 [QUÉ HACE ESTE ARCHIVO]
 SIMULA la conexión a Gmail para leer boletas electrónicas.
 En esta demo NO hay conexión real: los correos están "hardcodeados"
 en data/correos_simulados.json. Cada correo indica cuántos días
 atrás llegó ('dias_atras') y a qué hora, así la demo siempre
 muestra compras recientes sin importar el día en que se ejecute.

 [EN UNA VERSIÓN REAL] aquí iría la API de Gmail (OAuth2 + búsqueda
 de correos con asunto "Boleta electrónica") y un parser del XML/PDF
 del documento tributario.
=====================================================================
"""
import json
from datetime import datetime, timedelta

from servicios.validaciones import validar_gmail


class GmailSimulado:
    # -----------------------------------------------------------
    # [SECCIÓN 1] CONSTRUCTOR: carga la bandeja simulada
    # -----------------------------------------------------------
    def __init__(self, ruta_correos: str):
        with open(ruta_correos, encoding="utf-8") as archivo:
            self._correos = json.load(archivo)["correos"]
        self.cuenta = None  # correo Gmail "conectado"

    # -----------------------------------------------------------
    # [SECCIÓN 2] CONECTAR / DESCONECTAR
    # Valida el formato del correo y lo deja como cuenta activa.
    # -----------------------------------------------------------
    def conectar(self, correo: str):
        ok, mensaje = validar_gmail(correo)
        if ok:
            self.cuenta = correo.strip().lower()
        return ok, mensaje

    def desconectar(self):
        self.cuenta = None

    @property
    def conectado(self) -> bool:
        return self.cuenta is not None

    # -----------------------------------------------------------
    # [SECCIÓN 3] LEER BOLETAS
    # Convierte cada correo en un "gasto" con fecha real calculada.
    # Los correos de HOY cuya hora aún no llega se ignoran (todavía
    # no han "llegado" a la bandeja).
    # -----------------------------------------------------------
    def obtener_boletas(self, ahora: datetime = None) -> list:
        if not self.conectado:
            return []
        ahora = ahora or datetime.now()
        boletas = []
        for correo in self._correos:
            hora, minuto = map(int, correo["hora"].split(":"))
            dia = (ahora - timedelta(days=correo["dias_atras"])).date()
            fecha = datetime(dia.year, dia.month, dia.day, hora, minuto)
            if fecha > ahora:
                continue  # aún no llega este correo
            boletas.append({
                "id": correo["id"],
                "fecha": fecha,
                "descripcion": correo["detalle"][0]["item"],
                "comercio": correo["comercio"],
                "categoria": correo["categoria"],
                "monto": int(correo["monto"]),
                "origen": "gmail",
                # Datos del "documento de respaldo" (se muestran al tocar el gasto)
                "remitente": correo["remitente"],
                "asunto": correo["asunto"],
                "folio": correo["folio"],
                "detalle": correo["detalle"],
            })
        return boletas
