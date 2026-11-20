# SIGRA 1.4.0.0 · Mapa mundial y experiencia móvil

Versión `1.4.0.0`, build `20261120.1`, canal `stable`.

El paquete actualiza una instalación existente. Extrae su contenido en la raíz
que contiene `app/`, `public/`, `index.php` y `.env`, conservando las rutas.

1. Conserva una copia privada del código anterior y de la base de datos.
2. Usa el ZIP adecuado a tu instalación: desde beta.2 o desde 1.3.1.3.
   Revisa los manifiestos de archivos y hashes incluidos en el paquete.
3. Conserva `.env`, `vendor/` y `writable/`, incluidas las pausas de proveedores.
   Los paquetes no contienen credenciales ni datos del servidor.
4. Desde beta.2 y su hotfix no hay migraciones nuevas. Desde 1.3.1.3 ejecuta
   `php spark migrate --all` antes de servir el mapa: incorpora la identidad
   canónica de eventos introducida en beta.2. No uses reset ni seeds.
5. Si `.env` fija la identidad, actualiza solo estos valores:

   ```ini
   sigra.version = '1.4.0.0'
   sigra.build = '20261120.1'
   sigra.channel = 'stable'
   ```

6. No se añaden dependencias Composer. Ambos service workers renuevan sus
   archivos con `sigra-static-1.4.0.0`. Si el servidor mantiene código anterior
   mediante OPcache, utiliza el mecanismo de recarga de PHP del hosting.
7. Comprueba `/api/health`: versión `1.4.0.0`, build `20261120.1` y base accesible.
   Abre `/sismos`, `/historico` y `/acerca-de`; verifica la ubicación, los filtros,
   el cambio 2D/3D, el listado móvil y la capa de amenaza sísmica mundial.

El relieve y los edificios 3D se han eliminado. La amenaza mundial usa teselas
de `maps.openquake.org`, con atribución y licencia de GEM CC BY-NC-SA 4.0.
Su disponibilidad no determina el estado del catálogo de sismos.

Publicar el código o una release en GitHub no despliega por sí solo el hosting.
La subida al servidor debe utilizar el acceso configurado por su operador.
Para revertir el código, restaura el conjunto anterior y sus dos service workers;
conserva la base y no ejecutes migraciones descendentes automáticamente.
