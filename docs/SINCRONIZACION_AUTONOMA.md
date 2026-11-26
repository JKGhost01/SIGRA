# Sincronización autónoma · SIGRA 1.4.1.0

El catálogo que consume Android se guarda en el servidor. Hasta ahora, sin cron,
la ingesta dependía de las consultas del navegador a `/sismos/sync`; las lecturas
de la API móvil no descargan fuentes. Desde esta versión la instalación de una
tarea programada forma parte del despliegue: funciona aunque no haya navegadores
ni teléfonos conectados. Subir a GitHub no instala una tarea en Hostinger.

## Instalar en el servidor

Prepara dependencias y `.env`, aplica `php spark migrate --all` y configura los
contactos `legal.*` para habilitar reportes. Esta entrega incluye la migración
aditiva `20261120000001_AddReportConsent`; no recrees la base. Conserva los secretos
y `writable/`. Identidad: `1.4.1.0`, build `20261126.1`, canal `stable`.

Con el mismo usuario que ejecuta SIGRA y puede escribir en `writable/`, usa el PHP
CLI del hosting con curl, intl, mbstring y mysqli:

```bash
cd /ruta/absoluta/sigra
python3 tools/install-sync-cron.py --php /usr/bin/php
python3 tools/install-sync-cron.py --php /usr/bin/php --install
crontab -l
```

El primer comando imprime la entrada; el segundo la instala. Repetirlo reemplaza
solo la entrada marcada de esa ruta, conservando las demás tareas. Requiere el
servicio cron activo. No lo ejecutes como root si la web usa otra cuenta.
Si cambias la ruta de instalación, elimina la entrada marcada de la ruta anterior.
Retira también cualquier cron manual antiguo de `sigra:sync` para evitar duplicados.

## Hostinger / hPanel sin Python o sin acceso a crontab

En **Tareas cron**, selecciona ejecución personalizada y configura todos los
campos de frecuencia en `*` (cada minuto). Usa rutas absolutas reales del hosting:

```bash
/bin/bash /home/USUARIO/domains/DOMINIO/public_html/tools/sync-catalog.sh /usr/bin/php
```

Sustituye la ruta del proyecto y del PHP CLI según tu instalación. Si contienen
espacios, encierra cada ruta entre comillas simples. No uses una URL pública ni
un servicio que visite el mapa. El script encuentra la raíz desde su ubicación,
por lo que funciona tanto con `public/` como con la raíz web tradicional.

## Frecuencia, logs y fallos

Cada minuto se invoca `php spark sigra:sync`. El coordinador solo descarga cuando
vence el intervalo compartido (`quakes.cacheSeconds`, mínimo 300 segundos), aplica
el bloqueo de ingesta y conserva las restricciones 403/429, Retry-After y caché
condicional. No se fuerza la ingesta ni se añade Firebase como dependencia.
El intervalo efectivo depende también de las pausas y disponibilidad de cada fuente.

La salida queda en `writable/logs/sync-AAAA-MM-DD.log`, con inicio UTC. Cada día
genera un archivo diferente; incluye estos archivos en la política de rotación y
retención de logs del hosting. La salida del proceso conserva el código de error
de PHP. `sigra:sync` devuelve error si no se actualiza ninguna fuente y existen
errores, también si el estado previo fallido sigue en caché. Un salto por intervalo
o bloqueo sin errores es normal; un fallo parcial conserva las fuentes disponibles.

## Comprobar sin visitantes

1. Ejecuta una vez el comando de hPanel desde consola para detectar errores de
   rutas, extensiones, permisos o conexión a la base.
2. Cierra el mapa y no consultes `/sismos/sync`. Espera al menos dos intervalos
   efectivos de actualización (normalmente 10–12 minutos).
3. Revisa los logs diarios: deben aparecer nuevas ejecuciones UTC cada minuto.
   Inspecciona `writable/cache/catalog-refresh.json`: `timestamp` identifica el
   último intento y `last_success_at` el último éxito, en Unix segundos. Ambos
   deben avanzar cuando las fuentes están disponibles. No confundas ejecución
   de cron con éxito de todas las fuentes.
4. Consulta la API que usa Android y compara con el catálogo guardado. No es
   necesario que haya sismos nuevos: una consulta exitosa puede guardar cero filas.
   `/api/health` verifica versión/base, pero no acredita frescura del catálogo.

Pruebas automatizadas: `python3 tests/ScheduledSyncTest.py` instala dos veces en un
crontab simulado, conserva otras tareas, ejecuta el comando exacto sin HTTP desde
otro directorio, verifica rutas con espacios/comillas, logs, fallos y errores de
lectura del crontab. `tests/Unit/AutomaticRefreshTest.php` comprueba bloqueo, TTL,
fallo, conservación del último éxito y recuperación. No consultan proveedores reales.

## Reversión

Retira únicamente la entrada marcada `# sigra-sync:...` de `crontab -e` o la tarea
creada en hPanel. Restaura el código anterior conservando base y `writable/`; la
migración de consentimiento conserva su evidencia. Sin tarea vuelve a depender
de visitas al mapa. La API v1 y el cliente Android no requieren cambios para usar
el catálogo actualizado por CLI.
