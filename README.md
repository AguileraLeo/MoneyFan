# MoneyFan · Demo de control de microgastos

App demo (solo **frontend + UX/UI**) hecha con **Python + Kivy + KivyMD + lenguaje kv + JSON**.
Registra y consulta *microgastos* (galletas en el kiosco, un desodorante en la farmacia…) con
sus documentos de respaldo visibles. **No usa base de datos ni backend**: Gmail y las compras
están simulados (hardcodeados) en archivos JSON.

## Cómo ejecutarla en Visual Studio Code

1. Descomprime el `.zip` y abre la carpeta `MoneyFan` en VS Code (*Archivo → Abrir carpeta*).
2. Abre una terminal (*Terminal → Nueva terminal*) y crea un entorno virtual:
   ```bash
   # Windows
   python -m venv .venv
   .venv\Scripts\activate
   # macOS / Linux
   python3 -m venv .venv
   source .venv/bin/activate
   ```
3. Instala las dependencias:
   ```bash
   pip install -r requirements.txt
   ```
4. Ejecuta la app con **F5** (configuración "MoneyFan: ejecutar demo") o con:
   ```bash
   python main.py
   ```

> Requiere Python 3.10 a 3.13. Si VS Code pregunta por el intérprete, elige el de `.venv`.

## Credenciales demo

| RUT            | Contraseña |
|----------------|------------|
| 12.345.678-9   | 123456     |
| 11.111.111-1   | abc123     |

Para conectar Gmail usa cualquier correo `@gmail.com` (ej: `leo.demo@gmail.com`).

## Qué hace

| # | Requisito | Dónde está |
|---|-----------|------------|
| 1 | "Conexión" a Gmail para leer boletas electrónicas (simulada) | `servicios/gmail_simulado.py` + `data/correos_simulados.json` |
| 2 | Recuento **diario, semanal y mensual** | `servicios/gestor_gastos.py` |
| 3 | Aviso a las **20:00** con lo gastado en el día | `servicios/notificador.py` (botón 🔔 del Home para probarlo ya) |
| 4 | Agregar microgasto **manual** (efectivo) | Pantalla 1, tarjeta "Agregar microgasto manual" |
| 5 | **Pantalla 1** (Home: suma diaria + agregar) y **Pantalla 2** (gráficos) | `interfaz.kv` secciones 3 y 4 |
| 6 | Ventana simulando un **teléfono** (400×800 + barra de estado) | `config.py` sección 6, `BarraEstado` |
| 7 | Paleta **suave y formal** (azul petróleo, salvia, arena) | `config.py` sección 2 |
| — | Ingreso con **RUT** (12.345.678-9) y **clave de 6 caracteres** | `LoginScreen` + `servicios/validaciones.py` |
| — | Documento de respaldo visible (toca cualquier gasto) | `MoneyFanApp.mostrar_boleta` |

### Restricciones (validaciones)
- Todos los campos son obligatorios.
- RUT con formato `12.345.678-9` (se formatea solo al salir del campo; solo acepta dígitos, `.`, `-`, `K`).
  Para exigir dígito verificador real, cambia `VALIDAR_DIGITO_VERIFICADOR = True` en `config.py`.
- Contraseña de exactamente 6 caracteres, sin espacios.
- Monto: solo **enteros positivos** (el campo bloquea letras, puntos y signos), entre $1 y $100.000.
- Descripción entre 3 y 30 caracteres; categoría elegida de la lista.
- Correo Gmail con formato `algo@gmail.com`.

## Estructura del proyecto

```
MoneyFan/
├── main.py                  # Punto de entrada: pantallas + clase MoneyFanApp
├── interfaz.kv              # Diseño visual (lenguaje kv + KivyMD)
├── config.py                # Paleta, rutas, categorías y reglas
├── servicios/
│   ├── validaciones.py      # Restricciones de RUT, clave, monto, etc.
│   ├── gmail_simulado.py    # Bandeja de Gmail simulada
│   ├── gestor_gastos.py     # Totales y series para gráficos
│   └── notificador.py       # Aviso diario de las 20:00
├── widgets/
│   └── graficos.py          # Gráfico de barras y de dona (canvas de Kivy)
├── data/
│   ├── usuarios.json            # Usuarios demo
│   ├── correos_simulados.json   # 66 boletas electrónicas simuladas
│   └── gastos_manuales.json     # Se llena al agregar gastos manuales
└── .vscode/                 # Configuración para ejecutar con F5
```

Cada archivo tiene etiquetas `[SECCIÓN N]` que explican parte por parte lo que hace el código.

## Notas de la demo
- Las boletas simuladas usan `dias_atras` relativo a la fecha actual, así siempre hay datos recientes.
  Las de hoy aparecen solo cuando su hora ya pasó (como si fueran llegando a la bandeja).
- El aviso de las 20:00 funciona mientras la app esté abierta. Si `plyer` está instalado,
  también intenta mostrar una notificación del sistema.
- Para reiniciar la demo, deja `data/gastos_manuales.json` como `{}` y borra `data/estado_app.json`.
