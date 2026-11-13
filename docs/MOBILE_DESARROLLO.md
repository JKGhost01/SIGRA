# Estado del cliente Android

Android está en construcción y se mantendrá en un repositorio independiente. No se
incluye ni se compila código móvil en SIGRA web/backend y no se publica un enlace a un
repositorio inexistente.

El backend ofrece [OpenAPI](api/openapi.yaml) y [la API de red](API_RED.md). El cliente
futuro debe conservar su token de dispositivo, enviarlo para un nuevo registro del
mismo identificador y reemplazarlo al recibir una rotación válida. Perder el token no
autoriza a tomar un identificador registrado por otro dispositivo.

Las estimaciones y notificaciones son experimentales, sin garantía de anticipación,
precisión ni sustitución de autoridades. Las pruebas de simulación no publican alertas
reales y no equivalen a validación en campo.
