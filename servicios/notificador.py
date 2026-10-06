"""
=====================================================================
 MoneyFan · servicios/notificador.py
---------------------------------------------------------------------
 [QUÉ HACE ESTE ARCHIVO]
 Programa el AVISO DIARIO de las 20:00 hrs.
 Usa el reloj de Kivy (Clock) para revisar la hora cada 15 segundos
 mientras la app está abierta. Cuando llegan las 20:00 ejecuta el
 "callback" (la app muestra un diálogo con lo gastado en el día) y,
 si está instalada la librería opcional 'plyer', también lanza una
 notificación del sistema operativo.

 [NOTA] En un teléfono real esto se haría con un servicio en segundo
 plano / notificación programada del sistema.
=====================================================================
"""
from datetime import datetime, timedelta

from kivy.clock import Clock

# [SECCIÓN 1] Librería opcional para notificaciones nativas del sistema
try:
    from plyer import notification as notificacion_sistema
except Exception:  # plyer no instalado o sin soporte en este sistema
    notificacion_sistema = None


class NotificadorDiario:
    # -----------------------------------------------------------
    # [SECCIÓN 2] CONSTRUCTOR
    # callback: función a ejecutar cuando sea la hora del aviso.
    # -----------------------------------------------------------
    def __init__(self, callback, hora: int = 20, minuto: int = 0):
        self.callback = callback
        self.hora = hora
        self.minuto = minuto
        self.ultimo_aviso = None  # fecha del último aviso (evita repetirlo el mismo día)
        self._evento = None

    # -----------------------------------------------------------
    # [SECCIÓN 3] INICIAR / DETENER LA REVISIÓN PERIÓDICA
    # -----------------------------------------------------------
    def iniciar(self):
        self.detener()
        self._evento = Clock.schedule_interval(self._revisar, 15)

    def detener(self):
        if self._evento:
            self._evento.cancel()
            self._evento = None

    # -----------------------------------------------------------
    # [SECCIÓN 4] REVISIÓN: ¿ya son las 20:00?
    # Se avisa una sola vez por día, dentro de la hora 20:xx
    # (si la app se abre a las 20:30 igual recibe el aviso).
    # -----------------------------------------------------------
    def _revisar(self, _dt):
        ahora = datetime.now()
        es_hora = ahora.hour == self.hora and ahora.minute >= self.minuto
        if es_hora and self.ultimo_aviso != ahora.date():
            self.ultimo_aviso = ahora.date()
            self.callback()

    # -----------------------------------------------------------
    # [SECCIÓN 5] NOTIFICACIÓN NATIVA (opcional)
    # -----------------------------------------------------------
    @staticmethod
    def notificar_sistema(titulo: str, mensaje: str):
        if notificacion_sistema is None:
            return
        try:
            notificacion_sistema.notify(title=titulo, message=mensaje,
                                        app_name="MoneyFan", timeout=8)
        except Exception:
            pass  # algunos sistemas no lo soportan; el diálogo en la app basta

    # -----------------------------------------------------------
    # [SECCIÓN 6] TEXTO "PRÓXIMO AVISO" (se muestra en el Home)
    # -----------------------------------------------------------
    def texto_proximo_aviso(self) -> str:
        ahora = datetime.now()
        objetivo = ahora.replace(hour=self.hora, minute=self.minuto, second=0, microsecond=0)
        if ahora >= objetivo:
            objetivo += timedelta(days=1)
        faltan = objetivo - ahora
        horas, resto = divmod(int(faltan.total_seconds()), 3600)
        minutos = resto // 60
        return f"Próximo aviso a las {self.hora:02d}:{self.minuto:02d} (en {horas} h {minutos} min)"
