# Pruebas de SIGRA

Requisitos y dependencias: [INSTALLATION.md](INSTALLATION.md).

## Regresión sin base de producción

```bash
composer test
composer analyse
python3 tools/check-git.py
```

`composer test` ejecuta arquitectura, dominio, contratos heredados/v1, normalización
SGC/USGS con fixtures, fallos de ingestión, concurrencia de actualización, reportes,
fusión experimental, renderizado modular, filtros JS, service workers y rutas PHP-CGI.
Las pruebas HTTP excluyen expresamente el `.env` local. PHPStan nivel 3 cubre dominio
y aplicación; los adaptadores CI4 aún no tienen cobertura estática completa.

## MySQL/MariaDB aislada

Crea una base vacía cuyo nombre cumpla `sigra_*_test`, con usuario exclusivo de pruebas.
Nunca selecciones la base real. Puedes configurar `SIGRA_TEST_USER`,
`SIGRA_TEST_PASSWORD` y `SIGRA_TEST_PORT` mediante tu entorno privado.

```bash
python3 tools/test-mysql-architecture.py --database sigra_local_test
# O un servidor aislado que escuche mediante socket:
python3 tools/test-mysql-architecture.py --database sigra_local_test --socket /ruta/mysql.sock
```

El runner aplica migraciones, comprueba persistencia/rollback, privacidad de reportes,
auditoría y cancelación de alertas. Prueba HTTP con raíz tradicional y `public/`,
registro y rotación autenticada de dispositivos, rechazo del token anterior,
detecciones duplicadas y formulario con CSRF. Usa un writable temporal y limpia sus
fixtures. No envía push ni depende de fuentes externas activas.

CI repite estas comprobaciones con PHP 8.2, Node 22 y MariaDB 10.11 en un servicio
separado. La configuración del workflow no equivale a una ejecución verde en GitHub;
espera ese resultado antes de publicar una Release.

## Navegador real (opcional)

Con un servidor de staging y navegador Chromium instalado:

```bash
cd tools
npm ci
CHROME_PATH=/ruta/chromium node test-web.js
```

Lee la configuración de `tools/test-web.js` antes de ejecutarlo; no lo apuntes a un
sitio productivo para pruebas experimentales. Las capturas se guardan bajo `writable/`
y quedan ignoradas en Git. Las pruebas de DOM/CGI no sustituyen geolocalización, mapa,
accesibilidad o comportamiento offline comprobados en un navegador real.

Los resultados y límites de esta revisión están en
[RECOMMENDATIONS_STATUS.md](RECOMMENDATIONS_STATUS.md). Las cifras de simulación de
ondas P no validan precisión en campo ni tiempos útiles de aviso.
