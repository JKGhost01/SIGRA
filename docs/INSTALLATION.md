# Instalación soportada / Supported installation

Este procedimiento instala SIGRA web/backend desde los archivos de esta versión.
Android está en desarrollo en otro repositorio. El monitoreo sensorial es experimental.
El README en inglés, su traducción y CONTRIBUTING remiten a esta misma secuencia.

## Requisitos

- PHP 8.2 o posterior, con `curl`, `intl`, `mbstring`, `mysqli` y las extensiones estándar de PHP.
- Composer 2. El lock resuelve con una plataforma mínima PHP 8.2.12.
- Una base vacía MySQL/MariaDB y un usuario con permisos sobre esa base.
- Linux/Bash para los comandos publicados. No se afirma una instalación Windows verificada.
- Para pruebas: Node 22+ (CI usa 22), Python 3 y PHP-CGI; no son dependencias del frontend desplegado.

`system/` contiene el framework CI4 4.7.3 versionado, con sus avisos de licencia.
Composer carga `CodeIgniter\` desde ese directorio; `replace` declara esa versión
incluida para evitar descargar una segunda copia. No se obtiene el framework desde
`vendor/`. Este último se genera con `composer install` y no se sube a Git.
Actualizar `system/` requiere un cambio separado, trazable y probado; Composer audit
no sustituye la revisión de ese framework incluido.

## Entorno comprobado

Pruebas locales: PHP 8.2.12 y arranque con PHP 8.5.12; MariaDB 10.4.32;
Composer 2.11.2. El workflow solicita PHP 8.2 y MariaDB 10.11, pero necesita
confirmación de ejecución en GitHub. No se ha probado una versión concreta de
MySQL ni una instalación Windows en esta revisión.

## Instalación nueva

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
```

El inicializador escribe claves aleatorias de 32 bytes en `.env`, nunca las muestra y
conserva valores reales ya configurados. No lo ejecutes para rotar claves de un sitio
existente: esa rotación requiere planificar sesiones y los HMAC de reportes.

Crea una base de desarrollo vacía con tu administrador MySQL o phpMyAdmin:

```sql
CREATE DATABASE sigra CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

Edita `.env`: `app.baseURL`, `database.default.hostname`, `database.default.database`,
`database.default.username`, `database.default.password` y el puerto, si corresponde.
El usuario de producción debe limitarse a la base de SIGRA; permisos para crear/alterar
esquema solo son necesarios al aplicar migraciones.

```bash
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

Con `app.baseURL = 'http://127.0.0.1:8080/'`, abre
`http://127.0.0.1:8080/index.php/sismos`. No es obligatorio ejecutar una sincronización
manual: el mapa consulta el caso de uso de ingesta al cargar, con caché de 60 segundos
y exclusión de concurrencia. Fallos externos muestran estado degradado y no borran el
histórico. También existe `php spark sigra:sync` para uso manual o cron opcional.

## Despliegue existente y Hostinger

No cambies la raíz web de una instalación existente a ciegas. El frontend conserva
compatibilidad con la raíz tradicional y con `public/`. Para instalaciones nuevas,
configura el document root en `public/`, dejando `.env`, `app`, `system`, `vendor` y
`writable` fuera del alcance web. En el modo tradicional conserva las reglas de bloqueo
de `.htaccess`; no equivale a aislar físicamente esos directorios.

No reemplaces el `.env` del servidor ni sus datos de `writable/`. Aplica migraciones
aditivas; nunca uses un reset de la base para actualizar. Si no tienes Composer en el
hosting, prepara `vendor/` desde el lock en un equipo confiable con
`composer install --no-dev` y súbelo como dependencia de despliegue, no como código Git.
El ZIP incremental sigue suponiendo que el hosting ya tiene las dependencias de la
versión anterior; las dos bibliotecas runtime no cambiaron de versión.

La configuración de producción usa HTTPS y `CI_ENVIRONMENT = production`. Activa
`app.forceGlobalSecureRequests = true` después de comprobar TLS y la configuración del
proxy del hosting. No actives FCM ni publicación experimental como parte de instalar:
`.env.example` deja `red.alertingEnabled = false` y `red.fcmEnabled = false`.

Consulta [migración modular](architecture/migration.md) y [subida por archivos](../deploy/LEEME-SUBIDA.md).

## Diagnóstico

- **Faltan extensiones:** compara `php -m` y `composer check-platform-reqs`; CLI y PHP del hosting pueden usar versiones distintas.
- **No encuentra vendor/autoload.php:** ejecuta Composer desde la raíz del proyecto.
- **No puede escribir caché/sesiones:** da al usuario PHP acceso a `writable/cache`, `writable/session` y `writable/logs`, sin hacer públicos esos directorios.
- **Base inaccesible:** revisa nombre, host/socket, puerto y permisos en el archivo privado; no pegues contraseñas en Issues.
- **Error 500:** revisa `writable/logs` o el registro PHP del hosting; mantén errores detallados fuera de la respuesta pública.
- **Versión antigua:** una asignación `sigra.version` o `sigra.build` en `.env` prevalece sobre la configuración versionada.
- **Fuentes caídas:** health puede responder aunque los eventos no estén actualizados. El mapa distingue última actualización exitosa y fallo de consulta.

## Comprobaciones

```bash
composer validate --strict
composer check-platform-reqs
composer test
composer analyse
composer audit
python3 tools/check-git.py
```

Los resultados ejecutados y las tareas que requieren recursos externos se registran en
[RECOMMENDATIONS_STATUS.md](RECOMMENDATIONS_STATUS.md). No se afirma que una prueba
sintética demuestre una red de alertas científicamente validada.
