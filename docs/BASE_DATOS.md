# Base de datos de SIGRA

Esquema consolidado para hosting con recursos limitados. Sustituye las migraciones
incrementales de septiembre y noviembre de 2026 (guardadas en `docs/migraciones-legacy/`).

## Tablas

| Tabla | Uso | Crecimiento |
|---|---|---|
| `sismos` | Eventos oficiales SGC/USGS; reportes antiguos retenidos pero excluidos del acceso público | Acotado por `sigra.sismosRetentionDays` (730 días por defecto) |
| `citizen_reports` | Reportes ciudadanos privados y claves HMAC para deduplicación/límites | Pendientes expiran a las 48 h; registros se purgan a los 30 días durante sincronización |
| `red_devices` | Teléfonos registrados | Se limpian los inactivos |
| `red_detections` | Detecciones de onda P | Se purgan a los `red.retentionDays` (7 por defecto) |
| `red_alerts` | Alertas fusionadas y del SGC | Se purgan al expirar + retención |
| `red_alert_deliveries` | Entrega de cada alerta a cada teléfono | Se purga con la alerta |

## Decisiones de optimización

- **InnoDB + `ROW_FORMAT=DYNAMIC` + `utf8mb4`**. Sin `MyISAM` ni tablas temporales.
- **Sin columnas redundantes**: `sismos.source_id` duplicaba `id`; `red_detections.raw_payload`
  duplicaba columnas ya normalizadas. Ambas se eliminaron.
- **Metadatos del SGC acotados**: solo se guardan las claves que la interfaz muestra
  (`agency`, `cdi`, `closerTowns`, `felt`, `gap`, `localTime`, `magType`, `maxPGA`, `mmi`,
  `nst`, `rms`, `updated`, `utcTime`). El objeto completo pesaba ~30 % más.
- **DECIMAL en vez de FLOAT** para coordenadas, magnitud, profundidad, confianza y radios:
  menos bytes y comparaciones exactas. `confidence` es `DECIMAL(4,3)` (0.000–1.000).
- **Sin claves foráneas**: la red recibe escrituras frecuentes y en hosting compartido cada
  comprobación extra se nota. La integridad se valida en `RedNetworkService`; la limpieza de
  entregas huérfanas la hacen los comandos de mantenimiento.
- **Índices compuestos alineados con las consultas reales**:
  - `sismos(fecha_hora)`, `(fuente, fecha_hora)`, `(fecha_hora, magnitud)`, `(magnitud)`.
  - `red_devices(status, last_seen_at)`, `(status, notifications_enabled, last_seen_at)`, `(updated_at)`.
  - `red_detections(device_id, detected_at_ms)` único, `(detected_at_ms)`, `(cluster_id)`.
  - `red_alerts(status, expires_at)`, `(source, created_at)`, `(created_at)`, `(expires_at)`.
  - `red_alert_deliveries(alert_id, device_id)` único, `(device_id, created_at)`, `(created_at)`.

## Escrituras eficientes

- La sincronización con el SGC ya no hace un `SELECT` + `INSERT/UPDATE` por evento.
  Lee los ids existentes en lotes, compara los campos y solo escribe las filas nuevas o
  realmente modificadas (`insertBatch` / `updateBatch` de 200).
- Un segundo pase del mismo feed no escribe nada. Se verificó con 132 eventos reales:
  `132 leídos, 0 nuevos o modificados`.
- `/api/red/red` cachea los agregados 10 s (`red.statsCacheSeconds`) y la expiración de
  alertas corre como máximo una vez por minuto (`red.expireThrottleSeconds`).
- `/sismos/historico` limita la consulta a `sigra.historicoLimit` eventos (500 por defecto)
  en lugar de cargar toda la tabla en PHP.

## Comandos

```bash
# Recrear desde cero (borra las tablas de SIGRA) y sembrar el SGC
php spark sigra:db-reset --force

# Igual, pero sin llamar al SGC
php spark sigra:db-reset --force --no-seed

# Solo aplicar el esquema (base vacía)
php spark migrate --all

# Sembrar/actualizar los eventos del SGC (idempotente)
php spark db:seed SismosSeeder

# Purga + ANALYZE (barato, para cron)
php spark sigra:db-optimize

# Purga + ANALYZE + OPTIMIZE TABLE (necesita espacio libre)
php spark sigra:db-optimize --optimize
```

Cron sugerido en producción:

```cron
*/5 * * * * cd /ruta/sigra && php spark red:limpiar --dias 7 >/dev/null 2>&1
0 3 * * 0   cd /ruta/sigra && php spark sigra:db-optimize --optimize >/dev/null 2>&1
```

## Migración desde el esquema anterior

La migración nueva detecta el esquema viejo (columna `sismos.source_id`) y aborta con un
mensaje claro. Para pasar de la base anterior:

1. Haz un respaldo (`mysqldump`) si hay datos que quieras conservar.
2. Ejecuta `php spark sigra:db-reset --force`. Borra solo las tablas de SIGRA y `migrations`;
   no toca otras bases ni tablas ajenas.
3. Verifica `GET /api/health` → `database: ok` y `GET /api/red/health` → `status: ok`.

### Hosting sin SSH (solo SFTP y panel)

Si no hay terminal en el servidor, usa `tools/setup-remoto.php`:

1. Copia el archivo a la raíz del sitio como `__setup.php`.
2. Reemplaza `CAMBIAR_ESTE_TOKEN` por un token largo y aleatorio.
3. Abre `https://tu-dominio/__setup.php?token=TU_TOKEN` (con `&seed=0` si quieres la base vacía).
4. Cuando responda `OK: base de datos recreada`, **borra `__setup.php`**.

El script hace lo mismo que `sigra:db-reset --force` y exige el token para ejecutarse.


## Ajustes en `.env`

```ini
sigra.sismosRetentionDays = 730
sigra.historicoLimit = 500
red.statsCacheSeconds = 10
red.expireThrottleSeconds = 60
red.retentionDays = 7
```

La migración 1.1.1 añade solo `citizen_reports` a la base MySQL existente y la
columna agregada `sismos.citizen_reports_count`. Usa `php spark migrate --all`;
no requiere crear otra base ni ejecutar `sigra:db-reset`.
