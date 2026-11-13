# ADR-007 — Composición e inyección en CI4

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: Los servicios instanciaban modelos y clientes internamente.
- Decisión: Construir dependencias en Config Services e inyectar puertos, configuración y Clock.
- Alternativas: Inyectar objetos por constructores de controllers sin integración CI4 no funciona de forma automática.
- Consecuencias: Los controladores resuelven casos de uso; el dominio y las pruebas no requieren servidor HTTP.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
