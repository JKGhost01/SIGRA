# SIGRA — Propuesta de arquitectura modular rigurosa para CodeIgniter 4

> **Estado:** propuesta de refactorización progresiva, no implementación existente.  
> **Proyecto:** [JKGhost01/SIGRA](https://github.com/JKGhost01/SIGRA)  
> **Convención de referencia:** [JKGhost01/MVC-CI4-views_logic](https://github.com/JKGhost01/MVC-CI4-views_logic)  
> **Fecha de análisis:** 12 de noviembre de 2026  
> **Objetivo:** convertir el MVC modular propio en una arquitectura **modular por capacidades, con contratos explícitos, capas bien definidas, pruebas y controles de calidad**, preservando el espíritu de los controladores ligeros y la organización por operaciones.

---

## 0. Alcance y principio rector

Esta propuesta **no significa reescribir SIGRA desde cero**, abandonar CodeIgniter 4 ni imponer microservicios. Propone evolucionar su diseño actual hacia un **monolito modular**: una aplicación desplegable, con módulos aislados internamente y reglas claras de dependencia.

**Se conserva del patrón original:**

- El **controlador es una frontera HTTP y un coordinador ligero**; no es el lugar de las reglas sísmicas ni de consultas SQL.
- El trabajo se organiza en **operaciones reconocibles** (`view`, `list`, `create`, `edit`, `delete` cuando corresponda).
- Cada operación tiene entrada, permisos, validación, ejecución y presentación identificables.
- Los layouts generales se mantienen separados de las pantallas de cada módulo.
- Los modelos de CI4 siguen siendo útiles para persistencia; no se descartan por moda arquitectónica.

**Se corrige del patrón original:**

- Un archivo bajo `Views/` **no ejecuta casos de uso, transacciones, autenticación ni SQL**. Las vistas se limitan a renderizar datos preparados.
- Las funciones conceptuales de `validator.php` y `procesor.php` evolucionan a **validadores/objetos de entrada** y **casos de uso tipados**, respectivamente.
- `deny.php` es una **presentación del rechazo**, nunca el mecanismo que protege la ruta. El control real lo ejercen filtros/políticas y se devuelve HTTP 401/403.
- No se obliga a crear 25 archivos de vista por cada tabla si una operación no aplica. **Consistencia no es crear archivos vacíos**.
- Para SIGRA, la unidad principal de organización es la **capacidad de negocio** (catálogo sísmico, ingestión, red, reportes, alertas), y dentro de esa capacidad se conservan entidades y operaciones. Una tabla auxiliar no tiene por qué convertirse en un módulo independiente.

> **Decisión arquitectónica propuesta (ADR-001):** “Monolito modular con casos de uso y vistas de solo presentación”.

## 1. Hallazgos verificados y limitaciones de esta revisión

La revisión se basa en archivos públicos accesibles de los dos repositorios. No se ejecutó el proyecto, no se hizo auditoría completa de dependencias y puede haber cambios posteriores a la fecha indicada. Los ejemplos de código del documento son **referenciales**; deben ajustarse a la versión exacta de CI4 y al autoloading del proyecto antes de usarlos.

| Evidencia | Impacto | Prioridad |
|---|---|---|
| `app/Controllers/Sismos.php` declara que solo enruta, pero también valida tokens, interpreta query params, consulta modelos, serializa API, calcula distancias y decide niveles de alerta. | El contrato arquitectónico declarado no coincide con la implementación. | P0 |
| `app/Views/sismos/list/index.php` supera 900 líneas y mezcla consultas, transformación de datos, HTML y JavaScript. | Difícil de probar, revisar y mantener. | P0 |
| `app/Views/sismos/create/index.php` utiliza `$sesionValida = true` y `$tienePermiso = true` como sustitutos de permisos. | Estas variables no deben considerarse controles de seguridad reales. El reporte público necesita controles específicos, no necesariamente login. | P0 |
| `create/validator.php` y `create/procesor.php` ejecutan comportamiento mediante `require` en cadena. | Acoplamiento implícito por variables compartidas y retornos de scripts. | P0 |
| `QuakeSyncService` descarga, combina, deduplica, persiste y reconcilia reportes. | Puede dividirse en responsabilidades testeables y sustituibles. | P1 |
| `ApiRed.php` mezcla adaptador HTTP, autenticación, parsing, resolución del dispositivo y lógica de aplicación. | Es difícil estandarizar seguridad y reutilizar casos de uso. | P1 |
| `app/Config/Routes.php` contiene rutas para catálogo, red, API y páginas estáticas. | Crecerá sin una estructura clara por módulos/versiones. | P1 |
| Existe una `ApiResponses` reutilizable. | Buen punto de partida: evolucionar a un formateador HTTP central sin mezclarlo con reglas de negocio. | Conservar/mejorar |
| La validación `CitizenReportService` ya contiene comprobaciones y reglas de abuso. | Se debe **extraer sin perder** restricciones ni garantías actuales. | Conservar/mejorar |

Referencias consultadas:

- [`MVC-CI4-views_logic/README.md`](https://github.com/JKGhost01/MVC-CI4-views_logic/blob/main/README.md)
- [`SIGRA/app/Controllers/Sismos.php`](https://github.com/JKGhost01/SIGRA/blob/main/app/Controllers/Sismos.php)
- [`SIGRA/app/Controllers/ApiRed.php`](https://github.com/JKGhost01/SIGRA/blob/main/app/Controllers/ApiRed.php)
- [`SIGRA/app/Views/sismos/list/index.php`](https://github.com/JKGhost01/SIGRA/blob/main/app/Views/sismos/list/index.php)
- [`SIGRA/app/Views/sismos/create/`](https://github.com/JKGhost01/SIGRA/tree/main/app/Views/sismos/create)
- [`SIGRA/app/Libraries/QuakeSyncService.php`](https://github.com/JKGhost01/SIGRA/blob/main/app/Libraries/QuakeSyncService.php)
- [`SIGRA/app/Libraries/CitizenReportService.php`](https://github.com/JKGhost01/SIGRA/blob/main/app/Libraries/CitizenReportService.php)
- [`SIGRA/app/Config/Routes.php`](https://github.com/JKGhost01/SIGRA/blob/main/app/Config/Routes.php)

---

## 2. Objetivos medibles de arquitectura

1. **Una responsabilidad por clase:** HTTP, negocio, proveedor externo, persistencia y presentación no se mezclan.
2. **Dependencias dirigidas hacia el dominio:** dominio no depende de CI4, MySQL, `$_SERVER`, `session()` ni `service()`.
3. **Operaciones explícitas:** los flujos `list/view/create/edit/delete` permanecen reconocibles sin convertir cada operación en 5 scripts PHP obligatorios.
4. **Contratos testables:** clientes de SGC/USGS, repositorios, reloj y notificaciones se reemplazan por dobles en pruebas.
5. **Seguridad en fronteras:** filtros/autenticación, políticas por caso de uso, validación y límites de tasa independientes de la vista.
6. **Compatibilidad:** mantener rutas y respuestas públicas mientras los adaptadores internos se migran.
7. **Auditabilidad:** eventos de dominio, versiones, procedencia de datos, bitácoras estructuradas y decisiones documentadas.
8. **Reproducibilidad:** tests y análisis estático ejecutables por terceros desde una instalación limpia.
9. **Robustez operativa:** fallos de un proveedor no corrompen el catálogo ni generan alertas sin comprobación.
10. **Simplicidad controlada:** no agregar abstracciones vacías a consultas o pantallas triviales.

### Métricas de aceptación propuestas

- Cero consultas a base de datos y cero escrituras en `Presentation/Views/`.
- Cero `require APPPATH . 'Views/.../index.php'` como mecanismo permanente de despacho HTTP al terminar la migración.
- Ningún controlador que contenga lógica sísmica, deduplicación o cálculo de alerta.
- Cero autenticaciones basadas en variables booleanas fijas en vistas.
- Todo endpoint de escritura tiene política, validación, control de errores y pruebas de autorización.
- Todos los casos de uso críticos (ingestión, deduplicación, reportes y detecciones) tienen pruebas unitarias y de integración.
- Módulos independientes de un servidor HTTP para ejecutar sus reglas en pruebas.
- Ningún cambio de formato de API sin prueba de contrato y política de versionado.

## 3. Arquitectura objetivo: monolito modular en 5 zonas

```text
                        Cliente WEB / Cliente API / CLI
                                     |
                         [HTTP / CLI / filtros]
                                     |
                        [Controladores/adaptadores]
                                     |
                           [CASOS DE USO]
                         comandos / consultas
                                     |
                            [DOMINIO SIGRA]
                   reglas / entidades / políticas
                                     ^
                                     |
                 [PUERTOS: interfaces de repositorios,
                  proveedores, reloj, publicaciones]
                                     ^
                                     |
                            [INFRAESTRUCTURA]
                  MySQL / SGC / USGS / caché / logs

          La presentación (Views/ViewModels) recibe datos
              preparados, nunca ejecuta el flujo.
```

**Las dependencias del código van hacia el interior**: `Http -> Application -> Domain`. Infraestructura implementa interfaces del dominio o aplicación. El dominio no importa clases de infraestructura. Un mecanismo de inyección de dependencias une ambos extremos en el arranque.

### Las cinco zonas

| Zona | Contiene | No debe contener |
|---|---|---|
| `Domain` | Entidades, value objects, reglas invariantes, políticas puras, contratos apropiados. | `Request`, `Response`, SQL, HTML, servicios globales de CI4. |
| `Application` | Casos de uso, transacciones coordinadas, comandos/consultas, DTOs, orquestación. | HTML, rutas, SQL concreto, lógica de presentación. |
| `Infrastructure` | Repositorios CI4/MySQL, adaptadores SGC/USGS, cache, reloj, logging, publishers. | Decidir permisos de usuario final o contenido de pantallas. |
| `Http` | Controllers, parsers/requests, autenticación, filtros, serializers, código de estado. | Regla científica, persistencia directa, deduplicación. |
| `Presentation` | Views, componentes, layouts, ViewModels, assets web. | Validación como barrera de seguridad, writes, invocación de servicios. |

**Excepción razonable:** un controlador puede mapear un request simple a un DTO y retornar una respuesta; no necesita delegar cada `if` de transporte a veinte clases. La regla es **no meter negocio** allí.

## 4. Límites de módulos y propiedad de datos

Dividir por capacidad evita confundir una tabla física con un concepto de negocio.

| Módulo | Responsable de | Ejemplos de entidades/datos | No debe hacer |
|---|---|---|---|
| `SeismicCatalog` | Consultas, detalle, histórico y catálogo normalizado. | `SeismicEvent`, `SourceReference`. | Descargar feeds directamente al pintar un mapa. |
| `SeismicIngestion` | Conectar fuentes, normalizar, deduplicar y sincronizar. | `IngestionBatch`, `ProviderEvent`, reglas de correlación. | Generar HTML o alertas ciudadanas. |
| `CitizenReports` | Capturar, limitar, conservar y contrastar testimonios. | `CitizenReport`, `ReporterFingerprint`, estado de contraste. | Modificar datos oficiales de origen sin trazabilidad. |
| `SensorNetwork` | Registro de sensores, latidos, detecciones, calidad del dispositivo y antiabuso. | `Device`, `Detection`, `SensorStatus`. | Declarar alertas operativas por sí mismo. |
| `Alerting` | Evaluación de evidencia, estados, distribución, cancelación y auditoría de alertas. | `AlertCandidate`, `AlertDecision`, `AlertDelivery`. | Tratar una predicción experimental como certeza. |
| `Shared` | Solo elementos realmente transversales (identificadores, reloj, correlación). | Interfaces pequeñas, errores base, paginación genérica. | Convertirse en una carpeta de “todo lo demás”. |
| `System` | Salud, versión, configuración pública segura, supervisión. | `HealthResult`, `VersionInfo`. | Exponer secretos o detalles internos a visitantes. |

**Regla de propiedad:** cada módulo modifica sus propias tablas mediante sus repositorios. Otros módulos consumen **interfaces públicas de aplicación** o eventos. Evitar que `SensorNetwork` escriba directamente tablas de `SeismicCatalog`.

**Regla de modularidad:** si una tabla existe solo como detalle técnico de otra entidad, **no crearle automáticamente** controlador, vista y cinco operaciones CRUD. La regla original “un conjunto MVC por tabla” es útil en módulos administrativos simples, pero puede romper límites de negocio si se aplica mecánicamente a todos los datos científicos.

## 5. Estructura de directorios objetivo

La siguiente es una **estructura propuesta**, no un listado de archivos que ya existen. Las carpetas de presentación pueden permanecer temporalmente en `app/Views` durante la migración.

```text
app/
├── Config/
│   ├── Routes.php                 # composición global mínima
│   ├── Services.php               # composición/inyección de dependencias
│   ├── Filters.php
│   └── Sigra.php
├── Shared/
│   ├── Domain/
│   │   ├── Clock.php
│   │   └── DomainException.php
│   ├── Application/
│   │   ├── Pagination.php
│   │   └── TransactionManager.php
│   ├── Infrastructure/
│   │   ├── SystemClock.php
│   │   └── Logging/
│   └── Http/
│       ├── ErrorResponder.php
│       ├── ApiEnvelope.php
│       └── CorrelationId.php
├── Modules/
│   ├── SeismicCatalog/
│   │   ├── Domain/
│   │   │   ├── Entities/SeismicEvent.php
│   │   │   ├── ValueObjects/Coordinates.php
│   │   │   ├── ValueObjects/Magnitude.php
│   │   │   └── Contracts/SeismicEventRepository.php
│   │   ├── Application/
│   │   │   ├── Queries/ListEvents/
│   │   │   │   ├── ListEventsQuery.php
│   │   │   │   └── ListEventsHandler.php
│   │   │   ├── Queries/GetEvent/GetEventHandler.php
│   │   │   └── DTO/SeismicEventDTO.php
│   │   ├── Infrastructure/
│   │   │   └── Persistence/CiSeismicEventRepository.php
│   │   ├── Http/
│   │   │   ├── Controllers/Web/SeismicCatalogController.php
│   │   │   ├── Controllers/Api/V1/SeismicEventsController.php
│   │   │   ├── Requests/ListEventsRequest.php
│   │   │   └── Resources/SeismicEventResource.php
│   │   └── Presentation/
│   │       ├── ViewModels/ListEventsViewModel.php
│   │       └── Views/sismos/
│   │           ├── list/index.php
│   │           ├── detail/index.php
│   │           └── historico/index.php
│   ├── SeismicIngestion/
│   │   ├── Domain/
│   │   │   ├── Contracts/SeismicSource.php
│   │   │   └── Services/EventDeduplicator.php
│   │   ├── Application/
│   │   │   └── Commands/SyncEvents/SyncEventsHandler.php
│   │   ├── Infrastructure/
│   │   │   ├── Sources/SgcSourceAdapter.php
│   │   │   └── Sources/UsgsSourceAdapter.php
│   │   └── Console/SyncEventsCommand.php
│   ├── CitizenReports/
│   │   ├── Domain/
│   │   ├── Application/Commands/SubmitReport/
│   │   ├── Infrastructure/Persistence/
│   │   ├── Http/Controllers/
│   │   └── Presentation/Views/reports/create/
│   │       ├── index.php
│   │       ├── form.php
│   │       └── deny.php             # solo representación de error
│   ├── SensorNetwork/
│   │   ├── Domain/
│   │   ├── Application/Commands/RegisterDetection/
│   │   ├── Infrastructure/
│   │   └── Http/Controllers/Api/V1/
│   ├── Alerting/
│   │   ├── Domain/
│   │   ├── Application/
│   │   ├── Infrastructure/
│   │   └── Http/Controllers/Api/V1/
│   └── System/
│       ├── Application/
│       └── Http/Controllers/
├── Views/
│   └── layouts/                    # layouts compartidos, si conviene
└── Database/
    ├── Migrations/
    └── Seeds/
public/
├── assets/css/
├── assets/js/modules/
│   ├── seismic-map/
│   └── citizen-reports/
└── assets/images/
tests/
├── Unit/
├── Integration/
├── Feature/
└── Contract/
docs/
├── architecture/
│   ├── README.md
│   ├── module-boundaries.md
│   ├── dependency-rules.md
│   └── adr/
└── api/
    └── openapi.yaml
```

**Nota de implementación CI4:** registrar correctamente el namespace/autoload PSR-4 de `App\Modules\` y resolver las vistas a través de CI4. Verificar esta configuración con tests; **no** depender de que una ruta de ejemplo con namespace funcione sin configurarla. Empezar por la estructura de clases y mantener las vistas en su lugar actual es totalmente válido.

## 6. Evolución exacta del patrón `index/deny/form/validator/procesor`

Tu repositorio de referencia diferencia `index.php`, `deny.php`, `form.php`, `validator.php` y `procesor.php` para cada operación. La intención (encapsular una operación) es buena. Lo que debe cambiar es **dónde vive el comportamiento ejecutable**.

| Convención original | Equivalente más riguroso | Ubicación recomendada |
|---|---|---|
| `operation/index.php` | Entrada/presentación de la operación; orquestación en controlador HTTP pequeño. | `Presentation/Views/.../index.php` + `Http/Controllers/...` |
| `operation/deny.php` | Plantilla para 401/403; la autorización se decidió antes. | `Presentation/Views/shared/forbidden.php` o vista específica. |
| `operation/form.php` | Formulario, componente o pantalla, **sin writes**. | `Presentation/Views/.../form.php` |
| `operation/validator.php` | Reglas de entrada, DTO y validación de negocio por capas. | `Http/Requests/*Request.php` + `Domain`/`Application`. |
| `operation/procesor.php` | Caso de uso ejecutable con contrato y tests. | `Application/Commands/<Operacion>/*Handler.php` |
| `Model.php` | Adaptador de persistencia CI4; opcionalmente repositorio con interfaz. | `Infrastructure/Persistence/*Repository.php` |
| `Controller.php` | Adaptador web/API con máximo código de transporte. | `Http/Controllers/Web` o `Http/Controllers/Api`. |

### Reglas operativas

- No recrear `validator.php` y `procesor.php` como simples fachadas que vuelven a ejecutar código con `require`.
- Si una pantalla `list` no procesa datos, **no crear** un `procesor.php` vacío.
- `view` significa *consultar detalle*; `list` significa *consultar colección*. No duplicar un mismo endpoint con dos nombres.
- `create`, `edit` y `delete` no deben aparecer públicamente para registros sísmicos oficiales sin reglas de autoridad; **un reporte ciudadano** es un concepto diferente a **crear un sismo oficial**.
- La autorización es **defensa en profundidad**: un filtro rechaza accesos claramente no permitidos y el caso de uso vuelve a comprobar la política contextual cuando sea necesario.
- Para vistas normales usar el mecanismo de render de CodeIgniter, no `require` de scripts PHP de negocio.

### Flujo propuesto para `create` de reporte ciudadano

```text
POST /sismos/create (ruta de compatibilidad)
  -> filtro CSRF + reportguard + límite de tasa
  -> CitizenReportController::store()
  -> SubmitCitizenReportRequest (parse y formato)
  -> SubmitCitizenReportHandler
       -> Domain: validar ubicación/fecha/intensidad
       -> Policy: antifraude, límites y consentimiento
       -> CitizenReportRepository::savePending()
       -> evento ReportSubmitted (si aplica)
  -> PRG: redirect HTTP 303 a pantalla de confirmación

Si falla:
  -> error tipado -> HTTP 422/429/503 -> form.php con errores seguros
```

**Importante:** los reportes ciudadanos actuales pueden ser públicos. No convertirlos a “requiere iniciar sesión” por automatismo. Reemplazar los dos `true` de la vista por reglas reales de acceso público responsable y mantener antiabuso y CSRF.

## 7. Controladores estrictamente ligeros, pero útiles

### Contrato de controlador web

Puede: leer parámetros, invocar un caso de uso, preparar respuesta y renderizar vista.  
No puede: abrir transacciones manuales, deduplicar eventos, computar el nivel de alerta, acceder a SGC/USGS o construir SQL.

**Pseudocódigo PHP ilustrativo:**

```php
final class SeismicCatalogController extends BaseController
{
    public function __construct(
        private readonly ListEventsHandler $listEvents,
    ) {}

    public function index(): string
    {
        $request = ListEventsRequest::fromHttp($this->request);
        $result = $this->listEvents->handle($request->toQuery());
        $vm = ListEventsViewModel::fromResult($result);

        return view('sismos/list/index', ['vm' => $vm]);
    }
}
```

**No copiar directamente:** inyectar las dependencias mediante la configuración de servicios/factorías apropiada a CI4 en uso. Este es un contrato de diseño.

### Contrato de controlador API

Puede: negociar JSON, autenticar vía filtro, validar request, crear DTO, invocar handler y mapear resultados/errores. No puede retornar arrays del modelo sin un resource explícito.

```text
GET /api/v1/sismos/recent
    -> filtro de autenticación (según alcance del endpoint)
    -> RecentEventsRequest
    -> ListEventsHandler
    -> SeismicEventResource
    -> respuesta JSON consistente
```

**No modificar abruptamente** `GET /api/sismos/recent` u otras rutas consumidas. Mantener adaptadores de compatibilidad mientras se publica `/api/v1/...`.

## 8. Contratos de dominio y ejemplos de código

Ejemplo de value object que valida invariantes en un solo sitio:

```php
<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Latitud fuera de rango.');
        }
        if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Longitud fuera de rango.');
        }
    }
}
```

Ejemplo de **puerto de lectura** que no menciona `CodeIgniter\Model`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Contracts;

use App\Modules\SeismicCatalog\Application\Queries\ListEvents\ListEventsQuery;
use App\Modules\SeismicCatalog\Application\DTO\SeismicEventDTO;

interface SeismicEventReader
{
    /** @return list<SeismicEventDTO> */
    public function list(ListEventsQuery $query): array;
}
```

Este puerto de **lectura de aplicación** depende de tipos de `Application`, por eso vive en `Application/Contracts` y no en `Domain`. Para puertos del dominio, usar firmas que dependan solo de entidades y value objects del dominio. La implementación de este contrato vive en infraestructura. **Los ejemplos son ilustrativos; su construcción y registro deben integrarse con el contenedor real de CI4.**

### Reglas de tipos y nombres

- Activar `declare(strict_types=1);` en nuevas clases.
- Usar `final` donde no exista una razón real para heredar.
- `readonly` para DTOs/value objects inmutables cuando la versión PHP lo permita.
- No pasar arrays sin forma por diez capas; usar DTOs para fronteras estables.
- No crear DTOs duplicados si dos capas comparten una estructura mínima claramente definida.
- Definir magnitud, profundidad, distancia y tiempo con **unidades explícitas** (por ejemplo `depthKm`, `distanceKm`, `occurredAtUtc`).
- Definir en un único lugar el significado de una coordenada y validar valores no finitos.
- Usar `DateTimeImmutable` y UTC para persistencia/intercambio; convertir a hora local solo en presentación.
- Mantener etiquetas de origen (`SGC`, `USGS`) y estado de verificación separadas de los datos calculados.

## 9. Patrón CQS ligero para operaciones

**Lecturas (Queries)**: no modifican el estado, retornan estructuras de lectura.  
**Escrituras (Commands)**: realizan cambios controlados y retornan resultados definidos.

```text
Application/
  Queries/
    ListEvents/
      ListEventsQuery.php
      ListEventsHandler.php
    GetEvent/
      GetEventQuery.php
      GetEventHandler.php
  Commands/
    SyncEvents/
      SyncEventsCommand.php
      SyncEventsHandler.php
    SubmitCitizenReport/
      SubmitCitizenReportCommand.php
      SubmitCitizenReportHandler.php
```

No instalar un bus CQRS complejo ni Event Sourcing solo para tener nombres bonitos. **CQS ligero** = clases pequeñas, responsabilidades visibles y posibilidad de probarlas.

## 10. API REST, versionado y contrato público

**Situación observada:** `Sismos.php` mezcla endpoints web/API, y `ApiRed.php` resuelve parte de la seguridad internamente.

### Propuesta

- Agrupar controladores: `Web`, `Api/V1`, `System`.
- Versionar **nuevos endpoints** bajo `/api/v1/...`.
- Mantener durante la transición rutas existentes `/api/sismos/*` y `/api/red/*` como adaptadores que delegan a los mismos handlers.
- Separar rutas públicas, rutas protegidas, endpoints internos y comandos CLI.
- Documentar peticiones, errores y estados con OpenAPI.
- Serializar la salida en `Resources`, sin exponer directamente `sgc_metadata` o estructuras internas.
- Definir paginación, filtros, ordenamiento, límites y validación consistente de parámetros.
- Usar nombres/estados uniformes: `400`, `401`, `403`, `404`, `409`, `422`, `429`, `500`, `503` según el caso.
- Respuestas de error con `code`, `message` seguro, `details` no sensibles y `request_id`.
- Un `health` público **mínimo**; diagnóstico interno ampliado solo para operadores. Evitar divulgar `environment` y detalles del servidor en respuestas públicas.
- No depender de una clave estática incrustada en un cliente móvil para identificar usuarios o dispositivos: una app pública se puede inspeccionar. Usar identidad/credenciales por dispositivo y rotación; diseñar las garantías según el riesgo.

### Ejemplo de respuesta

```json
{
  "status": "error",
  "error": {
    "code": "INVALID_COORDINATES",
    "message": "Coordenadas fuera de rango."
  },
  "request_id": "<identificador-generado-en-el-servidor>"
}
```

**Compatibilidad:** el contrato mostrado es el **objetivo**, no un cambio inmediato. No transformar silenciosamente respuestas existentes de aplicaciones consumidoras.

## 11. Seguridad por capas

### HTTP y autenticación

- [ ] Centralizar en filtros CI4 la autenticación de API y sus errores; no duplicar la misma comprobación en cada acción.
- [ ] Diferenciar **usuario web**, **cliente API**, **dispositivo registrado**, **tarea CLI** y **administrador**.
- [ ] Aplicar políticas por recurso y acción (`events.read`, `reports.submit`, `devices.detect`, `alerts.manage`).
- [ ] Hacer *deny-by-default* en endpoints sensibles; las rutas verdaderamente públicas se declaran expresamente.
- [ ] Conservar CSRF para formularios web con sesión y cookies; definir protección adecuada para APIs autenticadas por otros mecanismos.
- [ ] Validar método HTTP e impedir escrituras por GET.
- [ ] Limitar tamaños de payload, rango de fechas, paginación, frecuencia y cantidad de dispositivos/detecciones.
- [ ] Evitar secretos, tokens, coordenadas sensibles e IPs crudas en logs.
- [ ] Establecer rotación y revocación de credenciales de dispositivo.
- [ ] Proteger sincronización manual de fuentes contra uso público abusivo.

### Regla de autorización importante

```text
Filtro / autenticar  ->  Política del caso de uso  ->  Validar entrada
     ->  Ejecutar  ->  Responder
```

Un `deny.php` solo **muestra** la denegación después de tomar la decisión. Cambiar un archivo de vista no debería permitir saltarse permisos.

### Escrituras ciudadanas

- No presentar los reportes ciudadanos como datos oficiales.
- Mantener consentimiento, minimización de datos, límites por sesión/red y retención definida.
- Auditar qué identificadores se almacenan y cuánto tiempo.
- Mantener la validación de `CitizenReportService`; extraerla progresivamente y acompañarla de pruebas de regresión.

## 12. Dominio sísmico: distinguir hechos, inferencias y alertas

Tres tipos de información **no son intercambiables**:

1. **Evento oficial reportado por fuente:** proviene de SGC/USGS, con identificador y procedencia.
2. **Detección ciudadana/sensorial:** lectura cruda de un dispositivo; **no prueba por sí sola** la existencia de un terremoto.
3. **Candidato de alerta:** inferencia experimental con calidad, incertidumbre, estado y trazabilidad.

### Modelo mínimo propuesto

```text
ProviderEvent      -> dato crudo normalizado + referencia original
SeismicEvent       -> evento consolidado del catálogo
CitizenReport      -> observación humana independiente
SensorDetection    -> señal técnica capturada por dispositivo
AlertCandidate     -> hipótesis evaluable, no alerta confirmada
AlertDecision      -> política + evidencia + versión del algoritmo
AlertDelivery      -> intento, resultado y hora de entrega
```

**No almacenar `magnitud` y `intensidad percibida` como si fueran el mismo concepto.** La vista actual del reporte ciudadano usa un campo llamado `magnitud` y el servicio lo interpreta como intensidad. Separar ambos nombres en los contratos nuevos y mantener un mapper de compatibilidad para formularios existentes.

**Estados de alerta recomendados:** `experimental`, `candidate`, `under_review`, `confirmed_by_policy`, `rejected`, `expired`, `cancelled`. Su semántica debe documentarse; “confirmed_by_policy” **no equivale** a confirmación de una agencia científica.

**Condición no negociable:** una validación de sensores simulados no basta para usar el sistema como alerta temprana operativa. Requiere pruebas científicas, de campo, de latencia, de falsos positivos, tolerancia a fallos y revisión especializada.

## 13. Ingestión SGC / USGS desacoplada

El `QuakeSyncService` actual hace demasiadas tareas. Dividir por intención:

```text
SyncEventsHandler
   |
   +--> SourceRegistry
   |      +--> SgcSourceAdapter   -> fetch + normalización
   |      +--> UsgsSourceAdapter  -> fetch + normalización
   |
   +--> EventDeduplicator          -> decisión de correlación
   +--> SeismicEventWriter         -> persistencia idempotente
   +--> IngestionAuditRepository   -> resultado por fuente/lote
   +--> (evento) CatalogUpdated    -> reconciliación posterior
```

### Condiciones de aceptación

- [ ] Si SGC falla, USGS puede terminar su lote de manera independiente y viceversa.
- [ ] Cada fuente produce una lista normalizada o un error tipado (sin capturar `Throwable` y silenciarlo indiscriminadamente).
- [ ] Las llamadas HTTP tienen timeout, retry acotado, backoff y respeto de límites del proveedor.
- [ ] La sincronización es **idempotente**: repetir un lote no duplica registros.
- [ ] Se registra `source`, `source_event_id`, `fetched_at_utc`, `source_updated_at_utc`, hash/versionado si corresponde.
- [ ] La deduplicación conserva referencia de ambos proveedores cuando se fusionan datos.
- [ ] Duplicados tienen una decisión explicable (ventana temporal, distancia geográfica, fuente preferida, incertidumbre).
- [ ] Reglas de deduplicación están testeadas con casos cercanos que **no** son el mismo evento.
- [ ] La persistencia por lotes usa transacciones donde corresponde y restricciones únicas de BD.
- [ ] Los lotes son visibles en métricas: fuente, inicio, fin, número recibido, número fusionado, número descartado y fallos.
- [ ] La reconciliación ciudadana puede fallar sin invalidar el lote sísmico ya persistido.
- [ ] Los comandos programados aplican lock/lease para evitar ejecuciones simultáneas conflictivas.

**No hacer:** ejecutar sincronización completa dentro del renderizado del mapa. Programar CLI/worker y entregar lecturas desde el repositorio/caché.

## 14. Red de sensores y separación entre ingestión y decisión

```text
HTTP POST /api/red/deteccion
    -> autenticar dispositivo + límites + parsing estricto
    -> RegisterDetectionHandler
    -> validar sellos temporales y señales plausibles
    -> guardar detección con estado provisional
    -> emitir DetectionReceived (evento interno)
    -> EvaluarCandidateHandler (separado, reproducible)
    -> si cumple política experimental -> publicar resultado
    -> AlertDeliveryHandler (separado)
```

### Requisitos específicos

- [ ] Contratos versionados de detección y registro.
- [ ] Distinción entre tiempo declarado por móvil y tiempo recibido por servidor.
- [ ] Medición y límites de desfase del reloj y latencia de red.
- [ ] Detección de duplicados/reintentos mediante ID idempotente por evento del dispositivo.
- [ ] Validación de frecuencia, plausibilidad y reputación/estado del sensor.
- [ ] Robustez ante dispositivos comprometidos, datos falsos y ataques de Sybil.
- [ ] Almacenamiento separado de detecciones, candidatos y entregas.
- [ ] Simuladores etiquetados que **no** emiten alertas a usuarios reales.
- [ ] Kill switch y posibilidad de cancelar alertas.
- [ ] Todas las decisiones registran algoritmo, versión, entradas relevantes y motivo.

### Repositorio Android independiente

La **versión móvil está en construcción y se publicará en un repositorio separado**. No crear `mobile/` ni copiar código Kotlin dentro de SIGRA por esta refactorización. La coordinación entre repositorios se hace mediante **OpenAPI**, versionado semántico de contratos, ejemplos JSON y pruebas de contrato entre cliente y servidor. Mientras no exista un repositorio público, describir Android como **en desarrollo** y no enlazar rutas inexistentes.

## 15. Persistencia y transacciones

### Roles distintos

- **Entidad de dominio:** representa un concepto y sus invariantes; no hereda `CodeIgniter\Model`.
- **Modelo CI4:** acceso a una tabla, queries, filtros y mapping; es un detalle de infraestructura.
- **Repositorio:** contrato para operaciones de negocio que requieren persistencia; no hay que crear interfaces para cada consulta trivial.
- **Unidad transaccional:** definida por el caso de uso, implementada por infraestructura.

### Reglas

- [ ] Migraciones para cualquier cambio de esquema; jamás modificar esquema productivo manualmente como fuente de verdad.
- [ ] Claves únicas para `source + source_event_id`, ids de detecciones y deduplicación que lo requieran.
- [ ] Índices para fecha, coordenadas según estrategia, magnitud, fuente y estados consultados frecuentemente.
- [ ] Paginación acotada: evitar devolver histórico completo sin límites.
- [ ] `EXPLAIN` en consultas relevantes y pruebas de carga con datos representativos.
- [ ] No devolver arreglos SQL crudos directamente a JSON público.
- [ ] No usar transacciones de base de datos que incluyan llamadas de red externas.
- [ ] Estrategia de retención de telemetría, reportes ciudadanos y logs.
- [ ] Estrategia documentada de respaldo, restauración y verificación.
- [ ] Evitar queries en bucle (N+1) y separar modelos de lectura si el mapa lo requiere.
- [ ] Preparar una estrategia de migración *expand/contract* para cambios de BD sin romper versiones desplegadas.

## 16. Presentación, mapas y JavaScript

El `list/index.php` actual necesita reducirse a una **plantilla**. Sus datos iniciales ya deberían venir preparados en `ListEventsViewModel` y los scripts complejos deben estar en archivos `public/assets/js/modules/`.

### Descomposición de una pantalla

```text
Presentation/
  ViewModels/ListEventsViewModel.php
  Views/sismos/list/
    index.php                    # composición mínima
    _filters.php                 # controles de filtrado
    _event_item.php              # elemento de listado
    _map_shell.php               # contenedor del mapa
    _empty_state.php             # sin resultados
public/assets/js/modules/seismic-map/
  index.js                       # bootstrapping de mapa
  api.js                         # consultar eventos
  state.js                       # estado / filtros
  geolocation.js                 # consentimiento y geolocalización
  markers.js                     # render de marcadores
  replay.js                      # reproducción histórica
```

### Reglas visuales

- [ ] `index.php` recibe `$vm` preparado, sin `new SismoModel()`.
- [ ] Mantener render del lado del servidor cuando aporte accesibilidad y primera carga rápida.
- [ ] `json_encode()` destinado a JS debe usar flags seguros para contexto HTML (o transmitir configuración por un mecanismo seguro equivalente).
- [ ] Preferir `base_url()`/assets correctamente configurados a rutas rígidas como `/public/assets/...`.
- [ ] Mantener CSS y JavaScript fuera de plantillas extensas.
- [ ] Capturar estados: `loading`, `success`, `empty`, `stale`, `partial outage`, `error`.
- [ ] No actualizar el mapa cada pocos segundos si el servidor ha declarado datos cacheados; usar estrategias consistentes de polling/backoff.
- [ ] Conservar privacidad de geolocalización: opt-in, permisos, almacenamiento mínimo y posibilidad de revocar.
- [ ] Probar la interfaz móvil web y accesibilidad independientemente del futuro cliente Android.

## 17. Configuración y composición de dependencias

- Crear un **composition root** (factorías, `Config\Services` o contenedor compatible) que construya los handlers y sus adaptadores.
- No usar `new SismoModel()`, `new SgcClient()` ni `db_connect()` dentro del dominio.
- Evitar que los casos de uso consulten `env()` directamente.
- Centralizar timeouts, ventanas, umbrales, claves y feature flags en clases de configuración validables.
- Validar la configuración obligatoria al arrancar comandos y procesos críticos; no aceptar secretos vacíos en producción.
- Inyectar un `Clock` o una función de reloj controlada en lógica que dependa del tiempo.
- Inyectar un `SourceRegistry` o lista de adaptadores registrados para que agregar fuentes no modifique el handler central.
- Mantener opciones por entorno en `.env` no versionado y ejemplos sin secretos.
- No mezclar “feature flag”, “permisos” y “modo experimental”: son mecanismos diferentes.

## 18. Manejo de errores, logging y observabilidad

Definir errores de dominio/aplicación bien tipados, por ejemplo:

```text
ValidationFailed         -> 422
AuthenticationRequired   -> 401
PermissionDenied         -> 403
ResourceNotFound         -> 404
ConflictDetected         -> 409
RateLimitExceeded        -> 429
ProviderUnavailable      -> 503 (si el endpoint depende de él)
UnexpectedFailure        -> 500 (detalle solo en logs)
```

- [ ] Registrar `request_id`/`correlation_id` en HTTP y procesos CLI.
- [ ] Log estructurado con módulo, operación, duración, resultado y error redactado.
- [ ] Métricas de latencia, errores por endpoint, staleness de fuentes y backlog de reconciliación.
- [ ] Separar “SGC/USGS caído” de “nuestro servidor caído”.
- [ ] Nunca revelar trazas, SQL, tokens o secretos en mensajes al cliente.
- [ ] Diseñar niveles de log por ambiente y retención acotada.
- [ ] Registrar quién/cuándo/por qué se confirmó, rechazó o canceló un candidato de alerta.
- [ ] Health de disponibilidad básico separado de readiness y diagnóstico interno.

## 19. Estrategia de pruebas por capa

| Tipo | Objetivo | Ejemplo SIGRA |
|---|---|---|
| Unitarias de dominio | Invariantes y algoritmos puros. | Magnitud/rango, coordenadas, correlación, TDOA con ruido. |
| Unitarias de aplicación | Flujo con puertos simulados. | Si falla SGC, procesar USGS; no disparar alerta desde una sola señal. |
| Integración | Bases de datos/adaptadores reales controlados. | Repositorios MySQL, migraciones, transacciones, unicidad. |
| HTTP/Feature | Rutas y seguridad. | 401 token ausente, 403 no autorizado, 422 payload inválido, 429 rate limit. |
| Contrato | Formato de intercambio y compatibilidad. | Respuesta `/api/sismos/recent` antigua y `/api/v1/...`. |
| E2E | Flujo real desde web o emuladores controlados. | Buscar sismo, filtrar, enviar reporte y ver confirmación. |
| Propiedades / escenarios | Reglas físicas con datos extremos. | Dos sismos cercanos distintos, dateline, relojes desincronizados. |
| Carga / resiliencia | Rendimiento y degradación. | 10k dispositivos simulados y caída de un proveedor (cifra de escenario, no capacidad afirmada). |

### Regresiones especialmente importantes

- [ ] Reporte ciudadano no crea automáticamente “sismo oficial”.
- [ ] Misma señal reintentada no duplica detecciones ni alertas.
- [ ] Sincronizar el mismo lote dos veces mantiene cantidad de eventos.
- [ ] Caída de SGC no borra el histórico ni rompe consultas.
- [ ] Orden por distancia exige coordenadas válidas.
- [ ] Falta de token no se tolera en producción.
- [ ] Diferentes husos horarios no alteran el orden temporal.
- [ ] UTC y conversiones de fecha funcionan entre países.
- [ ] Ninguna operación de escritura se activa por GET accidental.
- [ ] Datos simulados jamás alcanzan canales reales de alerta.

### Quality gates sugeridos

- CI en pull requests: Composer install + PHP syntax + PHPUnit + PHPStan/Psalm + estilo + análisis de dependencias.
- Establecer primero una línea base realista; elevar el rigor gradualmente.
- Tests de dominio deben ejecutarse sin servidor web ni credenciales de producción.
- Política de cobertura **en código nuevo/crítico**, sin confundir porcentaje alto con confiabilidad científica.

## 20. Reglas para dependencias entre módulos

**Permitido:** `SeismicIngestion -> SeismicCatalog/Application/Contracts` para registrar eventos mediante un contrato.  
**Permitido:** `Alerting -> SensorNetwork/Application/Contracts` para consultar evidencia de sensores.  
**Permitido:** `Presentation -> Application` mediante su controlador/preparador.  
**Prohibido:** `Domain -> Http`, `Domain -> CI4 Model`, `View -> SQL`.  
**Prohibido:** `CitizenReports -> SensorNetwork/Infrastructure/Persistence` o escribir tablas ajenas.

Para comunicación entre módulos:

1. **Llamada síncrona a interfaz pública** si se necesita una respuesta inmediata.
2. **Evento interno** si es una consecuencia desacoplada, por ejemplo después de persistir un lote.
3. **Outbox transaccional** solo si una publicación debe sobrevivir a caídas y hay garantías de entrega a implementar.

No usar colas ni microservicios por defecto. La primera versión puede usar procesos CLI y comandos programados, con controles de concurrencia.

## 21. Tabla de migración SIGRA: archivo actual -> destino

| Actual | Destino propuesto | Trabajo principal |
|---|---|---|
| `app/Controllers/Sismos.php` | `SeismicCatalog/Http/Controllers/{Web,Api/V1}/` + `System/Http/` | Separar páginas, API, health, serializadores y políticas. |
| `app/Controllers/ApiRed.php` | `SensorNetwork/Http/Controllers/Api/V1/` y `Application/Commands/` | Parsing/autenticación de transporte vs casos de uso. |
| `app/Views/sismos/list/index.php` | `SeismicCatalog/Presentation/Views/sismos/list/` | Extraer datos a query+VM y JS a assets. |
| `app/Views/sismos/create/index.php` | `CitizenReports/Presentation/Views/reports/create/` | Mantener ruta antigua como adaptador; controles en filtros y caso de uso. |
| `app/Views/sismos/create/validator.php` | `CitizenReports/Http/Requests/SubmitCitizenReportRequest.php` | Parsear entrada y normalizar; invariantes al dominio. |
| `app/Views/sismos/create/procesor.php` | `CitizenReports/Application/Commands/SubmitReport/` | Persistencia, política y redirección desde HTTP. |
| `app/Libraries/CitizenReportService.php` | `CitizenReports/{Application,Domain}` | Descomponer validación, submit y reconcile sin perder protecciones. |
| `app/Libraries/QuakeSyncService.php` | `SeismicIngestion/Application/Commands/SyncEvents/` | Desacoplar fetching, normalización, dedupe, persistencia y reconciliación. |
| `app/Libraries/SgcClient.php` | `SeismicIngestion/Infrastructure/Sources/SgcSourceAdapter.php` | Adaptador a contrato `SeismicSource`. |
| `app/Libraries/UsgsClient.php` | `SeismicIngestion/Infrastructure/Sources/UsgsSourceAdapter.php` | Adaptador a contrato `SeismicSource`. |
| `app/Models/SismoModel.php` | `SeismicCatalog/Infrastructure/Persistence/` | Mantener métodos útiles; separar mapper y contrato si hay beneficio real. |
| `app/Config/Routes.php` | Composición global + grupos por módulo | Consolidar filtros/versiones y rutas web/API. |
| `app/Libraries/ApiResponses.php` | `Shared/Http/ErrorResponder.php` | Centralizar mapping de errores y formato, sin duplicar lógica. |

**Compatibilidad:** no renombrar ni borrar todo de golpe. En cada etapa, dejar un adaptador del nombre antiguo al nuevo y eliminarlo solo cuando los tests de contrato sean verdes.

## 22. Orden recomendado de refactorización sin romper producción

### Fase 0 — Línea base y protección (P0)

- [ ] Crear rama de migración y marcar commit/base estable.
- [ ] Guardar ejemplos válidos de respuestas JSON antiguas (golden fixtures).
- [ ] Añadir pruebas de rutas actuales, auth, reportes y sincronización.
- [ ] Inventariar endpoints, tablas y jobs realmente usados.
- [ ] Corregir cualquier publicación inadvertida de datos sensibles en `health`.
- [ ] Definir `docs/architecture/adr/0001-modular-monolith.md`.

**Salida:** poder detectar una regresión funcional antes de mover archivos.

### Fase 1 — Separar las vistas (P0)

- [ ] Extraer consulta de `list/index.php` a `ListEventsHandler`.
- [ ] Crear `ListEventsViewModel`.
- [ ] Mover JavaScript masivo a archivos públicos por responsabilidad.
- [ ] Convertir la vista a puro render.
- [ ] Sustituir scripts `require` de creación por controlador + request + handler.
- [ ] Conservar UX y rutas exactamente iguales.

**Salida:** una pantalla lista/desacoplada y un reporte ciudadano funcionando con pruebas.

### Fase 2 — API y fronteras de seguridad (P0/P1)

- [ ] Separar `Sismos.php` en controlador web, API y system.
- [ ] Centralizar autenticación, autorización, normalización y respuestas.
- [ ] Introducir `SeismicEventResource` y DTOs.
- [ ] Mantener adaptadores antiguos y añadir `/api/v1` opcional.
- [ ] Añadir pruebas de contrato y códigos HTTP.

**Salida:** API documentable y controladores sin reglas de negocio.

### Fase 3 — Ingestión, persistencia y ciudadanía (P1)

- [ ] Inyectar dependencias en sincronización en lugar de instanciarlas internamente.
- [ ] Extraer `EventDeduplicator` como servicio puro.
- [ ] Separar `SyncEventsHandler`, persistencia y reconciliación.
- [ ] Crear claves idempotentes y auditoría de lotes.
- [ ] Extraer responsabilidad de `CitizenReportService` a handlers/políticas/repositorios.

**Salida:** sincronización resiliente y casos de uso probables de forma aislada.

### Fase 4 — Sensores, decisiones y observabilidad (P1/P2)

- [ ] Dividir registro, heartbeats, detecciones y alertas.
- [ ] Crear modelo de estados de candidato con incertidumbre explícita.
- [ ] Implementar trazabilidad y cancelación de alertas.
- [ ] Añadir métricas, IDs de correlación, pruebas con fallos y simulación.
- [ ] Formalizar contrato externo para repositorio Android separado.

**Salida:** red sensorial experimental mejor encapsulada y auditable.

### Fase 5 — Endurecimiento progresivo (P2)

- [ ] Hacer cumplir reglas de dependencia mediante análisis estático/tests de arquitectura.
- [ ] Establecer convenciones de nombres, namespaces, DTOs y puertos.
- [ ] Documentar APIs, límites de módulos y ADRs.
- [ ] Reducir adaptadores legacy solo cuando no existan consumidores dependientes.
- [ ] Revisar métricas antes de introducir cachés, colas u otras infraestructuras.

**Salida:** repositorio mantenible y más sencillo de ampliar por terceros.

## 23. Definition of Done para cualquier módulo nuevo

Una funcionalidad no se considera terminada solo porque aparece en pantalla:

- [ ] Descrita con su caso de uso, dueño del módulo y reglas relevantes.
- [ ] Rutas y permisos especificados.
- [ ] Validación de entrada definida con mensajes seguros.
- [ ] Comportamiento de aplicación probado.
- [ ] Persistencia y transacción revisadas, si aplica.
- [ ] Errores y códigos HTTP uniformes.
- [ ] Datos de salida serializados o preparados para vista.
- [ ] Sin acceso SQL desde `Views` o `Controllers`.
- [ ] Sin secretos, tokens o datos privados en logs.
- [ ] Unit tests y feature tests pertinentes ejecutados.
- [ ] Documentación de API/arquitectura actualizada.
- [ ] Regresión y compatibilidad comprobadas.
- [ ] Si interviene una alerta: validación, incertidumbre, trazabilidad y revisión especial obligatorias.

## 24. Normas de revisión de Pull Requests

Se devuelve para cambios si:

1. El controlador realiza queries, persistencia o algoritmos sísmicos.
2. Una vista valida autorización o ejecuta escritura.
3. Un servicio depende directamente de `$_POST`, `$_GET`, `$_SERVER` o del estado global de CI4 sin ser un adaptador HTTP.
4. Un módulo escribe las tablas de otro sin contrato.
5. La API cambia su JSON o semántica sin pruebas de contrato.
6. Un cambio crítico introduce reglas sin tests.
7. Se incluye código nuevo que solo funciona con credenciales reales de un desarrollador.
8. Se pierde información de procedencia o auditoría en la deduplicación.
9. Las detecciones experimentales pueden hacerse pasar por alertas oficiales.
10. Se obliga a crear archivos `form/validator/procesor` vacíos solo por convención.

## 25. Decisiones que deben registrarse como ADR

- [ ] `ADR-001`: monolito modular y límites por capacidad.
- [ ] `ADR-002`: casos de uso vs lógica ejecutable dentro de `Views`.
- [ ] `ADR-003`: contratos y ciclo de vida API.
- [ ] `ADR-004`: identificación, normalización y deduplicación SGC/USGS.
- [ ] `ADR-005`: privacidad, retención y protección contra abuso de reportes ciudadanos.
- [ ] `ADR-006`: distinción entre detección, evento y alerta.
- [ ] `ADR-007`: inyección de dependencias y composición en CI4.
- [ ] `ADR-008`: propiedad de tablas y transacciones entre módulos.
- [ ] `ADR-009`: estrategia de compatibilidad y deprecación.
- [ ] `ADR-010`: contrato API del cliente móvil en repositorio separado.

Plantilla mínima para cada ADR:

```markdown
# ADR-XXX — Título

- Estado: propuesto | aceptado | sustituido
- Fecha: AAAA-MM-DD
- Contexto: problema y restricciones reales
- Decisión: regla concreta adoptada
- Alternativas: opciones descartadas y por qué
- Consecuencias: ventajas, costes y riesgos
- Validación: tests/indicadores que muestran que funciona
```

## 26. Qué NO hacer durante esta migración

- No convertir SIGRA en microservicios sin necesidad operativa demostrada.
- No crear una interfaz y repositorio por cada tabla solo para inflar capas.
- No introducir CQRS distribuido, Event Sourcing, buses complejos o Kubernetes como requisito arquitectónico.
- No usar `deny.php` como sustituto de filtros, permisos y políticas.
- No migrar rutas y SQL simultáneamente sin pruebas de regresión.
- No inventar un módulo móvil dentro del repositorio backend.
- No presentar cálculos científicos no verificados como alertas fiables.
- No borrar código heredado antes de verificar consumidores y contratos.
- No mover archivos por estética dejando las dependencias iguales.
- No confundir “más carpetas” con “mejor arquitectura”.

## 27. Primeros 12 tickets listos para crear en GitHub Issues

| Ticket | Tarea | Prioridad | Aceptación |
|---|---|---|---|
| ARC-001 | Documentar módulos y ADR-001. | P0 | Diagrama, owner y reglas publicados. |
| ARC-002 | Tests de contrato de API actual. | P0 | Fixtures/versiones y respuesta regresiva validada. |
| ARC-003 | `ListEventsQuery/Handler`. | P0 | Vista sin instancias de modelo. |
| ARC-004 | `ListEventsViewModel`. | P0 | HTML consume datos preparados. |
| ARC-005 | Extraer JS de `sismos/list/index.php`. | P0 | Archivo PHP reducido; funciones JS separadas. |
| ARC-006 | Refactor de `create` a request + handler. | P0 | No `require` de procesador; CSRF y rate limit probados. |
| ARC-007 | Separar web/API/health de `Sismos`. | P0 | Sin negocio en controller; mismas rutas. |
| ARC-008 | Autenticación/políticas compartidas. | P0 | Tests 401/403/429 y rutas públicas documentadas. |
| ARC-009 | Resource y errores de API uniformes. | P1 | No exposición de entidades/metadata internas. |
| ARC-010 | Extraer deduplicador puro. | P1 | Test de duplicados y falsos duplicados. |
| ARC-011 | Descomponer `QuakeSyncService`. | P1 | Tests con SGC/USGS simulados y fallos parciales. |
| ARC-012 | Separar decisiones de alerta de detecciones. | P1 | Estados y trazabilidad documentados y probados. |

## 28. Resultado esperado

Con esta evolución, SIGRA conservará lo valioso de tu arquitectura original —**controladores ligeros, operaciones localizables y módulos organizados**— pero ganará algo esencial para un proyecto open source serio: **contratos verificables, independencia de framework en el dominio, pruebas por responsabilidad, seguridad en fronteras reales y capacidad de evolucionar sin romper consumidores**.

**Prioridad inmediata:** `ARC-002` + `ARC-003` + `ARC-004` + `ARC-006` + `ARC-007`. Son las modificaciones que más reducen el acoplamiento visible sin exigir rehacer toda la aplicación.

---

### Fuentes de referencia

- Arquitectura personal original: https://github.com/JKGhost01/MVC-CI4-views_logic
- Repositorio SIGRA: https://github.com/JKGhost01/SIGRA
- CI4 — documentación oficial (verificar API específica instalada): https://codeigniter.com/user_guide/
- PSR-4 — autoloading: https://www.php-fig.org/psr/psr-4/

> **Última advertencia de alcance:** este documento es un plano de mejora basado en la revisión estática de archivos públicos. No certifica seguridad, disponibilidad, capacidad de red ni validez científica del sistema de alerta.
