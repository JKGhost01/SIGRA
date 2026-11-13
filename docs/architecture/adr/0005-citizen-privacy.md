# ADR-005 — Privacidad y abuso de reportes

- Estado: aceptado
- Fecha: 2026-11-12
- Contexto: Los reportes son observaciones, no eventos oficiales.
- Decisión: Conservar HMAC por visitante/red, cuotas transaccionales, desafío CSRF/sesión, umbral y coincidencia SGC única.
- Alternativas: Login obligatorio cambia el acceso público; guardar IP cruda no es necesario.
- Consecuencias: Intensidad percibida explícita en contratos nuevos; retención y expiración existentes conservadas.
- Validación: `bash tools/test-architecture.sh`; para persistencia, runner MySQL aislado descrito en [arquitectura](../README.md).
