# Subida de SIGRA modular con actualización automática

El ZIP actualiza la instalación existente. Sus rutas empiezan en `app/`, `public/`
y `docs/deployment/`; extráelo en la misma raíz que contiene `index.php` y `.env`.
No extraerlo dentro de `public/` ni dentro de otra carpeta llamada `sigra`.

1. Antes de activar el código, prepara las dos tablas nuevas de auditoría:
   - Con phpMyAdmin: selecciona **la base actual de SIGRA** e importa
     `docs/deployment/auditoria-modular.sql`, incluido en el ZIP.
   - Con terminal: después de subir los archivos, ejecuta `php spark migrate --all`
     antes de abrir el mapa. Es la alternativa al SQL, no es necesario hacer ambas.
2. Sube y extrae el ZIP en la raíz de SIGRA, permitiendo sobrescribir los archivos
   incluidos. Por FTP, descomprímelo en tu equipo y sube su contenido conservando rutas.
   **Corrección de coordenadas USGS:** después de subir el código y antes de abrir
   el mapa, ejecuta `php spark migrate --all`. Si utilizas phpMyAdmin, importa
   `docs/deployment/corregir-coordenadas-usgs.sql` como alternativa. Repara también
   el histórico, conserva los valores anteriores y se puede repetir. No basta con
   cambiar el JavaScript: los datos antiguos tenían latitud y longitud invertidas.
3. Los archivos obsoletos están enumerados en `docs/deployment/archivos-retirados.txt`.
   Elimínalos para completar la limpieza de la arquitectura; las rutas nuevas ya no
   ejecutan esas vistas antiguas.
4. Abre `/sismos`. El navegador consulta `/sismos/sync` inmediatamente y después cada
   15 segundos. Cuando vence la caché compartida (mínimo 300 segundos), se ejecuta
   la ingesta. No hace falta crear un cron. Sin visitantes no hay consultas periódicas;
   el comando `php spark sigra:sync` permanece disponible opcionalmente.
5. Comprueba `/api/health` y `/sismos/sync`. Si falla una fuente, el mapa conserva lo
   almacenado. Los rechazos 401/403 pausan la fuente al menos 24 horas; 429 y otros
   fallos aplican espera progresiva y respetan `Retry-After`. `synced: false` dentro del
   intervalo es normal: indica que esa petición no volvió a descargar las fuentes.

El ZIP no contiene `.env`, `writable/`, `vendor/`, datos, credenciales ni dependencias
nuevas. Conserva esos directorios y archivos del servidor. `writable/cache` debe seguir
siendo escribible por PHP, igual que antes. Conserva `writable/cache/providers` entre
despliegues: contiene las pausas y validadores de los proveedores. No borres ese
estado para forzar consultas tras un bloqueo.

El SQL se generó desde la migración `20261112000002_CreateArchitectureAudit`. Solo usa
`CREATE TABLE IF NOT EXISTS` para las dos tablas de auditoría; no elimina ni vacía
ninguna tabla. Corresponde al esquema sin prefijos de SIGRA. Si tu instalación usa
un prefijo personalizado de tablas, utiliza la migración CLI para respetarlo.
Si más adelante ejecutas las migraciones, reconocerán las tablas ya creadas y podrán
registrar esta migración sin recrear sus datos.

**No uses `sigra:db-reset` para actualizar.** El helper `tools/setup-remoto.php`
fue deshabilitado. Si lo subiste como `__setup.php` u otro nombre, elimina también
esa copia del hosting. Conserva antes una copia privada de archivos y base de datos.

## Parche 1.3.1

Corrige la lectura de cabeceras múltiples y elimina las cabeceras de página HTML
que CodeIgniter añadía a las respuestas entrantes. No requiere nuevas migraciones
si ya aplicaste la reparación USGS de 1.3.0. Conserva los JSON y `.lock` de
`writable/cache/providers`: el siguiente intento permitido usará el código corregido.
Este parche no cancela las pausas ni garantiza que un proveedor vuelva a estar disponible.
