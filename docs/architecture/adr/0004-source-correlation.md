# ADR-004 — Identificación y correlación SGC/USGS

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: La deduplicación descartaba la referencia USGS.
- Decisión: Preferir SGC en coincidencia inequívoca por umbrales configurados y conservar ambas referencias y explicación; dejar ambiguos sin fusionar.
- Alternativas: Una fusión del primer candidato pierde trazabilidad.
- Consecuencias: Algoritmo conservador versionado y pruebas de eventos separados y antimeridiano.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).

## Umbrales y limitaciones

La correlación usa los límites configurados de tiempo y distancia y, si ambas
magnitudes están presentes, `quakes.dedupeMagnitudeDelta` (1.0 por defecto).
No se interpreta una magnitud ausente como cero. La coincidencia debe ser única;
un caso ambiguo se conserva por separado. Es una heurística susceptible a falsos
positivos, no una identidad científica demostrada. Las referencias externas se
conservan en metadata. Cambiar los umbrales exige revisar fixtures y resultados.
