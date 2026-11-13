# ADR-009 — Migración y adaptadores heredados

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: Los nombres PHP y rutas existentes tienen consumidores.
- Decisión: Mantener clases de compatibilidad para modelos, servicios y controladores mientras las rutas usan módulos.
- Alternativas: Borrar todos los nombres de una vez impediría una migración verificable.
- Consecuencias: Retirar fachadas solo tras comprobar consumidores. Se conserva explícitamente la actualización bajo demanda en GET /sismos/sync, limitada por caché y bloqueo, por petición del usuario; el cron no es obligatorio.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
