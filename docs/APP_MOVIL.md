# App móvil de SIGRA

La página `/app-movil` es la entrada del navbar «App móvil». Conserva el acceso
al panel `/red` desde «Explorar la red en vivo».

## Contadores

El navbar y la página muestran `stats.devices_total` de `/api/red/red`, es decir,
dispositivos registrados en la red. No equivale a personas únicas, instalaciones
ni descargas del APK. La página también muestra `stats.devices_online` por separado.
Sin una lectura correcta, los contadores iniciales muestran `—`.

## Versión y descarga en GitHub

Repositorio oficial: <https://github.com/JKGhost01/SIGRA_MOBILE>.

La página móvil y Acerca de usan el mismo lector de metadatos públicos. Selecciona
la primera release estable entre las diez más recientes; omite borradores y
prereleases. La versión procede del tag (`v1.0.0.0`, por ejemplo). El botón se
activa si esa release contiene un asset `.apk` con URL de descarga del repositorio;
se omiten archivos con `debug`, `unsigned` o `unaligned` en el nombre.

Para habilitar la descarga:

1. Compilar y firmar un APK de producción con la configuración del servidor.
2. Crear una release estable en SIGRA_MOBILE con un tag que coincida con
   `versionName` de Android.
3. Adjuntar el APK firmado, por ejemplo `SIGRA.apk`, y publicar la release.
4. Esperar hasta una hora para que la web actualice sus metadatos.

La web no compila ni publica el APK. Mientras no haya una release estable, lee
`versionName` de `app/build.gradle.kts` en la rama `main` y lo identifica como
versión del código, con descarga pendiente. El 13/11/2026 GitHub indicaba
`1.0.0.0`, Android mínimo API 26 (Android 8.0), sin releases publicadas.

La caché compartida y su bloqueo viven en `writable/cache/github`. Los fallos de
GitHub conservan la última información conocida con un aviso. No se utilizan
tokens privados ni se accede a GitHub desde el navegador del visitante.
La versión web y el contrato API son independientes de la versión Android.
