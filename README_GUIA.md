# SIGRA · Guía funcional

Aplicación CodeIgniter 4 para visualizar sismos de todo el mundo (SGC, USGS, EMSC, GeoNet, GEOFON e INGV), conservar
un histórico, recibir reportes ciudadanos y operar una red de detección temprana con teléfonos.

## Estado del proyecto: versión 1.3.1 · Cabeceras HTTP y recuperación de fuentes

Desde la versión 1.0.3 se unifica el feed colombiano del SGC con la red mundial USGS en un solo
mapa, listado, histórico y API. Incluye filtro por radio desde la ubicación del usuario,
deduplicación entre fuentes, sincronización automática con cron opcional, mapa activo, ficha técnica, filtros,
temas claro/oscuro, alertas visuales y sonoras, reporte ciudadano, red ciudadana de ondas P,
notificaciones experimentales y API para el cliente Android en desarrollo.

La funcionalidad de SMS fue eliminada. Las alertas ahora se entregan por notificaciones del
navegador, overlay a pantalla completa y la app Android.

## Idiomas

La interfaz está disponible en español e inglés. El selector **ES / EN** del navbar guarda la
preferencia en una cookie (`sigra_locale`). Las vistas usan claves (`lang('Sigra.*')`); el
archivo inglés se completa automáticamente con:

```bash
php spark sigra:translate
```

No se duplican páginas por idioma. Para añadir otro locale, crea
`app/Language/<locale>/Sigra.php` y ejecuta el comando con `--locale <locale>`.

## Fuentes de datos

- **SGC** (Servicio Geológico Colombiano): cobertura local, con CDI, MMI, PGA, reportes
  sentidos, municipios y estaciones.
- **USGS** (U.S. Geological Survey): cobertura mundial, por defecto magnitud 2.5+ de los
  últimos cinco días.

Ambas fuentes se normalizan al mismo esquema. Si el mismo sismo aparece en las dos, se
conserva el registro del SGC por tener más detalle.

## Arquitectura

- **Módulos por capacidad**: catálogo, ingesta, reportes, sensores, alertas y sistema.
- **Http**: controladores ligeros, filtros, parsing y recursos de salida.
- **Application**: consultas y comandos con dependencias inyectadas.
- **Domain**: reglas y algoritmos sin dependencias de CI4.
- **Infrastructure**: modelos CI4, fuentes, auditoría y entrega.
- **Presentation**: ViewModels y plantillas que solo presentan datos.
- **Frontend del catálogo**: `public/assets/js/modules/seismic-map/catalog.js` y utilidades Leaflet.
- **Composición**: `app/Config/Services.php`; las rutas se componen desde cada módulo.
- **Android**: en desarrollo en un repositorio separado, con contrato OpenAPI.

Los controladores usan el renderizador de vistas de CI4. Las vistas no ejecutan
validación, persistencia ni permisos. Consulta [architecture](docs/architecture/README.md).

## Instalación y requisitos

La secuencia soportada, Composer, claves y base de datos se mantienen únicamente en
[docs/INSTALLATION.md](docs/INSTALLATION.md). Esta guía complementa los README con el
uso de mapas, reportes y red experimental; no define un procedimiento alternativo.

## Funcionalidad actual

### Mapa activo

`/sismos` muestra los eventos de SGC (Colombia) y USGS (mundo) de los últimos cinco días. El navegador consulta `/sismos/sync` cada 15 segundos para leer el catálogo. Al vencer la caché compartida de 300 segundos, el endpoint solicita la ingesta al módulo correspondiente con un bloqueo contra ejecuciones simultáneas. Cron es opcional.

La actualización reemplaza únicamente la capa de marcadores y las tarjetas laterales: no recarga la página ni pierde centro, zoom o estado de navegación del mapa.

El panel lateral permite ordenar por:

- Fecha, de más reciente a más antiguo.
- Magnitud, de mayor a menor o de menor a mayor.
- Intensidad percibida (CDI).
- Intensidad instrumental (MMI).
- Aceleración PGA disponible.
- Distancia desde tu ubicación (“Más cercanos”).

También permite filtrar por lugar, por fuente (todas / SGC / USGS), por rango de magnitud (M2.5+, M3+, M4+, M5+, M6+) y por radio. La primera tarjeta se destaca temporalmente como sismo nuevo y el sistema muestra un aviso y una alerta sonora cuando detecta un evento nuevo.

Cada tarjeta abre `/sismos/detalle/{id}`.

### Filtro “Cerca de mí”

El botón **Mi ubicación** solicita permiso de geolocalización solo cuando el usuario lo pulsa. Con la ubicación activa:

- el mapa dibuja un círculo con el radio elegido;
- el listado muestra la distancia de cada epicentro;
- el selector permite 50, 100, 250, 500, 1.000, 2.000 o 5.000 km, además de “Todo el mundo”;
- el orden “Más cercanos” queda disponible.

Las coordenadas no se envían al servidor ni se guardan en la base de datos. Solo se conserva el radio elegido en `localStorage`. Si el navegador niega la ubicación precisa, se usa una aproximación por IP para no romper la función.

### Histórico

`/sismos/historico` conserva todos los eventos almacenados, de SGC y USGS. Permite filtrar por fecha y ordenar por fecha, magnitud, profundidad, ubicación o fuente.

### Ficha técnica

`/sismos/detalle/{id}` muestra los datos técnicos disponibles según la fuente:

- **SGC**: magnitud, tipo de magnitud, coordenadas, horas local/UTC, agencia, estaciones, RMS, GAP, MMI, CDI, reportes sentidos, PGA, municipios cercanos, estado y mapa oficial.
- **USGS**: magnitud, tipo de magnitud, coordenadas, agencia, reportes sentidos, MMI, CDI, nivel de alerta, riesgo de tsunami, significancia y enlace al reporte oficial.

La ficha SGC conserva el mapa oficial:

```text
https://archive.sgc.gov.co/events/{source_id}/map.png
```

### Reporte ciudadano

Desde 1.1.1, los reportes se guardan privados en `citizen_reports` dentro de la
misma base MySQL. Con al menos tres participantes distintos y un único evento
SGC compatible se añade el conteo al evento oficial; nunca se publica un
epicentro ciudadano. Detalles en `docs/REPORTES_CIUDADANOS.md`.


`/sismos/create` permite indicar dónde se sintió un evento mediante:

- clic en el mapa;
- ubicación del navegador;
- referencia textual;
- intensidad percibida y hora.

Los campos técnicos internos se completan sin pedir al ciudadano un epicentro científico ni profundidad instrumental.

### Interfaz y presentación

- Tema claro predeterminado y tema oscuro seleccionable desde el navbar.
- Logo institucional integrado en el navbar.
- Diseño responsive para escritorio y móvil.
- Panel lateral con ordenamiento, filtro por lugar, fuente y radio, y tarjetas alternadas.
- Aviso de actualización de datos sísmicos y alerta sonora para nuevos eventos.

## Integración de fuentes

Feeds actuales:

```text
SGC  https://archive.sgc.gov.co/feed/v1.0.1/summary/five_days_2.json
USGS https://earthquake.usgs.gov/fdsnws/event/1/query (GeoJSON, M2.5+, 5 días)
```

Flujo:

```text
SGC  -> SgcClient  \
                     -> QuakeSyncService -> deduplicación -> SismoModel -> MySQL -> mapa/histórico/ficha/API
USGS -> UsgsClient /
```

Los eventos se deduplican por `id` y, entre fuentes, por cercanía (75 km) y tiempo (120 s). Las propiedades originales se guardan en `sgc_metadata` como JSON. La configuración vive en `app/Config/Quakes.php`.

La sincronización se puede ejecutar sin abrir el mapa:

```bash
php spark sigra:sync
```

Programarla cada 2–5 minutos desde cron es opcional: permite actualizar sin visitantes. El mapa ya solicita la ingesta automáticamente.

En desarrollo se permite el certificado local de la red. En producción se exige verificación TLS.

## Rutas

| Ruta | Uso |
|---|---|
| `/sismos` | Mapa activo y listado reciente |
| `/sismos/sync` | Lectura JSON del catálogo |
| `/sismos/historico` | Histórico con filtros |
| `/sismos/create` | Reporte ciudadano |
| `/sismos/detalle/{id}` | Ficha de lectura |

Las rutas antiguas de edición y eliminación se conservan por compatibilidad interna, pero no se exponen en la interfaz pública del listado.

## Red de teléfonos y app Android

`/red` muestra el estado de la red ciudadana: teléfonos en línea, detecciones, alertas y un mapa
con posiciones aproximadas. La app Android está en desarrollo en un repositorio separado.

Flujo:

```text
Acelerómetro -> detección STA/LTA de onda P -> POST /api/red/deteccion
-> agrupación de 3+ teléfonos -> TDOA -> epicentro/magnitud/confianza
-> alerta -> FCM o polling -> notificación con ETA de onda S
```

Endpoints principales:

- `POST /api/red/registro`
- `POST /api/red/estado`
- `POST /api/red/deteccion`
- `GET /api/red/alertas`
- `GET /api/red/red`

Documentación completa en [docs/RED_TELEFONOS.md](docs/RED_TELEFONOS.md) y
[docs/API_RED.md](docs/API_RED.md).

### Operación de la red

```bash
php spark red:estado
php spark red:limpiar --dias 7
```

La evaluación experimental exige varios dispositivos y umbrales de confianza; esto no equivale a confirmar científicamente un sismo ni a garantizar una alerta útil.

## Alertas sin SMS

Las alertas por SMS se eliminaron de la web. Ahora hay tres canales:

1. **Alertas del navegador** con Web Notifications y service worker.
2. **Overlay a pantalla completa** con magnitud, distancia y cuenta atrás hasta la onda S.
3. **Contrato para el cliente Android en desarrollo**, con push y polling de respaldo.

El botón de campana en el navbar y los botones de `/red` activan las alertas del navegador. No hay
registro, teléfono ni OTP. La preferencia se guarda en `localStorage` y la ubicación se usa solo en
el navegador para calcular distancias.

### Probar las alertas del navegador

1. Abre `/red`.
2. Pulsa **Activar alertas del navegador** y concede permiso.
3. Cuando la red detecte un evento real, verás el overlay con la cuenta atrás y, si diste permiso,
   la notificación del sistema.
4. El botón de campana del navbar permite activar o desactivar el canal en cualquier página.

## Donaciones

Si SIGRA te resulta útil, puedes apoyar su desarrollo y mantenimiento con una
donación voluntaria. ¡Gracias por aportar al proyecto!

| Medio | Número para donar |
|---|---|
| **Nequi** | `315 9287929` |
| **Bancolombia** | Cuenta `912-982627-76` |

## Documentación adicional

- [Red de teléfonos y algoritmo](docs/RED_TELEFONOS.md).
- [API de la red](docs/API_RED.md).
- [Base de datos y mantenimiento](docs/BASE_DATOS.md).
- [Pruebas web y API](docs/PRUEBAS_WEB.md).
- [Arquitectura general](docs/ARQUITECTURA_SISMOS.md).
- [Documentación general del proyecto](README.md).
La distribución del código y los cambios operativos están en [docs/architecture](docs/architecture/README.md).
