# ADR-002 — Vistas de solo presentación

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: Los require encadenaban permisos, validación y SQL desde Views.
- Decisión: Resolver vistas con CI4 y recibir datos preparados; trasladar validación, comandos y permisos a sus fronteras.
- Alternativas: Mantener procesor.php como fachada de require conserva el acoplamiento implícito.
- Consecuencias: Se eliminan stubs y scripts de negocio; rutas web preservadas.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
