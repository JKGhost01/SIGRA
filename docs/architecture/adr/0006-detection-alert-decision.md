# ADR-006 — Detección, candidato y alerta

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: Una señal aislada no confirma un sismo.
- Decisión: Separar RegisterDetectionHandler, evaluación pura, decisión auditada y entrega; conservar estado experimental y bloquear simulaciones reales.
- Alternativas: Publicar directamente desde el registro de un sensor no ofrece evidencia suficiente.
- Consecuencias: Estados, algoritmo y motivo conservados; kill switch y cancelación CLI disponibles.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
