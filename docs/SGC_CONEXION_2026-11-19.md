# Recuperación de la conexión SGC · 19 de noviembre de 2026

## Resultado y alcance

El cliente PHP corregido recibió HTTP 200 y normalizó 100 eventos del feed
oficial. Una segunda llamada devolvió los mismos eventos desde la caché local.
La prueba se ejecutó en este entorno con `ENVIRONMENT=production`, TLS verificado,
un directorio temporal para el estado del proveedor y sin escribir en la base.
El despliegue y la comprobación desde el hosting quedan a cargo del operador.

Rama: [`hotfix/sgc-user-agent`](https://github.com/JKGhost01/SIGRA/tree/hotfix/sgc-user-agent).
Base: `ed08158a351974d35126078fc541fef3dfa62209`, desde
`hotfix/1.3.1-provider-headers`. La versión declarada sigue siendo `1.3.1.2`,
build `20261113.4`; este cambio se identifica por la rama y su commit, sin
crear una release ni modificar el contrato API v1.

## Comparación con la copia antigua

Se revisó `/home/azyron/Proyectos/sigra_old/app/Libraries/SgcClient.php`.
Usaba un User-Agent de navegador, `Referer`, `Cache-Control: no-cache`,
un `_cache_buster` variable y desactivaba TLS en desarrollo.
La versión exacta de esa copia no se verificó; su comportamiento sí se comparó.

Todas las peticiones de diagnóstico conservaron la verificación TLS y consultaron
`https://archive.sgc.gov.co/feed/v1.0.1/summary/five_days_2.json`:

| Petición | Resultado observado |
|---|---|
| Identidad `SIGRA/1.3.1` y repositorio | HTTP 403; XML `AccessDenied`, Amazon S3/CloudFront |
| Cabeceras antiguas y parámetro de diagnóstico | HTTP 200; 100 eventos |
| Identidad SIGRA y parámetro de diagnóstico | HTTP 403 |
| Identidad SIGRA, Referer y Cache-Control, sin parámetro | HTTP 403 |
| Solo Accept y User-Agent de navegador antiguo, URL original | HTTP 200 |
| Cliente PHP corregido, tokens antiguos más `SIGRA/1.3.1.2` y repositorio | HTTP 200; 100 eventos normalizados |

El cambio de User-Agent explica la diferencia observada entre las peticiones
comparadas. No se conoce la regla interna del proveedor que produce el rechazo;
el 403 por sí solo no demuestra bloqueo por exceso de tráfico.

## Implementación y regresiones

`app/Modules/SeismicIngestion/Infrastructure/Sources/SgcClient.php` envía los
tokens de compatibilidad de navegador de la copia antigua y añade explícitamente
`SIGRA/versión`. `CiFeedTransport` sigue añadiendo el enlace del repositorio.
Las otras fuentes conservan su cabecera habitual.

Se mantienen la URL configurada sin parámetros nuevos, TLS, timeout de diez
segundos, intervalo SGC de quince minutos, bloqueo por host, caché condicional,
Retry-After y pausas persistentes ante errores. No se añaden reintentos ni
endpoints alternativos, cambios de esquema o de dependencias.

La regresión en `tests/Unit/ProviderNormalizationTest.php` comprueba los tokens
de compatibilidad, la identidad y versión de SIGRA y la URL sin cache-busting.
`bash tools/test-architecture.sh` terminó correctamente, incluyendo transporte,
normalización, pausas, cabeceras, caché, arquitectura y contratos HTTP/CGI.
`git diff --check` también pasó. La suite no acredita el funcionamiento del hosting
ni sustituye una prueba de persistencia contra su base de datos.

## Subida y aceptación en producción

1. Sigue las precauciones de [PRODUCCION.md](PRODUCCION.md) y usa el código
   de la rama indicada. Este hotfix sobre la base declarada no requiere migraciones,
   Composer ni reconstrucción de assets. Si el servidor es más antiguo, revisa
   las actualizaciones intermedias antes de aplicar un paquete acumulativo.
2. El único archivo de ejecución modificado por este hotfix es
   `app/Modules/SeismicIngestion/Infrastructure/Sources/SgcClient.php`.
   Conserva `.env`, `vendor/` y `writable/`, especialmente `cache/providers`.
   Si PHP mantiene código anterior en OPcache, usa el mecanismo habitual del hosting
   para recargarlo.
3. Abre `/acerca-de#sources-health` y comprueba la próxima consulta permitida de SGC.
   Una pausa guardada sigue vigente tras subir el código; no borres el estado para
   forzar consultas. «Actualizar estado» solo vuelve a leer ese estado.
4. Cuando venza la pausa, permite la actualización normal del mapa o ejecuta
   `php spark sigra:sync` una vez si dispones de consola. Ese comando consulta las
   fuentes habilitadas y puede escribir eventos en la base; respeta sus pausas.
5. Confirma un nuevo resultado correcto de SGC (HTTP 200, o 304 si ya hay un JSON
   válido), fechas recientes de comprobación/respuesta válida y eventos con fuente
   SGC en mapa, histórico y detalle. Revisa las fechas del catálogo: una respuesta
   HTTP correcta no garantiza cobertura exhaustiva ni actualidad científica.

Si sigue fallando, registra código HTTP y fechas desde el hosting, la próxima
consulta y los logs privados. No publiques credenciales. La prueba local exitosa
no garantiza que la red o las reglas aplicadas al hosting sean iguales.

## Reversión

Sobre una instalación que ya estaba en la base indicada, restaura únicamente el
archivo SgcClient.php anterior o revierte el commit de este hotfix en tu flujo de
Git. No reviertas tablas ni borres el catálogo o el estado del proveedor.
La reversión recupera el comportamiento anterior, incluido el posible HTTP 403.
