# ADR-001 — Monolito modular por capacidades

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: La lógica estaba repartida entre controladores, vistas y servicios globales.
- Decisión: Organizar SeismicCatalog, SeismicIngestion, CitizenReports, SensorNetwork, Alerting y System en Domain/Application/Infrastructure/Http/Presentation según corresponda.
- Alternativas: Microservicios y un módulo por tabla añaden costes sin aislamiento de negocio.
- Consecuencias: Un despliegue y límites comprobados; contratos internos explícitos.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
