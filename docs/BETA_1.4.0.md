# SIGRA v1.4.0-beta.1 · Globo 3D experimental

| Identidad | Valor |
|---|---|
| Versión | `1.4.0-beta.1` |
| Build | `20261119.beta1` |
| Fecha | 2026-11-19 |
| Canal | `beta` |
| Rama | `prerelease/1.4.0-beta.1-cesium` |
| Tag de la pre-release | `v1.4.0-beta.1` |
| Base estable | `1.3.1.3`, commit `d1915f19734b06146eebe63c2efab2256ec53797` |
| Contrato API | `v1`, sin cambios |

Esta entrega se publica como **pre-release** para pruebas. No reemplaza la release
estable ni se integra automáticamente en `main`. La subida al hosting la realiza
el operador; las comprobaciones descritas abajo corresponden al entorno local.

## Qué incluye

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

## Subida desde 1.3.1.3

La pre-release incluye un ZIP de actualización con los archivos de ejecución
modificados desde la base indicada, esta guía, manifiesto y hashes SHA-256.
También está disponible el código completo del tag. El ZIP de actualización no
es un instalador para versiones antiguas ni una copia de los datos del servidor.

1. Conserva una copia privada del código estable y de la base, junto con `.env` y
   los datos de `writable/`. Sigue [PRODUCCION.md](PRODUCCION.md).
2. Revisa `archivos-incluidos.txt` y copia las rutas incluidas a la misma raíz
   del proyecto en el hosting. Deben llegar **todos los archivos nuevos**,
   especialmente `app/Config/SeismicMap.php`, el servicio de procedencia del
   catálogo, `public/assets/js/modules/seismic-map/globe.js` y
   `public/assets/css/seismic-globe.css`.
3. Sobre 1.3.1.3 **no hay migraciones nuevas, cambios Composer ni compilación
   JavaScript**. No ejecutes reset, seeds ni los SQL de actualizaciones antiguas.
   Si vienes de una versión anterior, revisa por separado sus cambios pendientes.
4. Conserva `.env`, `vendor/` y `writable/`, especialmente
   `writable/cache/providers`. No borres pausas para forzar consultas. La base
   local y los archivos de prueba no se incluyen en el ZIP de actualización.
5. Si el `.env` del servidor ya fija estos valores, actualízalos o retira sus
   overrides para usar la configuración del código:

   ```ini
   sigra.version = '1.4.0-beta.1'
   sigra.build = '20261119.beta1'
   sigra.channel = 'beta'
   seismicMap.engine = 'cesium'
   sgc.feedUrl = 'https://archive.sgc.gov.co/feed/v1.0.1/summary/five_days_all.json'
   ```

6. Recarga el código PHP mediante el mecanismo habitual del hosting si OPcache
   mantiene la versión anterior. Ambos service workers usan un nombre de caché
   nuevo para la beta; comprueba la actualización de los assets en el navegador.

## Aceptación después de subir

- `/api/health` debe responder con `version: 1.4.0-beta.1`,
  `build: 20261119.beta1`, contrato `v1` y base accesible. Acerca de debe mostrar
  el canal **Beta** y la entrada correspondiente del historial.
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
restaura el conjunto de archivos de la estable 1.3.1.3 y sus valores de versión,
build y canal si estaban sobrescritos. Conserva el catálogo y el estado de
proveedores: esta beta no añade esquema que haya que revertir. Los sismos
pequeños ya importados no se borran por restaurar el código estable.

## Evidencia local y límites

- Suite completa `bash tools/test-architecture.sh` y PHPStan aprobados.
- Prueba de persistencia y rollback en `sigra_source_sync_test`, separada del
  catálogo local: referencias conservadas, correcciones aplicadas e idempotencia.
- Chromium con Cesium real: arranque 3D, coordenadas, filtros, ubicación/radio,
  selección y escape de datos, cambio 3D/2D, capas, plegado, viewport móvil,
  CDN bloqueado y pérdida de WebGL. Las teselas de esa prueba están simuladas;
  no acredita disponibilidad permanente de los servicios de mapas.
- El usuario comprobó el mapa real en localhost:8080. La auditoría posterior
  contrastó las seis fuentes: cero ausencias y cero diferencias frente a las
  respuestas recibidas, contando correlaciones; 539 SGC en esa muestra.
- La página real respondió HTTP 200 y mostró 1673 eventos en esa comprobación.
  Estos números son evidencia de ese momento, no un catálogo garantizado.

No se ha verificado esta beta en el hosting ni en teléfonos físicos. No acredita
capacidad de alerta temprana o validación científica de la red experimental.

Para repetir la prueba de navegador, instala las dependencias de `tools/` y
proporciona recursos locales de Cesium 1.146, Leaflet 1.9.4, Bootstrap 5.3.3 y
Chromium mediante estas variables:

```bash
CESIUM_DIR=/ruta/cesium/Build/Cesium \
LEAFLET_JS=/ruta/leaflet.js LEAFLET_CSS=/ruta/leaflet.css \
BOOTSTRAP_CSS=/ruta/bootstrap.min.css CHROME_PATH=/ruta/chrome \
node tools/test-cesium-map.js
```
