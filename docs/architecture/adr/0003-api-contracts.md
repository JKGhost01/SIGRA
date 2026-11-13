# ADR-003 — Contratos y versiones de API

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: Android y el mapa consumen JSON existente.
- Decisión: Conservar recursos de éxito y errores legacy; versionar errores nuevos bajo /api/v1 y documentarlos con OpenAPI.
- Alternativas: Modificar silenciosamente el envelope rompe consumidores.
- Consecuencias: Fixtures del serializador original y tests CGI; X-Request-ID para correlación.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
