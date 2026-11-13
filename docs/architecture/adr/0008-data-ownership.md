# ADR-008 — Propiedad de tablas y transacciones

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: La reconciliación ciudadana escribía directamente sismos.
- Decisión: Asignar tablas a módulos y usar ReportEvidenceCatalog, EventWriter, DetectionRepository y AlertStatisticsReader para cruzar límites.
- Alternativas: Un repositorio SQL compartido sin dueño permite escrituras arbitrarias.
- Consecuencias: Transacciones ciudadanas comparten conexión; las llamadas de red terminan antes de persistir catálogo.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
