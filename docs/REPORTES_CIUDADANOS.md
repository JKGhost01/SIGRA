# Reportes ciudadanos · SIGRA 1.1.1

Los reportes del formulario son observaciones de personas, no epicentros ni
magnitudes instrumentales. Se guardan de forma privada en **una sola tabla
`citizen_reports` de la base MySQL existente**. Nunca se insertan en `sismos`.
No se requiere otra base de datos.

## Validación y publicación

1. Se guarda el reporte como `pending` con lugar, coordenadas, hora e intensidad
   percibida. Los campos de fuente, estado, magnitud oficial y profundidad no
   se aceptan del ciudadano.
2. Se busca un evento **SGC** compatible: hasta **5 minutos** de diferencia y
   hasta **150 km** entre el punto reportado y el epicentro oficial.
3. Si no hay coincidencia, o hay más de un candidato, el reporte sigue pendiente.
4. Con **3 participantes distintos** asociados al mismo evento, se marcan como
   `confirmed` y se actualiza el contador del evento oficial existente.
5. Se muestra **«Reportado por N usuarios»** en listado, popup del mapa,
   histórico y detalle. La API expone solo el entero `citizen_reports_count`.

Un evento oficial SGC/USGS sigue siendo público aunque no tenga reportes
ciudadanos. El umbral decide cuándo publicar el conteo de reportes; no decide
si un evento oficial existe. Cualquier cantidad de reportes sin coincidencia
SGC permanece privada. USGS no valida estos reportes.

El conteo es independiente de `felt`, publicado por la agencia. Nunca modifica
la magnitud, profundidad, coordenadas o fecha del evento oficial. Las
coincidencias se vuelven a revisar al sincronizar SGC y después de un envío;
se usan únicamente eventos oficiales ya almacenados, sin consultar SGC por
cada formulario. Una caída de SGC mantiene pendientes las observaciones sin
coincidencia disponible; no autoriza publicar eventos ciudadanos.

Los reportes sin resolver expiran a las 48 horas. Los registros privados se
purgan al superar 30 días durante la siguiente reconciliación. El contador
histórico agregado se conserva después de retirar los registros privados.
Mantén el cron `php spark sigra:sync` para ejecutar sincronización y limpieza
incluso cuando no haya visitantes.

## Participantes y protección contra spam

- CSRF activo para GET/POST del formulario, con cookies conservadas al redirigir.
- Desafío aleatorio de sesión de un solo uso, hasta cinco formularios abiertos,
  espera mínima de 3 segundos y vencimiento de una hora.
- Campo trampa fuera del recorrido de teclado y de lectores de pantalla.
- Cuerpo de hasta 8 KiB; tipos escalares, coordenadas finitas, referencia de hasta
  150 caracteres e intensidad percibida entre 0 y 10. Solo se aceptan horas de
  las últimas 24 horas, con 2 minutos de tolerancia al reloj adelantado.
- Máximo un reporte por minuto, tres por hora y diez por día para registros que
  compartan sesión o red. Los límites se consultan sobre `citizen_reports`;
  no existe una segunda tabla de cuotas.
- Claves HMAC de sesión y de red; no se guardan IP originales ni identificadores
  de sesión originales en la tabla. IPv6 se agrupa por /64; direcciones IPv4
  representadas como IPv6 se normalizan.
- Para sumar al conteo, un reporte no puede repetir la sesión ni la red de otro
  participante ya contado. Cambiar de cookie o rotar direcciones IPv6 del mismo
  bloque no aumenta los votos.
- MySQL `GET_LOCK` y transacciones InnoDB serializan inserciones/validaciones,
  evitando que solicitudes concurrentes superen cuotas o dupliquen el conteo.
- No hay endpoint público que publique los reportes ni sus ubicaciones.
- Las antiguas rutas de edición/eliminación devuelven 403: no existía una
  comprobación real de identidad y no pueden usarse para alterar eventos oficiales.

Sin cuentas verificadas, «usuarios» es un conteo conservador de participantes
deduplicados, no una prueba de identidad humana. Una red compartida puede contar
como un solo participante. Las tolerancias son criterios de asociación con un
catálogo oficial, no una detección científica independiente ni una alerta.

## Configuración

```ini
reports.minParticipants = 3
reports.matchSeconds = 300
reports.matchKm = 150
reports.hourlyLimit = 3
reports.dailyLimit = 10
# Opcional: de forma predeterminada se usa encryption.key.
# reports.hashKey = 'una-clave-estable-aleatoria-de-al-menos-32-caracteres'
```

El umbral no baja de 3. La clave debe ser estable y privada; si falta una clave
de al menos 32 caracteres, el envío se rechaza sin guardar el reporte.
Cambiarla altera la deduplicación respecto de reportes anteriores.

## Aplicar en la misma base MySQL

Desde la carpeta del proyecto, usando el PHP con `mysqli` habilitado:

```bash
php spark migrate --all
```

La migración `20261112000001_CreateCitizenReports` crea **solo
`citizen_reports`**, en InnoDB, y añade `sismos.citizen_reports_count` con valor
inicial 0. No borra tablas ni registros y no necesita `sigra:db-reset`.
Las filas antiguas de ciudadanos se conservan en `sismos` para auditoría,
pero se excluyen del acceso público por fuente y prefijo `SGI`. No aportan
votos: no disponen de identidad deduplicable.

La corrección también evita reutilizar listas antiguas del caché de sync;
solo las descargas externas se cachean y las filas públicas se vuelven a leer
mediante el modelo.

## Pruebas y alcance de esta revisión

```bash
php tests/CitizenReportLogicTest.php
python3 tests/CitizenReportHttpTest.py
php tests/CitizenReportsTest.php
```

- Lógica: 29 comprobaciones aprobadas de umbral, fuente SGC, ambigüedad,
  distancia, tiempo, duplicados, tipos y desafío del formulario; sin consultas SQL.
- HTTP: 11 comprobaciones aprobadas mediante PHP-CGI de CSRF, desafío,
  tamaño, entradas como listas y edición/eliminación bloqueadas; sin escrituras.
- MySQL: prueba preparada para la **base existente**, sin crear bases ni tablas
  de prueba. Solo escribe filas con identificadores de prueba dentro de una
  transacción que se revierte al terminar. Se limita a sus propios registros y
  exige InnoDB. Requiere haber aplicado la migración.

La prueba MySQL no pudo ejecutarse en el entorno del agente por fallo de conexión
2002. La migración tampoco pudo aplicarse allí; ambas quedan pendientes en el
equipo donde MySQL funciona. No se presentan las comprobaciones de lógica o CGI
como evidencia de escrituras reales correctas en MySQL.
