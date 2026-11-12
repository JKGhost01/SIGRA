# Checklist de producción

## 1. Configuración

- `CI_ENVIRONMENT = production`
- `app.baseURL = 'https://tu-dominio/'` (con barra final)
- `APP_API_TOKEN` y `red.appKey` con una clave propia.
- `encryption.key` configurada.
- Base de datos de producción en `database.default.*`.
- FCM solo si vas a usar push; de lo contrario `red.fcmEnabled = false`.
- No dejes `CI_ENVIRONMENT = development`, `debugbar` activo ni credenciales de prueba.

## 2. Base de datos

Instalación limpia (borra las tablas de SIGRA, aplica el esquema optimizado y carga el SGC):

```bash
php spark sigra:db-reset --force
```

Actualizar solo los eventos del SGC sin tocar la red:

```bash
php spark db:seed SismosSeeder
php spark red:estado
```

Las migraciones nuevas crean las tablas de la red. El esquema y los índices están
documentados en [BASE_DATOS.md](BASE_DATOS.md).

## 3. Archivos y permisos

- Sube el proyecto completo sin `.env` de desarrollo, `writable/` con datos de prueba ni `tools/node_modules`.
- Permisos de escritura solo para `writable/`.
- El `.htaccess` bloquea `app/`, `system/`, `vendor/`, `tests/`, `docs/`, `tools/`, `mobile/`, `writable/`, `.env` y archivos sensibles.
- Si usas Nginx, replica esos bloqueos.
- Sirve siempre por HTTPS: las notificaciones del navegador y el service worker lo requieren.

## 4. Limpieza

```bash
php spark sigra:db-optimize
rm -rf writable/cache/* writable/logs/* writable/session/* writable/debugbar/*
```

## 5. Verificación

- `GET /api/health` → `status: ok`, `version: 1.1.1`, `database: ok`.
- `GET /api/red/health` → `status: ok`.
- `GET /red` → panel sin errores de consola.
- `GET /acerca-de` → versión y estado del sistema.
- `GET /sismos` → mapa con eventos SGC.
- `GET /api/red/simular` → **404** (no existe en producción).
- `GET /sismos/suscripciones` → **404** (SMS retirado).
- Service worker y manifest cargan por HTTPS.

## 6. Operación

Cron sugerido:

```cron
*/5 * * * * cd /ruta/sigra && php spark red:limpiar --dias 7 >/dev/null 2>&1
```

Monitorea:

- teléfonos en línea y monitoreando,
- detecciones por minuto,
- alertas activas,
- latencia de entrega,
- errores en `writable/logs`.

## 7. App Android

Compila con la URL y clave de producción:

```powershell
cd mobile
# edita local.properties:
# sigra.baseUrl=https://tu-dominio/
# sigra.apiKey=TU_APP_API_TOKEN
.\gradlew.bat :app:assembleRelease
```

Firma el APK/AAB con la keystore de producción antes de publicarlo.
