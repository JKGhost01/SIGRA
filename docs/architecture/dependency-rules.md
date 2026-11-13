# Reglas de dependencia

1. `Http -> Application -> Domain`. Los controladores leen transporte, llaman casos de
   uso y producen respuestas. No consultan modelos ni SQL.
2. Domain no importa CI4, Config, Application, Http, persistencia ni servicios globales.
3. Application recibe puertos, configuración inmutable y reloj. No resuelve `env`,
   `service`, `session`, `db_connect` ni `cache`.
4. Infrastructure implementa puertos. Una implementación solo escribe las tablas de
   su módulo. El acceso entre módulos pasa por contratos públicos de Application.
5. Presentation recibe datos preparados. No autentica, valida como barrera de seguridad,
   invoca servicios, incluye scripts ejecutables ni escribe datos.
6. `Config\Services` es la raíz de composición y puede construir infraestructura.
   Los adaptadores antiguos son la excepción transitoria explícita de compatibilidad.
7. Las lecturas API no sincronizan fuentes, expiran filas ni marcan entregas como vistas.
   Excepción explícita de compatibilidad: `/sismos/sync` coordina una ingesta al vencer
   el intervalo compartido; usa el mismo bloqueo que CLI y conserva los datos ante fallos.
   La lógica sigue en Application/Infrastructure, nunca en vistas ni SQL del controlador.

`php tests/Unit/ArchitectureTest.php` examina tokens PHP de los módulos y falla ante
acceso a CI4 desde dominio/aplicación, SQL en controladores, comportamiento ejecutable
en vistas o referencias directas a infraestructura de otro módulo. Es un control de
dependencias estático acotado, no un analizador completo de tipos ni una prueba científica.

Las interfaces evitan tipos CI4 y query builders. Los DTO de lectura preservan registros
heredados internamente; la salida pública pasa por recursos explícitos. Los datos de
reporte nuevos nombran `felt_intensity`; únicamente el mapper HTTP interpreta el campo
heredado `magnitud` como intensidad percibida.
