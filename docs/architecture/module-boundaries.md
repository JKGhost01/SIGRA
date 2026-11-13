# Módulos, propiedad y operaciones

| Módulo | Datos que modifica | Operaciones públicas |
|---|---|---|
| SeismicCatalog | `sismos` | `SeismicEventReader`, `EventWriter`, `ReportEvidenceCatalog` |
| SeismicIngestion | `sigra_ingestion_batches` | `SyncEventsHandler`, `SeismicSource` |
| CitizenReports | `citizen_reports` | `SubmitCitizenReportHandler`, `ReportReconciler` |
| SensorNetwork | `red_devices`, `red_detections` | `SensorNetworkService`, `RegisterDetectionHandler`, repositorios de evidencia |
| Alerting | `red_alerts`, `red_alert_deliveries`, `sigra_alert_decisions` | `AlertingService`, `AlertDeliveryHandler`, `OfficialEventNotifier`, `AlertStatisticsReader` |
| System | Sin tablas propias | `HealthHandler`, páginas públicas |
| Shared | Sin tablas de negocio | `Clock`, `CacheStore`, `GeoDistance`, fronteras HTTP |

La reconciliación escribe anotaciones del catálogo por `ReportEvidenceCatalog`, nunca
mediante SQL en CitizenReports. Comparte conexión con el repositorio ciudadano para
conservar su transacción y bloqueo MySQL. La correlación entre fuentes no altera datos
científicos: conserva la referencia preferida y los IDs de ambas fuentes. Si existen
dos posibles eventos SGC, mantiene el candidato USGS sin fusionarlo arbitrariamente.

Alerting consulta y asigna clusters mediante el puerto público `DetectionRepository`;
solo la infraestructura de SensorNetwork modifica `red_detections`. Las estadísticas
públicas obtienen los agregados de alertas mediante `AlertStatisticsReader`.

La lista no exige crear CRUD por tabla. No existen operaciones web públicas de creación,
edición o borrado de sismos oficiales. `/sismos/create` sigue siendo un reporte público
protegido con CSRF, desafío de sesión y cuotas HMAC; no requiere login.
