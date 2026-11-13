# SIGRA — Plan integral de mejoras del repositorio open source

**Repositorio:** https://github.com/JKGhost01/SIGRA  
**Fecha de revisión:** 12 de noviembre de 2026  
**Estado:** hoja de ruta de mejora continua; no implica que todas las tareas estén pendientes o que existan vulnerabilidades confirmadas.  
**Ámbito:** repositorio **SIGRA web/backend**, documentación, API, infraestructura y red ciudadana del servidor.

> **Decisiones del mantenedor (obligatorias):**
>
> 1. **La aplicación Android está en desarrollo y tendrá un repositorio independiente.** No añadir `/mobile` al repositorio de SIGRA web/backend, no pedir su publicación aquí y no considerar su ausencia un defecto.
> 2. **No crear ni exigir `SECURITY.md`.** Las referencias que quedaron de una plantilla deben eliminarse del README, CONTRIBUTING y cualquier otro documento. Las medidas reales de seguridad del software sí deben seguir fortaleciéndose.
> 3. **No afirmar capacidades de alerta sísmica validadas sin evidencia.** Diferenciar explícitamente visualización de eventos publicados, monitoreo experimental y detección/alerta temprana experimental.

---

**Avance implementado y evidencia:** [estado local de recomendaciones](docs/RECOMMENDATIONS_STATUS.md).
Las casillas abiertas incluyen auditorías y validaciones externas aún pendientes.

## Cómo usar este documento

- `P0` — Corregir antes de promover el repositorio como instalación reproducible o usarlo en producción.
- `P1` — Alta prioridad: calidad, seguridad, pruebas y preparación para contribuciones.
- `P2` — Mejora posterior, cuando el núcleo esté estable.
- Marca `[x]` al completar una tarea y adjunta en su Issue/PR la evidencia de aceptación.
- Las tareas redactadas como **«verificar»** son puntos de auditoría; no se presentan como fallos demostrados.
- Evitar convertir el roadmap en un proyecto de reescritura total: mejorar incrementalmente y mantener el comportamiento existente.

## Diagnóstico inicial confirmado en la revisión pública

| Hallazgo | Evidencia observada | Acción |
|---|---|---|
| URL de clonación de plantilla | `README.md` y `CONTRIBUTING.md` usan `https://github.com/TU-USUARIO/sigra.git` | Sustituir por la URL real en toda la documentación. |
| Dependencias descritas de manera inconsistente | README afirma que se incluyen `system/` y `vendor/`; `.gitignore` ignora `/vendor/` | Alinear instalación documentada con archivos realmente distribuidos. |
| Android presentado como directorio local | README incluye árbol `mobile/`, `cd mobile` y ruta APK | Reemplazar por la nota de desarrollo en repositorio independiente. |
| Referencia heredada a `SECURITY.md` | Aparece en README y CONTRIBUTING; no es parte de la política deseada | Eliminar todos los enlaces y menciones que presupongan ese archivo. |
| Identidad Composer heredada | `composer.json` declara `codeigniter4/codeigniter4` y descripción genérica del framework | Revisar la identidad del **proyecto raíz**, conservando dependencias y licencias del framework. |
| Metadatos de GitHub vacíos | La sección About no tiene descripción, sitio ni topics | Completar presentación del proyecto. |
| Documentación extensa, pero con detalles inconsistentes | README incluye una lista de documentación con formato visible defectuoso y pasos no reproducibles | Hacer auditoría de enlaces, traducciones y comandos. |
| Prueba de fusión de ondas P disponible | Existe `tests/PwaveFusionTest.php` | Ampliar cobertura; una simulación determinista no valida funcionamiento en campo. |

**Fuentes verificadas:** [Repositorio](https://github.com/JKGhost01/SIGRA), [README.md](https://github.com/JKGhost01/SIGRA/blob/main/README.md), [CONTRIBUTING.md](https://github.com/JKGhost01/SIGRA/blob/main/CONTRIBUTING.md), [.gitignore](https://github.com/JKGhost01/SIGRA/blob/main/.gitignore), [composer.json](https://github.com/JKGhost01/SIGRA/blob/main/composer.json), [PwaveFusionTest.php](https://github.com/JKGhost01/SIGRA/blob/main/tests/PwaveFusionTest.php).

---

## Fase 0 — Correcciones de exactitud y presentación (`P0`)

### 0.1 README y documentación principal

- [x] **P0** Sustituir en todos los archivos `TU-USUARIO/sigra` por `JKGhost01/SIGRA`.
- [x] **P0** Comprobar manualmente cada comando de instalación empezando desde un clon nuevo, sin dependencias locales previas ni archivos secretos del equipo original.
- [x] **P0** Corregir la afirmación de que `vendor/` viene en el repositorio; documentar cómo obtenerlo según el modo de instalación elegido.
- [x] **P0** Explicar claramente la presencia de `system/` y su relación con `composer.json`/`composer.lock`; evitar duplicación del framework o rutas de autoload incompatibles.
- [x] **P0** Retirar del árbol de arquitectura la entrada `mobile/`; indicar que Android es un producto complementario independiente y **todavía en construcción**.
- [x] **P0** Eliminar instrucciones `cd mobile`, `./gradlew` y ruta de APK del README de este repositorio. Esas instrucciones corresponden al repositorio Android cuando se publique.
- [x] **P0** Eliminar todo enlace o indicación de consultar `SECURITY.md` en README, README.es.md, CONTRIBUTING y otros documentos donde se haya heredado.
- [x] **P0** Actualizar las descripciones de funciones de red ciudadana para dejar explícito qué está implementado y qué es **experimental o está en desarrollo**.
- [ ] **P0** Comprobar que `/api/health`, `php spark sigra:sync`, migraciones y rutas listadas existen y responden como se documenta.
- [x] **P0** Revisar el marcado Markdown de la sección Documentation/Documentación (hay un fragmento con formato defectuoso) y corregir enlaces rotos.
- [x] **P1** Explicar en las primeras líneas: problema resuelto, público objetivo, estado de madurez y límites del sistema.
- [x] **P1** Separar en el README: **Funciones disponibles**, **Funciones experimentales**, **En desarrollo** y **Planes futuros**.
- [ ] **P1** Añadir capturas reales de mapa, filtros, detalle y dashboard; optimizar peso de imágenes y usar texto alternativo descriptivo.
- [ ] **P1** Incorporar un diagrama de arquitectura que muestre clientes SGC/USGS, normalizador, base de datos, API, frontend y servicio experimental de red ciudadana.
- [x] **P1** Mantener README.md y README.es.md sincronizados en funcionalidades, instrucciones, versión, limitaciones y enlaces.
- [x] **P1** Decidir si `README_GUIA.md` complementa o duplica los otros README; evitar tres fuentes contradictorias.
- [ ] **P2** Añadir un GIF o video corto de demostración, siempre con datos ficticios cuando muestre pruebas de alertas.
- [ ] **P2** Añadir un FAQ pequeño: fuentes, latencia, deduplicación, privacidad, despliegue y precisión.

### 0.2 Estado de Android (texto sugerido para el README)

**Español:**

> ### Aplicación Android — En desarrollo
> La aplicación móvil de SIGRA se encuentra actualmente en construcción. Su código se mantendrá en un **repositorio independiente** del proyecto web/backend. Este repositorio contiene el servidor, la interfaz web y las API de integración. Se publicará el enlace al repositorio móvil cuando esté disponible. Las capacidades de detección y alerta temprana basadas en teléfonos son **experimentales** y no sustituyen sistemas oficiales.

**English:**

> ### Android application — In development
> The SIGRA Android application is currently under development and will be maintained in a **separate repository** from the web/backend project. This repository contains the server, web interface, and integration APIs. A link to the mobile repository will be published when available. Phone-based detection and early-warning capabilities are **experimental** and do not replace official warning systems.

**Criterio de aceptación:** ningún lector del repositorio web recibe instrucciones para compilar un directorio `mobile/` inexistente; tampoco se publica un enlace a un repositorio que todavía no existe.

### 0.3 Perfil y presentación de GitHub

- [ ] **P1** Agregar descripción en About. Sugerencia: `Open-source seismic monitor combining SGC and USGS data, interactive maps, historical records and a PHP/CodeIgniter API.`
- [ ] **P1** Configurar topics: `sigra`, `earthquake-monitoring`, `seismology`, `codeigniter4`, `php`, `mysql`, `leaflet`, `open-source`, `usgs`, `colombia`.
- [ ] **P1** Configurar website solamente si hay una demo pública estable y segura; de lo contrario dejar vacío.
- [ ] **P1** Definir una imagen de vista previa (social preview) coherente con la identidad de SIGRA.
- [ ] **P1** Añadir capturas con datos atribuidos a las fuentes correspondientes, sin simular alertas reales.
- [ ] **P2** Añadir badges **reales** de licencia, versión publicada y CI cuando el workflow exista y funcione.
- [ ] **P2** Publicar una demo controlada y datos de ejemplo, sin credenciales ni telemetría sensible.

---

## Fase 1 — Instalación reproducible y dependencias (`P0` / `P1`)

- [x] **P0** Definir **un único procedimiento de instalación soportado** y repetirlo consistentemente en README y CONTRIBUTING.
- [x] **P0** Verificar que `composer install` resuelve las dependencias correctas desde `composer.lock`, si Composer será el método oficial.
- [x] **P0** Comprobar que `composer.json` y `composer.lock` coinciden (`composer validate --strict` y una instalación limpia).
- [x] **P0** Revisar cuidadosamente `composer.json`: cambiar nombre/descripción del **proyecto raíz** si procede, sin alterar el paquete de CodeIgniter requerido ni sus avisos legales.
- [x] **P0** Asegurar que los scripts y autoload de Composer apuntan a las rutas efectivas usadas por SIGRA.
- [ ] **P0** Precisar requisitos mínimos probados de PHP, MySQL/MariaDB, Composer y extensiones. No prometer compatibilidad con versiones no probadas.
- [x] **P0** Documentar generación de `encryption.key`, `APP_API_TOKEN` y otros secretos **sin valores reales**; recomendar claves de alta entropía.
- [x] **P0** Probar arranque desde una base de datos vacía y comprobar que todas las migraciones se aplican en el orden correcto.
- [x] **P1** Incluir troubleshooting para extensiones ausentes (`intl`, `mbstring`, `mysqli`, `curl`), permisos `writable/`, URLs y errores de conexión.
- [ ] **P1** Añadir procedimientos equivalentes para Linux y, solo si se soporta, Windows; evitar instrucciones que requieran herramientas no documentadas.
- [ ] **P1** Proporcionar datos de demostración **sintéticos** para pruebas de interfaz sin depender de fuentes externas activas.
- [x] **P1** Mantener `.env.example` completo y sin secretos; marcar claramente qué variables son obligatorias, opcionales o exclusivas de producción.
- [ ] **P1** Decidir si ofrecer Docker Compose para desarrollo (PHP + DB), como vía opcional y no como nueva dependencia obligatoria.
- [ ] **P2** Implementar un comando de diagnóstico de prerrequisitos (`sigra:doctor`) si las instalaciones de terceros se vuelven frecuentes.

**Ejemplo mínimo de arranque a validar (no publicar como probado hasta ejecutarlo):**

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
# Configurar .env y crear la base de datos de desarrollo.
php spark migrate --all
php spark sigra:sync
php spark serve
```

> Si se decide distribuir `system/` por separado del gestor de paquetes, reemplazar estos pasos por un procedimiento que realmente funcione. **No** prometer simultáneamente modalidades incompatibles.

---

## Fase 2 — Organización del código y arquitectura (`P1`)

- [x] **P1** Revisar que cada controlador administre petición/respuesta y no concentre consultas, deduplicación o cálculos sísmicos complejos.
- [x] **P1** Mantener ingestión, normalización, fusión, almacenamiento y publicación como responsabilidades separadas y testeables.
- [x] **P1** Definir interfaces/adaptadores para proveedores sísmicos (SGC y USGS) con DTO o estructura de evento normalizada documentada.
- [x] **P1** Centralizar validación de datos externos: latitud, longitud, profundidad, magnitud, fecha/hora, fuente e identificadores.
- [x] **P1** Reducir duplicaciones entre endpoint web, endpoint API y comandos CLI, reutilizando servicios sin acoplarlos a HTTP.
- [x] **P1** Verificar que no exista lógica de negocio sensible implementada exclusivamente en las vistas.
- [ ] **P1** Revisar dependencias entre `app/Libraries`, `app/Models`, `app/Controllers` y `app/Config`; evitar dependencias circulares.
- [ ] **P1** Revisar tipado PHP, resultados nulos, excepciones y manejo de errores; usar nombres coherentes y comentarios orientados al porqué.
- [x] **P1** Crear un documento de decisiones arquitectónicas (ADR) para explicar deduplicación, cache, proveedor principal y sincronización.
- [ ] **P1** Documentar contratos internos de eventos y diferencia entre `source_id`, identificador interno y evento fusionado.
- [ ] **P2** Introducir interfaces formales donde reduzcan acoplamiento; evitar abstraer cada clase sin beneficio comprobable.
- [ ] **P2** Evaluar si las dependencias copiadas o vendorizadas en `system/` se pueden actualizar de forma segura y rastreable.

---

## Fase 3 — Seguridad del backend, API y operación (`P0` / `P1`)

> **Importante:** las tareas de esta sección son **controles técnicos a comprobar o implantar**. No suponen que se haya identificado una explotación ni implican crear `SECURITY.md`.

### 3.1 Credenciales, autenticación y autorización

- [ ] **P0** Revisar el historial de Git para detectar credenciales, tokens, contraseñas, dumps, datos personales o archivos `.env` que se hayan publicado accidentalmente; rotar cualquier secreto expuesto.
- [x] **P0** Mantener `.env`, claves privadas, backups y `writable/` excluidos del control de versiones, sin excluir migraciones o fixtures necesarios.
- [ ] **P0** Revisar qué endpoints son públicos, cuáles requieren token y cuál es el motivo de cada decisión.
- [ ] **P0** No usar indefinidamente un único Bearer token global para identificar todos los dispositivos ciudadanos: evaluar credenciales **por dispositivo**, revocación y rotación.
- [ ] **P0** Comprobar que registro de dispositivos y reportes de detección no permitan suplantación trivial, reenvíos ilimitados ni acceso entre dispositivos.
- [ ] **P1** Exigir HTTPS en producción para todas las APIs autenticadas; impedir que secretos aparezcan en query strings o logs.
- [x] **P1** Revisar comparaciones de tokens en tiempo constante y manejo de tokens malformados o ausentes.
- [ ] **P1** Establecer límites de solicitudes por IP/dispositivo/token, especialmente para registro, detecciones, sincronización y endpoints costosos.
- [ ] **P1** Añadir detección de abuso y revocación administrativa de dispositivos/tokens con trazabilidad.

### 3.2 Validación, navegador e infraestructura

- [ ] **P0** Validar en servidor rangos de coordenadas, radios, magnitudes, fechas, `limit`, ordenación, IDs y tipos de contenido.
- [ ] **P0** Verificar consultas parametrizadas y ausencia de SQL construido concatenando valores de usuario.
- [ ] **P0** Auditar salida HTML/JS para evitar XSS; comprobar que enlaces a informes externos se tratan como URLs no confiables.
- [ ] **P0** Configurar raíz web en `public/`; evitar exposición de `app/`, `.env`, archivos de backup, `writable/` y directorios internos.
- [ ] **P1** Revisar CORS por endpoint y origen; no permitir orígenes arbitrarios junto con credenciales.
- [ ] **P1** Configurar cabeceras HTTP apropiadas (CSP realista, `X-Content-Type-Options`, `Referrer-Policy` y HSTS tras asegurar HTTPS).
- [ ] **P1** Verificar CSRF para formularios autenticados por cookie y distinguirlo de endpoints Bearer stateless.
- [x] **P1** Configurar manejo global de errores que oculte trazas y credenciales en producción, con IDs de correlación para soporte.
- [ ] **P1** Añadir exploración de dependencias vulnerables y actualizaciones periódicas del framework.
- [ ] **P1** Evaluar degradación o pausa de endpoints si se detecta comportamiento abusivo o datos sospechosos.
- [ ] **P2** Documentar un canal privado de contacto con el mantenedor **solo si existe y se atenderá**; no es requisito crear un archivo de política.

---

## Fase 4 — API pública y contratos (`P1`)

- [ ] **P1** Documentar todos los endpoints existentes: método, ruta, autenticación, parámetros, límites, valores por defecto y respuestas.
- [ ] **P1** Documentar ejemplos reales de `200`, `400`, `401`, `403`, `404`, `409`, `422`, `429` y `500` según correspondan; no prometer estados que la API no devuelve.
- [ ] **P1** Homogeneizar los errores JSON con un esquema como `code`, `message`, `details`, `request_id`.
- [ ] **P1** Usar formatos consistentes: ISO 8601 con zona horaria, coordenadas WGS84, magnitud sin unidad ambigua y distancias en km.
- [ ] **P1** Definir políticas de paginación, `limit` máximo, filtros, ordenación y límites de consulta histórica.
- [ ] **P1** Distinguir claramente datos oficiales importados, datos derivados por SIGRA y observaciones ciudadanas experimentales.
- [x] **P1** Definir reglas de compatibilidad y deprecación antes de cambiar contratos consumidos por frontend y clientes futuros.
- [x] **P1** Publicar especificación OpenAPI 3.x del backend (solo endpoints implementados).
- [x] **P1** Añadir pruebas de contrato para autenticación, filtros, paginación, datos inválidos y errores.
- [x] **P1** Revisar `/api/health`: exponer solo información necesaria, sin versiones internas sensibles, secretos ni detalles innecesarios de infraestructura.
- [ ] **P2** Considerar prefijo de versión (`/api/v1`) cuando existan cambios incompatibles; no romper consumidores sin plan.
- [ ] **P2** Añadir ejemplos de `curl` reproducibles con valores de muestra, sin tokens en claro.

---

## Fase 5 — Calidad de datos sísmicos y sincronización (`P0` / `P1`)

- [ ] **P0** Diferenciar la hora del sismo, hora de publicación por proveedor, hora de recepción y hora de última actualización local.
- [x] **P0** Tratar SGC y USGS como fuentes externas falibles; una fuente caída no debe borrar ni invalidar datos correctos de otra.
- [x] **P0** Evitar duplicados con criterios explícitos de tiempo, distancia, magnitud, fuente y nivel de confianza; conservar trazabilidad de registros fusionados.
- [ ] **P0** Implementar actualización de eventos ya publicados cuando cambien magnitud, profundidad, coordenadas o estado oficial.
- [x] **P1** Probar la normalización con entradas vacías, malformadas, fechas inconsistentes, magnitudes desconocidas y latitudes/longitudes de borde.
- [x] **P1** Documentar umbrales y posibles falsos positivos de la deduplicación entre proveedores.
- [ ] **P1** Registrar origen, identificador externo, versión y `fetched_at` en cada observación almacenada cuando el esquema lo permita.
- [x] **P1** Añadir timeout explícito, reintentos acotados con backoff y control de respuestas HTTP 429/5xx.
- [x] **P1** Hacer sincronización idempotente y segura ante ejecuciones concurrentes (bloqueo o mecanismo equivalente).
- [ ] **P1** Reportar métricas de sincronización: eventos recibidos, insertados, actualizados, deduplicados, errores y duración.
- [ ] **P1** Verificar atribución, términos de uso y políticas de consumo de SGC/USGS y los proveedores de mapas.
- [x] **P2** Introducir fixtures versionados de respuestas SGC/USGS para reproducir regresiones sin internet.
- [ ] **P2** Considerar fuentes adicionales solo después de estabilizar las dos actuales y su capa de normalización.

---

## Fase 6 — Red ciudadana y validación científica (`P0` / `P1`)

> Esta fase es **indispensable antes de considerar la plataforma apta para alertas reales**. El cálculo geométrico correcto en datos simulados no garantiza detección fiable, tiempos de aviso útiles ni ausencia de falsas alarmas.

- [ ] **P0** Etiquetar claramente todas las alertas provenientes de la red telefónica como **experimentales** mientras no exista validación independiente.
- [ ] **P0** Evitar mensajes que impliquen predicción de terremotos o equivalencia con alertas oficiales.
- [ ] **P0** Diseñar un modo de simulación aislado de producción; prohibir que detecciones de prueba se publiquen como alertas reales.
- [ ] **P0** Definir condiciones mínimas para correlacionar sensores: cantidad, dispersión geográfica, calidad del reloj, precisión GPS y coherencia temporal.
- [ ] **P0** Incorporar protección frente a detecciones fabricadas, repetidas, inverosímiles y enviadas por dispositivos comprometidos.
- [ ] **P1** Medir y documentar tasas de falsos positivos/falsos negativos sobre un conjunto de datos representativo.
- [ ] **P1** Probar con vibraciones no sísmicas: transporte, golpes, caídas, movimiento humano y obras cercanas.
- [ ] **P1** Considerar desfases de reloj, latencias de red, pérdida de paquetes, sensores ruidosos y diferentes modelos de teléfono.
- [ ] **P1** Calibrar y justificar velocidades de propagación usadas por el modelo; cuantificar incertidumbre de ubicación y de tiempo estimado.
- [ ] **P1** Añadir intervalos de confianza o estados de incertidumbre; no publicar tiempos de llegada con precisión ilusoria.
- [ ] **P1** Rechazar automáticamente eventos cuya geometría no permita triangulación fiable.
- [ ] **P1** Registrar versiones de algoritmo, umbrales y parámetros usados para permitir evaluación posterior.
- [ ] **P1** Validar con registros históricos y, después, pilotos controlados bajo supervisión experta antes de cualquier despliegue de alerta pública.
- [ ] **P1** Diseñar un mecanismo de desactivación inmediata de alertas automáticas ante anomalías.
- [ ] **P1** Crear documentación de limitaciones científicas y operativas revisable por especialistas.
- [ ] **P2** Publicar un benchmark reproducible del algoritmo con datos sintéticos y, donde sea posible, datos reales con permiso y procedencia verificable.

---

## Fase 7 — Base de datos, migraciones y rendimiento (`P1`)

- [ ] **P1** Revisar índices para búsquedas por fecha, magnitud, fuente, identificador externo y coordenadas cuando sea relevante.
- [ ] **P1** Evaluar consultas y planes de ejecución sobre un volumen histórico representativo; no optimizar sin métricas.
- [ ] **P1** Asegurar restricciones `UNIQUE`, claves foráneas y tipos de datos coherentes donde el modelo lo requiera.
- [ ] **P1** Verificar manejo de concurrencia y transacciones durante deduplicación, inserción de detecciones y generación de alertas.
- [ ] **P1** Documentar actualización y reversión de migraciones, incluyendo respaldos previos a cambios destructivos.
- [ ] **P1** Definir retención y purga de logs, datos temporales y telemetría; evitar crecimiento ilimitado.
- [ ] **P1** Separar o archivar registros históricos grandes según necesidades reales de rendimiento y consulta.
- [ ] **P1** Probar comportamiento con tablas vacías, millones de registros y consultas sin filtros.
- [ ] **P1** Validar el cache por proveedor, TTL, invalidación y control de tormentas de solicitudes.
- [ ] **P2** Añadir índices espaciales o estructura geográfica solo si los patrones de consulta demuestran beneficio.
- [ ] **P2** Crear scripts documentados de backup y restauración con pruebas periódicas de recuperación.

---

## Fase 8 — Pruebas automatizadas y estándares (`P0` / `P1`)

- [ ] **P0** Garantizar que existe **un comando único y documentado** para ejecutar los tests desde un clon limpio.
- [ ] **P0** Confirmar que el proyecto instala las herramientas de test necesarias; no depender de paquetes locales no declarados.
- [ ] **P1** Ampliar `tests/PwaveFusionTest.php` con entradas degeneradas, ruido, desfase temporal y fallos de estaciones.
- [ ] **P1** Probar normalizadores SGC/USGS con fixtures reales anonimizados o públicos y respuestas corruptas.
- [ ] **P1** Crear tests del servicio de sincronización y deduplicación con base de datos de prueba aislada.
- [ ] **P1** Crear tests de integración de rutas API (autenticación, validación, estados HTTP, JSON y filtros geográficos).
- [ ] **P1** Crear pruebas que garanticen que fallos de proveedores externos no provocan caída completa del sistema.
- [ ] **P1** Añadir pruebas de regresión para fechas, husos horarios, coordenadas internacionales y magnitudes faltantes.
- [ ] **P1** Probar permisos de escritura de `writable/`, generación de caché y mensajes de error esperados.
- [ ] **P1** Adoptar PHP-CS-Fixer o PHP_CodeSniffer conforme a PSR-12 **siempre que se documente el uso**.
- [ ] **P1** Incorporar análisis estático (PHPStan o Psalm) de forma progresiva, estableciendo un baseline controlado.
- [ ] **P1** Revisar `node --check` y, según la complejidad, ESLint para scripts JavaScript propios.
- [ ] **P1** Medir cobertura de las capas críticas sin convertir un porcentaje global arbitrario en el objetivo principal.
- [ ] **P2** Integrar pruebas de navegador para mapa, filtros, geolocalización denegada, idioma y PWA.
- [ ] **P2** Agregar pruebas de carga para sincronización, API de eventos y recepción de detecciones.

### 8.1 CI recomendada en GitHub Actions

- [ ] **P1** Crear un workflow que se ejecute en cada pull request y push a la rama principal.
- [ ] **P1** Instalar PHP y extensiones necesarias, ejecutar `composer validate`, `composer install`, linter y tests.
- [ ] **P1** Añadir un servicio de base de datos de prueba cuando se ejecuten tests de integración.
- [ ] **P1** Usar secretos de prueba efímeros; jamás desplegar secretos de producción en pipelines de PR de terceros.
- [ ] **P1** Fallar la verificación por errores reales; no permitir `|| true` en pruebas críticas.
- [ ] **P1** Mostrar badge de CI únicamente después de obtener ejecuciones verdes reproducibles.
- [ ] **P2** Crear una matriz con versiones de PHP que **de verdad se soportan** y hacer seguimiento de deprecaciones.

---

## Fase 9 — Frontend, PWA, mapas y accesibilidad (`P1` / `P2`)

- [ ] **P1** Revisar tiempos de carga en móvil, tamaño de recursos, caché y uso de CPU durante la actualización del mapa.
- [ ] **P1** Evitar recrear todos los marcadores del mapa cuando solo cambian unos pocos eventos; medir la mejora.
- [ ] **P1** Mantener funcionamiento útil con conexión lenta, fallos de tiles y caídas temporales de proveedores.
- [ ] **P1** Mostrar fuente y fecha de actualización del evento con claridad; distinguir fechas locales/UTC.
- [ ] **P1** Hacer visibles estados de carga, errores de sincronización, resultados vacíos y fecha de última actualización correcta.
- [ ] **P1** Verificar navegación por teclado, foco visible, contraste, roles ARIA y alternativas textuales para el mapa.
- [ ] **P1** Asegurar que los avisos experimentales no dependan únicamente de color o sonido para comunicar su estado.
- [ ] **P1** Revisar consentimiento de geolocalización y explicar el envío a servicios terceros de ubicación aproximada si existe fallback por IP.
- [ ] **P1** Verificar el service worker: no cachear respuestas autenticadas ni servir alertas o datos obsoletos como actuales.
- [ ] **P1** Diseñar mensajes offline que indiquen explícitamente cuándo los datos podrían estar desactualizados.
- [ ] **P1** Probar el flujo de permiso denegado, localización desactivada y ubicación inexacta.
- [ ] **P2** Ofrecer vista de lista completamente usable sin mapa y filtros accesibles en pantallas pequeñas.
- [ ] **P2** Añadir agregación de marcadores o clustering si la densidad de eventos causa pérdida de rendimiento.
- [ ] **P2** Verificar texto, formatos y traducciones de español/inglés; evitar claves de idioma mostradas al usuario.

---

## Fase 10 — Observabilidad, despliegue y operaciones (`P0` / `P1`)

- [ ] **P0** Configurar `CI_ENVIRONMENT=production`, HTTPS, secretos fuera del repositorio y servidor apuntando solo a `public/`.
- [x] **P0** Documentar políticas de recuperación tras fallo de base de datos o caída sostenida de las fuentes sísmicas.
- [x] **P1** Definir diferencia entre servicio **activo**, base de datos **disponible** y datos **actualizados**; no confundir health con frescura de información.
- [ ] **P1** Separar health checks de liveness y readiness si el despliegue lo necesita, sin exponer detalles internos de forma pública.
- [ ] **P1** Ejecutar sincronización de forma programada, con bloqueo contra concurrencia, timeout y manejo de errores.
- [ ] **P1** Alertar al operador si la última sincronización exitosa supera un umbral definido.
- [ ] **P1** Registrar errores estructurados con contexto mínimo necesario; nunca incluir tokens, geolocalización exacta de usuarios ni payloads sensibles innecesarios.
- [ ] **P1** Definir rotación, retención y acceso restringido a logs.
- [x] **P1** Documentar copia de seguridad, restauración, actualizaciones de dependencias y rollback.
- [ ] **P1** Preparar un checklist de producción ejecutable antes de cada Release.
- [ ] **P1** Revisar permisos del usuario del servidor y políticas de acceso de MySQL por mínimo privilegio.
- [ ] **P2** Monitorizar latencia, errores por ruta, duración de ingestión, antigüedad del último evento y espacio de almacenamiento.
- [ ] **P2** Preparar un entorno staging con datos sintéticos y configuración aislada de producción.

---

## Fase 11 — Privacidad, atribuciones y responsabilidad (`P0` / `P1`)

- [ ] **P0** Separar en la documentación los eventos oficiales importados y reportes de sensores ciudadanos. Evitar presentar ambos como información oficial validada.
- [ ] **P0** Mantener visible que SIGRA no predice terremotos ni sustituye al SGC, USGS o sistemas oficiales de emergencia.
- [ ] **P1** Documentar qué información puede recibir el backend de dispositivos: coordenadas, identificadores, marcas temporales, registros y propósito de cada campo.
- [ ] **P1** Definir periodos de conservación y borrado de datos personales o potencialmente identificables según el alcance real del proyecto.
- [ ] **P1** Evaluar riesgo de reidentificación aun cuando el dashboard redondee posiciones aproximadamente a 1 km.
- [ ] **P1** Verificar las condiciones de uso y atribución de datos SGC/USGS, mapas, teselas, bibliotecas y demás terceros.
- [ ] **P1** Comprobar que `THIRD_PARTY_NOTICES.md` y `LICENSE` describan correctamente los componentes incluidos y sus licencias.
- [ ] **P1** Revisar la política de privacidad y requisitos aplicables cuando el sistema opere con datos de usuarios reales en Colombia u otras jurisdicciones.
- [ ] **P2** Preparar una página pública de metodología/limitaciones orientada a usuarios no técnicos.

---

## Fase 12 — Comunidad open source y gestión de versiones (`P1` / `P2`)

- [x] **P1** Mantener CONTRIBUTING con pasos de instalación **que funcionen** y describan cómo ejecutar los tests.
- [x] **P1** Crear plantillas de Issues: bug, propuesta de mejora y reporte de documentación.
- [x] **P1** Crear plantilla de Pull Request con resumen, motivo, pruebas realizadas y capturas si corresponden.
- [ ] **P1** Registrar tareas claras como `good first issue`, `help wanted`, `documentation`, `bug`, `enhancement` y `needs-tests`.
- [x] **P1** Establecer un esquema sencillo de ramas (por ejemplo, `main` y ramas cortas por funcionalidad) y cambios mediante PR.
- [ ] **P1** Publicar un release/tag `v1.1.1` **solo si coincide con el código y el changelog que realmente se entregan**; si no, corregir la versión anunciada.
- [x] **P1** Mantener un solo changelog canónico y relacionar cada cambio visible con su release.
- [ ] **P1** Añadir una definición de hecho (Definition of Done): CI verde, pruebas de cambio, documentación y compatibilidad comprobada.
- [ ] **P2** Establecer revisión de código y protección de la rama principal cuando exista un flujo regular de PR.
- [ ] **P2** Redactar guía para agregar nuevas fuentes sísmicas con fixtures, normalización, errores y atribución.
- [ ] **P2** Explicar el modelo de gobernanza: quién decide, tiempos razonables de revisión y alcance de contribuciones externas.
- [ ] **P2** Mantener actualizados Code of Conduct y contribución sin multiplicar archivos de plantilla innecesarios.

---

## Orden recomendado de ejecución

### Sprint A — Repositorio coherente (primero)

**Meta:** que un visitante entienda qué existe y pueda instalarlo.

1. Corregir URLs de clonación y enlaces Markdown.
2. Quitar referencias heredadas a `SECURITY.md`.
3. Sustituir secciones `mobile/` por la nota de Android en desarrollo y repositorio independiente.
4. Corregir afirmaciones sobre `vendor/`, `system/` y Composer.
5. Probar instalación en clon limpio con base de datos vacía.
6. Ajustar README en inglés y español y completar About de GitHub.

**Terminado cuando:** un tercero puede clonar, instalar, migrar y ejecutar la parte web/backend sin preguntar al autor ni buscar archivos ausentes.

### Sprint B — Calidad y automatización

1. Unificar comando de tests.
2. Añadir test de API, sincronización y normalizadores con fixtures.
3. Crear CI que valide Composer, sintaxis, estilos y tests.
4. Corregir errores detectados y documentar ejecución.
5. Agregar plantillas de Issue/PR y etiquetar primeras tareas.

**Terminado cuando:** un PR nuevo recibe verificación automática confiable y los fallos importantes pueden reproducirse.

### Sprint C — Robustez técnica y seguridad

1. Revisar autenticación/autorización de API y dispositivos.
2. Endurecer validaciones, rate limiting, manejo de secretos, HTTPS y rutas públicas.
3. Garantizar sincronización idempotente y robusta ante fallos externos.
4. Revisar índices, migraciones, concurrencia y monitoreo.
5. Ensayar restauración desde backup y procedimientos de rollback.

**Terminado cuando:** los principales fallos operativos tienen medidas preventivas, detección y procedimientos de recuperación probados.

### Sprint D — Ciencia, experiencia y publicación

1. Diseñar protocolo de validación del sistema experimental de detección.
2. Ejecutar benchmarks y pruebas negativas con trazabilidad.
3. Mejorar accesibilidad, PWA, estados offline y rendimiento.
4. Completar OpenAPI, documentación de límites y capturas reales.
5. Publicar una Release solo tras comprobar los criterios definidos.

**Terminado cuando:** se pueden demostrar los resultados publicados y el estado experimental no genera confusión con alertas oficiales.

---

## Métricas de éxito sugeridas

| Área | Evidencia de cumplimiento |
|---|---|
| Reproducibilidad | Un clon limpio instala dependencias, ejecuta migraciones, tests y servidor con la documentación publicada. |
| Documentación | Cero enlaces rotos conocidos; README principal y traducción sincronizados; distinción entre disponible/experimental. |
| Calidad | CI pasa en PR; tests cubren normalizadores, deduplicación, endpoints y casos de fallo. |
| Seguridad | Sin secretos expuestos; autenticación y rate limiting probados; análisis de dependencias al día. |
| Datos | Sin duplicados injustificados en los casos de prueba; trazabilidad a SGC/USGS; fallos de proveedor aislados. |
| Operación | Freshness de fuentes observable; backup y restore ensayados; logs no exponen datos sensibles. |
| Riesgo sísmico | Validación documentada y límites explícitos; ninguna alerta experimental presentada como oficial. |
| Comunidad | Issues reproducibles, PR con checklist y versión/tag consistente con el código. |

## Lista final de aceptación antes de anunciar una versión estable

- [ ] Instalación limpia reproducible.
- [ ] Documentación sin enlaces inexistentes ni instrucciones de `mobile/` en este repositorio.
- [ ] Sin referencias heredadas a `SECURITY.md`.
- [ ] Descripción clara: app Android en construcción y repositorio aparte.
- [ ] Sin secretos o datos privados en archivos ni historial de Git.
- [ ] CI y tests principales exitosos.
- [ ] Validaciones, permisos y contratos de la API verificados.
- [ ] Fuentes externas y sincronización resilientes ante fallos.
- [ ] Logs, backups y procedimientos de despliegue comprobados.
- [ ] Interfaz comprensible cuando hay datos viejos, conexión perdida o permisos de ubicación denegados.
- [ ] Atribución de fuentes y disclaimers correctos.
- [ ] Release, tag y changelog coinciden con el código publicado.
- [ ] Capacidades experimentales descritas **como experimentales**, no como sistema oficial o validado para emergencias.

---

## Notas de alcance y limitaciones de esta revisión

Este documento combina **hallazgos directamente visibles** en el repositorio público y **recomendaciones de auditoría y mejora**. No se ejecutó un entorno de producción, no se compiló la aplicación, no se midió desempeño ni se realizó un pentest. Por ello, una casilla de seguridad o rendimiento significa «verificar e implementar si falta», no «vulnerabilidad comprobada». Esta distinción debe conservarse al convertir el documento en Issues.

**Decisión final del proyecto:** SIGRA web/backend y SIGRA Android serán repositorios separados. No añadir el código móvil aquí. No crear un `SECURITY.md` para compensar los enlaces de plantilla: simplemente retirar esas referencias.
