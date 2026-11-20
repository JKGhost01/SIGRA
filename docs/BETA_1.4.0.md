> La entrega actual es **1.4.0.0**, build **20261120.1**, canal **stable**.
> Esta guía conserva el historial y las instrucciones de actualización desde las
> betas; consulta [PRODUCCION.md](PRODUCCION.md) para la identidad de publicación.

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

## Ancho del listado

En escritorio, arrastra el separador entre el listado y el mapa para ajustar el
ancho entre 300 y 640 px, dejando al menos 360 px para el mapa. La pestaña de
flecha permite contraerlo y volver a abrirlo con el ancho anterior. El navegador
guarda estas preferencias; si el almacenamiento está bloqueado, los controles
siguen funcionando durante la visita. Con el separador enfocado, usa las flechas
izquierda/derecha para ajustar, Inicio/Fin para los límites y Escape para cancelar
un arrastre. En móvil se conserva el listado desplegable, independiente del
estado guardado en escritorio. Ambos motores reajustan su área al cambiar el panel.

El radio de **Cerca de mí** usa un acabado de burbuja con centro transparente,
borde iluminado y halo suave. La representación respeta el mismo radio en metros:
Cesium aplica una textura SVG con una separación visual de 200 m sobre el
elipsoide para evitar recortes del borde; Leaflet usa un degradado SVG sobre
su círculo. Esa separación es decorativa y no cambia el radio del filtro. En
Cesium se añade además una esfera de geometría real centrada en la ubicación,
con un material de cristal cuyo borde y reflejo cambian con el ángulo de cámara.
Es un volumen decorativo: el aro del suelo sigue indicando el radio del filtro.
No captura clics ni activa seguimiento, y se elimina al quitar el radio o cambiar
de motor. Solo se renderiza al cambiar la vista, sin animación continua. El filtro de eventos mantiene el cálculo de distancia existente.

## Uso en móviles y tabletas

Hasta 850 px el mapa ocupa el espacio visible entre la barra superior y el pie.
El alto se recalcula al rotar, cambiar el tamaño o reducirse el viewport visual
(por ejemplo, al aparecer el teclado). Los controles empiezan plegados y se
pueden desplegar con desplazamiento propio, incluso en horizontal. Los controles
principales tienen áreas táctiles de al menos 44 px y los campos usan texto de
16 px para evitar ampliaciones automáticas al enfocar en móviles.

**Eventos sísmicos** abre un panel con filtros, estado de actualización y listado.
El panel tiene desplazamiento independiente; se cierra con su botón, el fondo o
Escape. El foco permanece dentro al usar Tab y vuelve al botón que lo abrió al
cerrar. Mientras está abierto el mapa queda inactivo. Al pasar a escritorio se
restaura el panel lateral y se conserva su preferencia de ancho, sin dejar
bloqueados los controles por el estado móvil. El pie y los créditos siguen visibles.

La prueba `tools/test-map-responsive.js` utiliza el HTML PHP real y motores
Leaflet y Cesium con recursos locales. Comprueba anchos de 320 a 1366 px,
vertical, horizontal, español e inglés, paneles, foco, modo oscuro, viewport
reducido y ausencia de desbordamientos. Las dependencias se indican al inicio del
script; no consulta catálogos externos ni requiere bases de datos.

## Amenaza sísmica mundial por colores

El botón **Amenaza sísmica mundial** muestra la zonificación de referencia
[GEM Global Seismic Hazard Map v2026.1](https://www.globalquakemodel.org/product/global-seismic-hazard-map),
con cobertura mundial de continentes y océanos. Reemplaza la cuadrícula de
conteos recientes: los colores del modelo tienen una escala fija, de menor a
mayor amenaza, y no cambian con la fuente, magnitud, radio, paginación o
reproducción del catálogo. Los sismos actuales siguen visibles como marcadores.

El modelo representa la aceleración máxima del suelo (PGA, en unidades de g) con
10 % de probabilidad de excedencia en 50 años, sobre roca de referencia
(Vs30 = 800 m/s). No es una predicción del próximo terremoto ni una clasificación
de riesgo de edificios. El panel muestra el significado de la capa y enlaza la
fuente y su leyenda oficial; las áreas de baja amenaza pueden sufrir sismos.

Se utiliza el endpoint Web Mercator publicado por el
[visor oficial de GEM](https://maps.openquake.org/map/gshm-v2026_1/). Leaflet y
Cesium cargan las mismas teselas, con crédito visible y licencia **CC BY-NC-SA 4.0**.
La capa es opcional, usa opacidad moderada, no cambia la cámara, no solicita
consultas de eventos y no guarda posiciones. La descarga de teselas se limita
al nivel 8; los acercamientos posteriores reutilizan esa resolución de referencia.
Los cambios de filtros conservan la capa existente y cambiar de base o motor
libera la anterior antes de crear otra. Si fallan teselas se informa en el panel;
no se sustituyen por colores inventados. Se puede desactivar y activar para reintentar.

## Qué representa el globo

El visor desactiva explícitamente el token de Cesium ion y utiliza un elipsoide,
sin relieve 3D ni descargas de elevación.

Las cuatro capas recuperan las fuentes de la versión 1.4.0.1-beta.2: OSM,
Esri World Imagery, Esri World Dark Gray Canvas y OpenTopoMap. La capa satelital
reemplaza NASA Blue Marble y vuelve al nivel máximo 16 de la beta.2. La capa
oscura usa directamente las teselas de Esri, sin procesar píxeles en el navegador.
Se puede configurar otra fuente mediante `seismicMap.satelliteUrl`, su crédito y
nivel máximo. Se eliminan los edificios 3D y las consultas a Overpass.

La cámara puede acercarse hasta 10 m, con controles de acercar/alejar y
orientación al norte. El detalle final depende de la cobertura y resolución de
las imágenes. No se incluyen edificios fotogramétricos ni servicios de Google.
La ubicación y los epicentros se representan sobre el elipsoide; los hipocentros
mantienen su altura científica negativa. Al volver a Leaflet se conservan capas,
radio y ubicación.

El mapa inicia sobre la ubicación del usuario si ya está disponible o cuando
el navegador la entrega con permiso concedido. Mientras tanto muestra Colombia.
La cámara no se guarda ni se restaura desde caché o almacenamiento local. Si hay
un radio de cercanía activo, la vista se ajusta a ese radio sobre la ubicación.


Los hipocentros usan altura `-profundidad * 1000` sobre el elipsoide WGS84, sin
exageración vertical. La profundidad de la agencia es una aproximación respecto
a esta superficie, no un modelo de la corteza local. Los valores ausentes o no
numéricos se sitúan en superficie; las profundidades negativas se conservan como
altitudes positivas. Desde arriba se muestran los epicentros con su tamaño por magnitud y color por
profundidad; los puntos subterráneos permanecen ocultos. Al inclinar la cámara
más de 15° se activa la transparencia por distancia y se muestran los hipocentros,
referencias pequeñas de superficie y líneas claras entre ambos. Se vuelve a la
vista opaca al bajar de 8°, evitando parpadeos cerca del umbral. La vista de
profundidad sustituye el interior negro por un tono atmosférico claro, conserva
el detalle con un ajuste suave de brillo/gamma y usa un fondo de cielo en lugar
del espacio negro. Al salir restaura las propiedades normales de la capa y el
cielo espacial. Las nuevas capas reciben el mismo ambiente si se cambian mientras
la profundidad está activa.

El botón **Ver profundidades** centra e inclina la cámara sobre el evento
seleccionado o uno cercano al centro visible. Permite mantener la vista de
profundidad al filtrar; al desactivarlo vuelve a la vertical. Las líneas conservan
su longitud real: las profundidades pequeñas requieren acercarse para distinguir
la separación. Los símbolos de superficie se dibujan como discos completos,
sin recorte contra el búfer de profundidad del globo ni el círculo de cercanía.
La ocultación por horizonte impide mostrar los del hemisferio opuesto. Los
hipocentros mantienen las pruebas de profundidad y sus coordenadas científicas.
Los puntos blancos pequeños indican la superficie, los puntos de color el
hipocentro; la leyenda lo aclara al activar la vista. Las líneas son discretas
y la del evento seleccionado se resalta para seguirlo dentro de una agrupación.

El catálogo utiliza colecciones de puntos y polilíneas primitivas, reutilizadas
por ID durante filtros y reproducción. Solo la selección, la ubicación y el
radio usan entidades; los mapas de candidatos TDOA de la red mantienen su flujo
actual.

Cesium y sus recursos se cargan desde la versión fijada en `cesium.com`. Las
capas necesitan acceso a OpenStreetMap, Esri u OpenTopoMap; la capa de amenaza
utiliza maps.openquake.org. Los créditos de los
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

## Fuentes del mapa y autoalojamiento

La configuración predeterminada no usa token de Cesium ion. Las capas de Esri
conservan sus créditos y condiciones de uso. Los
servidores públicos mantienen sus propias políticas y disponibilidad; datos
abiertos no significan capacidad de tráfico ilimitada garantizada. Se pueden
reemplazar las URL de las capas, y servir el Build/Cesium 1.146 completo
mediante `seismicMap.cesiumBaseUrl`. La configuración está en `.env.example`.
El autoalojamiento utiliza almacenamiento, capacidad de cómputo y ancho de banda.
