# Producción y recuperación

Instalación canónica: [INSTALLATION.md](INSTALLATION.md). Android está en desarrollo
en otro repositorio. La red sensorial es experimental; estas comprobaciones operativas
no acreditan utilidad para emergencias.

## Antes de actualizar

1. Exporta la base completa y conserva una copia de los archivos desplegados, `.env`
   y datos necesarios de `writable/` en almacenamiento privado, fuera de la raíz web.
2. Ensaya la restauración del export en una base separada antes de usarlo como respaldo.
   Verifica recuentos y abre catálogo, detalle y reportes en esa copia aislada.
3. Prepara las dependencias con `composer install --no-dev` desde el lock. En Hostinger
   puedes subir ese `vendor/` preparado localmente si no tienes Composer en el servidor.
4. Conserva el `.env` del servidor. Sube el código de una misma versión y aplica
   `php spark migrate --all`. Sin consola, sigue [la guía de subida](../deploy/LEEME-SUBIDA.md)
   y el SQL aditivo correspondiente. **No uses reset, seeds ni setup remoto para actualizar.**
5. Conserva el respaldo de la versión anterior hasta verificar el sitio. Eliminar archivos
   obsoletos requiere seguir el manifiesto del paquete; extraer encima puede dejarlos presentes.

El antiguo `tools/setup-remoto.php` queda deshabilitado y responde 410 por HTTP.
Si alguna vez subiste una copia como `__setup.php`, elimínala del hosting: actualizar
el archivo original no retira copias con otro nombre.

## Configuración

- `CI_ENVIRONMENT = production`; errores detallados y debugbar fuera de las respuestas.
- HTTPS verificado, `app.baseURL` con barra final y dominio correcto. Activa redirección
  HTTPS después de validar la terminación TLS/proxy; no anuncies HSTS sin esa verificación.
- En instalaciones nuevas, document root en `public/`. En instalaciones tradicionales,
  comprueba las denegaciones `.htaccess` antes de cambiar rutas. Nginx necesita reglas propias.
- Secretos privados, únicos y sin query strings; usuario de base restringido a SIGRA.
- Escritura para el proceso PHP únicamente donde corresponda en `writable/`.
- Publicación experimental y FCM desactivados hasta una decisión operativa explícita.

## Verificar después de subir

Abre mapa, histórico, detalle, formulario de reporte, `/red` y `/acerca-de`.
`/api/health` comprueba acceso a la base; no certifica frescura del catálogo.
`/api/red/health` indica actividad del backend; no certifica detección en campo.
La versión esperada de este cambio es **1.3.0**; un override en `.env` puede prevalecer.
Comprueba assets, manifest y service worker, y revisa errores del hosting sin exponerlos.
Las APIs autenticadas deben rechazar claves ausentes o incorrectas.

## Fallos y recuperación

- **Fuente caída:** conserva el catálogo existente. El mapa muestra última actualización
  exitosa o consulta degradada; usa el registro de ingestión para diagnosticar. Reintentos
  y bloqueo están acotados. Sin visitas no hay sincronización automática del mapa;
  cron con `php spark sigra:sync` sigue siendo opcional para actualizar sin visitantes.
- **Base caída:** health devuelve indisponibilidad. Corrige conexión/permisos y verifica
  integridad antes de reanudar escritura; no recrees tablas para tratar un error de conexión.
- **Código incompatible:** restaura el conjunto completo de archivos anterior. No ejecutes
  migraciones down en producción sin evaluar datos nuevos. Las tablas de auditoría son
  aditivas y pueden conservarse al volver al código anterior.
- **Restauración de datos:** hazla primero sobre otra base. Programa una ventana sin
  escrituras, conserva también el estado fallido y cambia la conexión solo tras verificar.
  Restaurar un backup descarta escrituras posteriores: requiere decisión del operador.

Restringe acceso a logs y backups. Define retención según necesidades del despliegue;
no borres sesiones ni registros indiscriminadamente como parte de subir una versión.
La retención legal, alertas al operador, pruebas de carga y ensayo de restauración del
hosting real permanecen pendientes; véase [estado de recomendaciones](RECOMMENDATIONS_STATUS.md).
