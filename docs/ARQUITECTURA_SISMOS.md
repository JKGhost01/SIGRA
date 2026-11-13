# Arquitectura del sistema de sismos

La arquitectura vigente y sus decisiones están documentadas en
[architecture/README.md](architecture/README.md).

La ingesta se ejecuta desde CLI o automáticamente mediante `/sismos/sync` cuando
vence la caché compartida. El mapa no requiere configurar cron.
El mapa conserva su ventana de cinco días, filtros y detalle. Los módulos separan
fuentes, normalización, correlación, persistencia, consultas, presentación, reportes,
sensores y decisiones de alerta.

Consulta los [cambios de migración](architecture/migration.md) y el
[contrato OpenAPI](api/openapi.yaml) antes de desplegar.
