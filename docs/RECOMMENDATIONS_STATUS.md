# Estado del plan de mejoras

Revisión local del 12 de noviembre de 2026, sobre el código preparado para 1.2.1.
El [plan original](../SIGRA_PLAN_DE_MEJORAS.md) es una hoja de ruta: esta entrega
resuelve instalación, documentación y problemas verificables del backend. No completa
todos los sprints ni certifica el sistema para alertas reales. Todavía no se ha
publicado este conjunto de cambios en GitHub ni desplegado en Hostinger.

## Cambios realizados y evidencia

| Área | Resultado | Evidencia reproducible |
|---|---|---|
| Documentación | URL real, instalación única, relación system/vendor, Android separado y en desarrollo, sin enlaces a una política inexistente | README ES/EN, CONTRIBUTING, INSTALLATION y guía funcional |
| Composer | Identidad SIGRA, autoload de CI4 incluido, lock para PHP 8.2.12 y dependencias de desarrollo reducidas a PHPStan | `composer validate --strict`, instalación desde cero y `check-platform-reqs` |
| Secretos | Generación aleatoria sin mostrar valores; preservación de claves existentes, LF/CRLF y comentarios | `tests/SetupLocalTest.py`; `.env` privado sin cambios |
| Arquitectura | Módulos, puertos, vistas de presentación, casos de uso compartidos y decisiones ADR | ArchitectureTest, directorio docs/architecture |
| Dispositivos | Re-registrar un UUID exige su token vigente; rotación invalida el anterior | Cuatro nuevos casos CGI/MySQL; API_RED |
| Proveedores | Campos finitos y acotados, fechas/IDs válidos, enlaces HTTP(S) seguros, TLS verificado y reintentos acotados por presupuesto | ProviderNormalizationTest; ExternalUrl y CiFeedTransport |
| Correlación | Umbrales de tiempo/distancia/magnitud, ambigüedad conservada, referencias de ambas fuentes | IngestionTest y ADR-004 |
| Frescura | Última actualización exitosa observable; fallo conserva catálogo y timestamp previo | AutomaticRefreshTest; estado visible y traducciones ES/EN |
| PWA y rutas | Assets y página offline para raíz tradicional/public; API y sincronización fuera de caché del worker | test-service-worker.js y pruebas CGI en ambos modos |
| Operación | Actualización aditiva, recuperación documentada y helper de reset HTTP retirado | PRODUCCION, guía de subida y stub de setup-remoto |
| Automatización | Composer, lint, PHPStan, regresión y MariaDB aislada en workflow | .github/workflows/php-lint.yml; ejecución remota aún pendiente |
| Git | Secretos, vendor, runtime, paquetes, dumps y resultados ignorados; fixtures y migraciones visibles | tools/check-git.py |

## Comprobaciones ejecutadas

- Copia nueva mediante clon local sin enlaces duros y superposición de los cambios
  aún no publicados; sin vendor ni `.env` heredados. `composer install` generó el
  autoload optimizado desde el lock. No equivale a clonar estos cambios de GitHub:
  aún no se han subido.
- Composer validate estricto, requisitos runtime con PHP 8.2.12 y audit del lock:
  correctos. Audit no encontró avisos para las tres dependencias gestionadas.
  **El framework incluido en system/ no queda auditado por declarar replace.**
- `composer test`: arquitectura, ingesta, normalización, actualización automática,
  sensores, 29 verificaciones de reportes, 61 respuestas aisladas, fusión sintética,
  presentación, filtros, PWA, 60 verificaciones HTTP API y 11 de protección de reportes.
  El inicializador de secretos se comprobó además con su prueba específica.
- PHPStan nivel 3: 51 archivos de dominio/aplicación, sin errores. No es un análisis
  completo de todos los adaptadores del framework.
- MariaDB **10.4.32** aislada en `/tmp`: tres migraciones desde base vacía,
  idempotencia, persistencia, rollback, auditoría, 40 comprobaciones de reportes y
  atomicidad/cancelación de alertas. **27 recorridos CGI de éxito por cada raíz**,
  tradicional y public; sin push ni consultas a proveedores.
- Comando documentado `php spark migrate --all` con PHP 8.2.12 sobre otra base vacía;
  arranque `php -S ... -t public` con PHP 8.5.12, health 200 y versión 1.2.0,
  catálogo y manifest accesibles. La compatibilidad local probada no sustituye CI.
- Fixture USGS de un evento público con procedencia guardada. Fixture SGC expresamente
  sintético: la consulta pública realizada devolvió 403; no se afirma haber verificado
  disponibilidad real ni todos los formatos SGC en esta revisión.

## Pendientes explícitos

- Confirmar workflow verde en GitHub y MariaDB 10.11; About, topics, preview, Issues,
  tags y Release. Ninguno se publica automáticamente con esta revisión local.
- Auditoría completa del historial por secretos y eventual rotación si se detectan;
  revisar framework incluido, CORS, CSP, HTTPS/proxy y exposición efectiva del hosting.
- Rate limiting integral por IP/dispositivo/token y revocación administrativa;
  los límites de reportes y detecciones existentes no cubren toda la API.
- Fixture real actualizado del SGC; validación con especialistas de la convención
  de coordenadas y criterios de correlación. Conversión de fechas históricas/contratos
  a UTC requiere una migración específica; se conservó la semántica heredada.
- Medir carga, índices/EXPLAIN, cachés, latencia, concurrencia masiva y tamaño del catálogo.
- Ensayo de backup/restauración del despliegue real, alertas al operador, retención de
  logs y revisión de permisos del servidor. Aquí solo se documenta el procedimiento.
- Capturas reales actuales, navegador, accesibilidad y PWA offline en dispositivos;
  no había Chromium disponible. Las pruebas de VM/CGI no sustituyen esa revisión.
- Validación científica en campo, falsos positivos, ruido, relojes y tiempos útiles
  de aviso. La simulación no acredita detección real ni reemplaza autoridades.
- Revisión especializada de privacidad, retención de datos, condiciones de uso y
  atribuciones de terceros. No se presenta este cambio como dictamen legal.

Las casillas marcadas del plan remiten a esta evidencia; las tareas amplias o que
requieren acceso externo permanecen abiertas. No se añadió código Android aquí.
