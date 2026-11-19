# SIGRA v1.4.0.1-beta.2 · Catálogo unificado y paginación

| Identidad | Valor |
|---|---|
| Versión | `1.4.0.1-beta.2` |
| Build | `20261119.beta2` |
| Fecha | 2026-11-19 |
| Canal | `beta` |
| Rama | `prerelease/1.4.0.1-beta.2` |
| Tag de la pre-release | `v1.4.0.1-beta.2` |
| Base estable | `1.3.1.3`, commit `d1915f19734b06146eebe63c2efab2256ec53797` |
| Contrato API | `v1`, compatible; añade `fuentes` y `source_references` |

Esta entrega se publica como **pre-release** para pruebas. No reemplaza la release
estable ni se integra automáticamente en `main`. La subida al hosting la realiza
el operador; las comprobaciones descritas abajo corresponden al entorno local.

## Qué incluye

- **Un sismo con varias fuentes:** las observaciones se cruzan con el catálogo
  guardado, incluidas agencias tardías y fallos parciales. Se mantiene la fuente
  preferida para los parámetros científicos y las referencias de las otras agencias.
  Las coincidencias ambiguas se conservan separadas.
- **Duplicados existentes como alias:** sus filas originales permanecen para
  auditoría, fuera de las consultas públicas. Las URLs anteriores resuelven al
  evento unificado y los reportes ciudadanos se trasladan en la misma transacción.
- **Paginación:** 20, 50 o 100 sismos por página en listado del mapa e histórico.
  Los filtros y el orden se conservan; las actualizaciones automáticas mantienen
  la página abierta. El mapa representa todos los resultados filtrados. El histórico
  se consulta por páginas en la base y supera el límite anterior de 500 eventos.

- **Mapa principal CesiumJS 1.146:** globo navegable con epicentros, selección de
  eventos y enlace al detalle. Filtros, orden, ubicación opcional, radio,
  reproducción y actualización del catálogo conservan sus datos y contratos.
- **Controles unificados a la derecha:** vistas 3D/2D y cuatro capas —mapa,
  satélite, oscuro y topográfico— con miniaturas, panel plegable, foco visible,
  selección accesible, tema oscuro y adaptación a pantallas pequeñas.
- **Respaldo Leaflet:** cambio manual a 2D y recuperación automática si falla la
  carga inicial de Cesium o se pierde el contexto WebGL. La selección de eventos,
  el radio y la ubicación se conservan al cambiar de motor. Detalle, reportes y
  red continúan utilizando sus mapas anteriores.
- **SGC completo:** `five_days_all.json` sustituye al resumen M2+ por defecto.
  Se incluyen eventos pequeños y se conservan TLS, identidad SIGRA, caché,
  bloqueo e intervalo mínimo de quince minutos por host.
- **Procedencia resistente a fallos parciales:** las referencias históricas de
  otras agencias sobreviven a una actualización de la fuente principal. Los
  valores científicos nuevos siguen actualizándose.
- **Auditoría de solo lectura:** `php tools/audit-source-sync.php` compara las
  últimas respuestas guardadas con el catálogo y las referencias entre agencias.

## Qué representa el globo

Los epicentros se dibujan en la superficie del elipsoide. La profundidad se
conserva como dato científico en la ficha; no se transforma en una posición bajo
la superficie. Esta beta **no añade terreno con relieve, edificios 3D ni Cesium
ion**. No requiere token de ion.

Cesium y sus recursos se cargan desde la versión fijada en `cesium.com`. Las
capas necesitan acceso a OpenStreetMap, Esri u OpenTopoMap. Los créditos de los
proveedores y de Cesium permanecen visibles. Las condiciones y atribuciones
figuran en [THIRD_PARTY_NOTICES.md](../THIRD_PARTY_NOTICES.md).

Un fallo de teselas muestra un aviso y permite elegir otra capa o 2D; no implica
que el catálogo sísmico haya fallado. Si un servidor aplica CSP estricta, debe
permitir los recursos y workers que utiliza Cesium, incluidos workers `blob:`.
Revisa la consola del navegador si el hosting bloquea esos recursos. No se han
modificado sus cabeceras de seguridad desde esta entrega.

## Paquetes de actualización

La pre-release incluye dos ZIP con rutas relativas a la raíz del proyecto:

- `sigra-1.4.0.1-beta.2-desde-1.4.0-beta.1.zip`: cambios desde beta.1,
  commit `e9fb106`.
- `sigra-1.4.0.1-beta.2-desde-1.3.1.3.zip`: actualización acumulativa desde la
  estable 1.3.1.3, commit `d1915f19734b06146eebe63c2efab2256ec53797`.

Cada archivo tiene su checksum SHA-256, una guía y manifiestos de archivos
incluidos, retirados y hashes. El código completo también está disponible en
el tag. Estos ZIP actualizan una instalación existente; no incluyen `.env`,
`vendor/`, `writable/`, bases ni credenciales.

## Subida desde beta.1 o 1.3.1.3

1. Conserva una copia privada del código y de la base, junto con `.env` y los
   datos de `writable/`. Sigue [PRODUCCION.md](PRODUCCION.md).
2. Durante la ventana de actualización, evita servir páginas con el código nuevo
   antes de completar la migración. Copia el paquete adecuado a la misma raíz
   del proyecto, conservando todas las rutas. Revisa `archivos-incluidos.txt` y
   `archivos-retirados.txt`; no extraigas el ZIP dentro de `public/`.
3. **Ejecuta `php spark migrate --all` antes de abrir el mapa.** Esta beta añade
   `20261119000001_AddCanonicalEvent`: columna nullable
   `sismos.canonical_event_id` e índice. La migración es aditiva y no elimina
   registros. Las migraciones ya aplicadas se conservan. No uses reset ni seeds.
4. Conserva `.env`, `vendor/` y `writable/`, especialmente
   `writable/cache/providers`. No hay nuevas dependencias Composer ni compilación
   JavaScript. No borres pausas para forzar consultas a las agencias.
5. Si `.env` fija la versión o el build, actualiza esos overrides:

   ```ini
   sigra.version = '1.4.0.1-beta.2'
   sigra.build = '20261119.beta2'
   sigra.channel = 'beta'
   seismicMap.engine = 'cesium'
   sgc.feedUrl = 'https://archive.sgc.gov.co/feed/v1.0.1/summary/five_days_all.json'
   ```

6. Recarga PHP si OPcache mantiene archivos anteriores. Ambos service workers
   usan `sigra-static-1.4.0.1-beta.2` para renovar los assets del navegador.
7. Abre el mapa o ejecuta la sincronización habitual `php spark sigra:sync`.
   Cuando corresponda actualizar, el catálogo consolidará los duplicados de la
   ventana procesada. Se mantienen los intervalos y pausas de los proveedores.
   Los datos fuera de esa ventana no se reescriben al aplicar la migración.

## Aceptación después de subir

- `/api/health` debe responder con `version: 1.4.0.1-beta.2`,
  `build: 20261119.beta2`, contrato `v1` y base accesible. Acerca de debe mostrar
  el canal **Beta** y la entrada correspondiente del historial.
- Verifica páginas de 20, 50 y 100 registros en listado e histórico, incluido
  el final del catálogo. Comprueba que los filtros y el orden abarcan todos los
  resultados y que la actualización automática conserva la página del listado.
- Abre un sismo con varias agencias y filtra por una fuente secundaria: debe
  aparecer el mismo sismo. Comprueba también una URL anterior de un alias.
- Abre el mapa principal y prueba las dos vistas, las cuatro capas y el panel
  plegable. Cambia fuente, magnitud, búsqueda y orden; reproduce la actividad.
- Comprueba ubicación y radio cuando decidas dar permiso. Verifica que al pasar
  de 3D a 2D se conservan y que el detalle del sismo sigue abriendo.
- Revisa escritorio, pantalla pequeña y tema oscuro. Comprueba que el mapa y
  los créditos siguen utilizables al abrir o cerrar los controles.
- En `/acerca-de#sources-health`, comprueba las fechas y próxima consulta de
  cada fuente. Cambiar de URL SGC no elimina la pausa que ya tenga ese host.
  Cuando venza, el mapa o una sincronización normal podrán incorporar el feed
  completo. «Actualizar estado» solo vuelve a leer el estado guardado.
- Si tienes consola y subiste el auditor, ejecuta `php tools/audit-source-sync.php`.
  No hace consultas externas ni escribe eventos. Si no hay respuesta guardada
  para una URL nueva, espera su próxima sincronización permitida.

Las cantidades dependen del instante, la ventana y los filtros. Las referencias
históricas no demuestran una confirmación nueva de una agencia ausente. Consulta
[el diagnóstico de sincronización](SINCRONIZACION_FUENTES_2026-11-19.md).

## Volver a 2D o a la estable

Para cambiar solo la vista, usa **Mapa 2D**. Para abrirla directamente, utiliza
`/sismos?map=leaflet` —o `/index.php/sismos?map=leaflet` según la instalación—.
Para que sea la predeterminada en este servidor:

```ini
seismicMap.engine = 'leaflet'
```

Esto mantiene las correcciones del catálogo. Para revertir toda la beta,
restaura conjuntamente la copia privada del código y la base previa a esta
actualización, además de sus valores de versión, build y canal si estaban
sobrescritos. Conserva el estado de proveedores y sus pausas. Restaurar solo el
código anterior puede volver a mostrar las filas guardadas como alias; no elimina
la consolidación ni deshace el traslado de reportes. No uses reset para revertir.

## Evidencia local y límites

- Beta.2: suite `bash tools/test-architecture.sh`, PHPStan y pruebas MySQL
  aisladas aprobados. Se probaron fuentes tardías, fallos parciales, revisiones
  científicas, alias, enlaces, evidencia ciudadana e idempotencia.
- Histórico con 525 eventos sintéticos: recorrido completo, tamaño de página,
  filtros, orden estable, alias excluidos y límites vacíos; transacción revertida.
- JavaScript real del listado: paginación, orden global antes de dividir páginas,
  filtros, actualización sin perder página y mapa con todos los resultados.
- Las páginas locales respondieron HTTP 200; revisión visual con Firefox sin
  ventana. Esta captura no acredita por sí sola la carga de teselas ni WebGL.
- Beta.1 ya incluía pruebas Chromium de Cesium, respaldo 2D, coordenadas, filtros,
  radio, vistas, capas, móvil y pérdida de WebGL. No se repitió esa matriz completa
  como parte de los cambios de catálogo de beta.2.

No se ha verificado beta.2 en el hosting ni en teléfonos físicos. Las referencias
entre agencias son históricas; no certifican una confirmación independiente ni
capacidad de alerta temprana de la red experimental.

Para repetir la prueba de navegador, instala las dependencias de `tools/` y
proporciona recursos locales de Cesium 1.146, Leaflet 1.9.4, Bootstrap 5.3.3 y
Chromium mediante estas variables:

```bash
CESIUM_DIR=/ruta/cesium/Build/Cesium \
LEAFLET_JS=/ruta/leaflet.js LEAFLET_CSS=/ruta/leaflet.css \
BOOTSTRAP_CSS=/ruta/bootstrap.min.css CHROME_PATH=/ruta/chrome \
node tools/test-cesium-map.js
```
