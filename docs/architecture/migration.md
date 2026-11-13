# Aplicación y compatibilidad

## Antes de desplegar

```bash
php spark migrate --all
php spark sigra:sync
```

La migración `20261112000002_CreateArchitectureAudit` añade únicamente
`sigra_ingestion_batches` y `sigra_alert_decisions`. No altera las tablas científicas ni
reinterpreta fechas. No se ejecuta automáticamente al servir peticiones.
La auditoría debe estar disponible antes de evaluar/publicar nuevas alertas. La alerta
y su decisión se guardan en una transacción; la entrega ocurre después del commit.

La actualización automática del mapa funciona sin cron. Opcionalmente, para mantener
el catálogo actualizado incluso cuando no hay visitantes:

```cron
*/5 * * * * cd /ruta/sigra && php spark sigra:sync >> writable/logs/sync.log 2>&1
```

`sigra:sync` aplica un bloqueo de archivo para evitar dos ejecuciones simultáneas del
comando en la misma instalación. En despliegues con varios servidores se necesita un
lease compartido; el bloqueo local no ofrece esa garantía.

## Cambios de comportamiento previstos

- `GET /sismos/sync` conserva la actualización automática del mapa. La primera consulta
  tras `quakes.cacheSeconds` ejecuta la ingesta modular; las siguientes leen el catálogo
  sin descargar nuevamente. Comparte `ingestion.lock` con CLI y registra el intervalo
  en `writable/cache/catalog-refresh.json`. Un error conserva los eventos almacenados
  y aplica el mismo intervalo antes de reintentar. `synced` indica si esta petición
  actualizó las fuentes; `timestamp` identifica el último intento de actualización.
- La ingesta CLI conserva notificaciones informativas de eventos SGC recientes; la
  unicidad existente de `source + source_id` evita recrearlas al repetir el lote.
- Los GET de alertas consultan estados activos sin expirar ni marcar entregas vistas.
  El mantenimiento `red:limpiar` conserva la responsabilidad de limpieza.
- Los errores de `/api/sismos/*` y `/api/red/*` conservan `status` y `message`.
  `/api/v1/*` añade el envelope de error `error.code`, `error.message`, `request_id`.
  Los recursos de éxito mantienen sus campos. Todas las respuestas llevan correlación
  generada en el servidor, mediante `X-Request-ID`.
- Health deja de exponer `environment`. Sigue devolviendo la disponibilidad de base
  y versión pública para compatibilidad; no contiene trazas ni credenciales.
- Los envíos válidos de reportes usan redirect 303. Las rutas de edición/borrado siguen
  rechazadas con 403. Un ID web inexistente devuelve 404.
- La correlación ambigua conserva ambos eventos. Una fusión inequívoca conserva las
  referencias SGC/USGS y su explicación en metadata interna.

## Alertas experimentales

`red.alertingEnabled = false` detiene nuevas publicaciones; no borra alertas anteriores.
Las detecciones etiquetadas con `simulated: true` no se guardan ni llegan a canales reales.
Las pruebas usan publishers sustituidos y nunca disparan FCM.

```bash
php spark sigra:alert-cancel <uuid> "motivo de la cancelación"
```

Solo la consola del servidor ofrece cancelación. Exige una alerta activa y un motivo;
registra la decisión y cambia su estado. Las consultas activas dejan de devolverla;
esto no retira una notificación que un dispositivo ya haya recibido.

Los estados de candidato son `experimental`, `candidate`, `under_review`,
`confirmed_by_policy`, `rejected`, `expired`, `cancelled`. Las decisiones contienen
algoritmo/versionado, evidencia agregada, motivo y hora UTC. Los estados terminales no
se reactivan mediante `AlertCandidate::transition`. Los estados heredados de la tabla
`red_alerts` siguen siendo compatibles (`active`, `expired`, `cancelled`).

## Validaciones y límites restantes

Se verifican unidades, distancias, idempotencia, fallos parciales, reportes, contratos,
autenticación, CSRF, simulación, kill switch y cancelación. Las validaciones científicas
de campo/carga y la migración horaria de datos históricos se mantienen separadas.
Las auditorías nuevas no guardan IP ni tokens; definir su retención operativa antes de
un despliegue prolongado. No se habilitan colas ni infraestructura distribuida.

No ejecutar los antiguos tests MySQL contra `.env`. El runner de integración exige
una base `sigra_*_test`, omite `.env` y valida el destino antes de aplicar migraciones.
