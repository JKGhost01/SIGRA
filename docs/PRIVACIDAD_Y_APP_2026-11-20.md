# Privacidad y revisión de SIGRA Android

Estado: cambios web incorporados en 1.4.1.0; publicación en GitHub separada del despliegue. Android pasa de código 2 a 3, versión 1.1.0.0, para permitir actualizar la instalación existente conservando la firma.

## Antes de publicar

Completar `legal.responsible`, `legal.email`, `legal.address`, `legal.city` y `legal.phone` en el entorno privado del servidor. Deben corresponder al responsable real y a canales públicos autorizados para consultas y reclamos. No se infiere el responsable a partir del autor del repositorio. Actualizar también los cuatro documentos offline de Android, que actualmente indican que esos datos están pendientes, y reconstruir el APK. Esta revisión técnica no certifica cumplimiento jurídico.

Verificar los encargados reales, transferencias internacionales, plazos de logs y copias de seguridad, procedimiento de atención de derechos y ejecución periódica de limpieza. No atribuir plazos de eliminación al hosting sin confirmarlos. El mapa GEM tiene licencia CC BY-NC-SA 4.0; revisar cualquier uso comercial antes de distribuirlo.

Desplegar primero la migración `20261120000001_AddReportConsent` mediante el procedimiento habitual y `php spark migrate --all`, con copia de seguridad y verificación del entorno. Añade versión y fecha de autorización a los reportes; su reversión conserva esa evidencia. Los reportes web se bloquean con 503 si faltan los contactos, y con 422 si la autorización está ausente o desactualizada. No se ejecutó una migración en producción durante esta revisión.

## Comportamiento

- Cuatro documentos ES/EN: privacidad, tratamiento, términos y cookies. Android incluye copia española offline, de la misma fuente `LegalContent`.
- El aviso de cookies informa almacenamiento técnico; no presume consentimiento publicitario. Se eliminó la geolocalización silenciosa por IP tras denegar GPS.
- Avisos del catálogo separados del sensor. Servicio visible y voluntario, consulta cada 60 segundos, radio y magnitud configurables, primera consulta sin avisos históricos, deduplicación persistente y máximo cinco nuevos registros recientes por consulta. Un registro publicado no es alerta anticipada.
- Android conserva la última ubicación autorizada para las consultas. No solicita ubicación en segundo plano. Cambiar de ubicación requiere actualizarla desde la app. La cámara del mapa no se persiste.
- Participación como sensor opcional, autorización separada. Retirar autorizaciones detiene servicios y borra ubicación local; una baja remota fallida se comunica y no se presenta como eliminación del servidor.
- Firebase no se inicializa automáticamente. Push requiere configuración real de Firebase y del servidor; la consulta del catálogo no depende de ella.
- Android puede detener, limitar o retrasar el servicio por batería, Doze, reinicio, permisos o cierre forzado. No existe garantía de disponibilidad permanente. El subtipo `specialUse` requiere declaración y revisión si se publica en Google Play.
- Capas nativas: Esri satélite, calles OSM, Esri oscuro y amenaza GEM global. Amenaza a largo plazo no equivale a frecuencia reciente ni a riesgo particular de una construcción. Sin relieve ni edificios 3D.

## Validación y fuentes

Ejecutar `bash tools/test-architecture.sh`, PHPStan y `python3 tools/test-mysql-architecture.py` con una base desechable explícita. Android: JDK 21, `./gradlew --offline :app:testDebugUnitTest :app:lintDebug :app:assembleDebug :app:assembleRelease`. Comprobar firma y aumento de versionCode antes de instalar sobre la versión anterior. Una notificación de prueba debe identificarse como prueba y no representar un sismo real. Falta comprobar entrega en el teléfono físico del usuario y sus restricciones del fabricante.

Referencias oficiales:

- Android: https://developer.android.com/develop/background-work/services/fgs/service-types
- Restricciones Android 15: https://developer.android.com/about/versions/15/behavior-changes-15
- Ley 1581: https://cancilleria.gov.co/normograma/compilacion/docs/ley_1581_2012.htm
- SIC: https://sedeelectronica.sic.gov.co/politica-de-tratamiento-de-datos-personales
- GEM: https://maps.openquake.org/map/gshm-v2026_1/

## Resultado de la revisión local

79 pruebas Android aprobadas, cero errores de lint (39 advertencias), debug y release compilados. Certificado de firma idéntico al APK 1.0.0.0; versionCode 3. En emulador Android 37 se activaron los avisos sin sensor, se observó el servicio en primer plano y la bandeja mostró la notificación de prueba explícita. Las descargas GEM notifican al mapa cuando termina cada tesela para dibujarlas sin esperar a mover la cámara. No se probó entrega en el teléfono físico.

Suite de arquitectura/HTTP/mapas y PHPStan aprobados. MariaDB aislado: migración y reportes con versión/fecha de autorización verificados en ambas raíces públicas (27 casos HTTP por raíz), más repositorios, paginación, 40 verificaciones de reportes y atomicidad de alertas. Sin publicación ni despliegue.
