# ADR-010 — Cliente móvil en repositorio separado

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: La aplicación Android sigue en desarrollo fuera del backend.
- Decisión: Definir integración mediante OpenAPI y fixtures; no crear mobile ni enlazar un repositorio que no existe.
- Alternativas: Copiar Kotlin dentro del monolito mezcla ciclos de vida.
- Consecuencias: Contrato versionado y pruebas del backend; la validación del cliente real queda en su propio repositorio.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
