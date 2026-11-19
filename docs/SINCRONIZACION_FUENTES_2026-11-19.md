# Revisión de sincronización de fuentes · 19 de noviembre de 2026

## SGC: conexión correcta, resumen incompleto para el objetivo del mapa

La revisión local encontró HTTP 200 y 101 eventos normalizados del feed
`five_days_2.json`. Todos estaban en la base con los mismos datos de fecha,
coordenadas, magnitud, profundidad, lugar y estado. El problema no estaba en
Cesium: ese resumen incluía únicamente magnitudes de 2 o mayores en la muestra.

Una consulta de diagnóstico al feed oficial `five_days_all.json` recibió HTTP 200
y 539 eventos: 438 identificadores adicionales respecto del resumen anterior.
Entre los más recientes figuraban Chaparral, Tolima, a las 07:54 (M1.1) y 07:36
(M1.2), hora colombiana del 19 de noviembre. El resumen anterior terminaba a las
07:33. Estos números describen esa respuesta concreta, no valores constantes.

La configuración predeterminada usa ahora el resumen completo:

```text
https://archive.sgc.gov.co/feed/v1.0.1/summary/five_days_all.json
```

El umbral global M2.5 de `Config\Quakes` sigue aplicándose a los proveedores
globales; no excluye sismos SGC de menor magnitud. Los filtros que seleccione el
usuario en el mapa sí pueden ocultarlos. Se conserva la verificación TLS, el
User-Agent compatible, el intervalo de quince minutos por host y las pausas.

Si un `.env` define `sgc.feedUrl` con el resumen anterior, ese valor prevalece:
cámbialo al feed completo o retira el override. Al cambiar de URL, la caché anterior
no se utiliza como si fuera una respuesta del nuevo feed. La pausa por host puede
seguir vigente hasta la próxima consulta permitida; no borres sus archivos.

## Referencias entre agencias en actualizaciones parciales

Las observaciones correlacionadas pueden guardarse bajo el evento preferido, con
prioridad SGC. Por eso la cantidad de filas de una agencia no tiene que coincidir
con la cantidad que devolvió su feed: parte puede estar representada en
`source_references` de otra fila.

Se reprodujo otro defecto: al actualizar un evento desde una fuente disponible,
una fuente caída no participaba en el nuevo lote y sus referencias anteriores
podían desaparecer de la metadata. Ahora el guardado conserva las referencias
observadas para el mismo proveedor e identificador original. Las correcciones
de magnitud, lugar y demás datos de la fuente principal siguen actualizándose.

Las referencias conservadas son **procedencia histórica**, no una confirmación
nueva de la fuente ausente ni una validación científica del algoritmo de
correlación. Las referencias que ya se hubieran perdido se recuperan cuando una
consulta válida permite volver a relacionar las observaciones.

## Auditoría local de solo lectura

Desde la raíz del proyecto:

```bash
php tools/audit-source-sync.php
```

Lee la conexión configurada, el catálogo local y las respuestas ya guardadas.
No contacta proveedores, escribe eventos ni levanta pausas. Muestra el estado
de las seis conexiones y, por fuente, observaciones normalizadas, coincidencias
con filas, referencias correlacionadas, ausencias y diferencias científicas.
También informa del catálogo visible y los últimos lotes de ingesta.

Una ausencia en una respuesta limitada no demuestra que un evento histórico sea
inválido. GeoNet y GEOFON, por ejemplo, no garantizan un catálogo completo de cinco
días. Los datos fuera de la ventana activa no aparecen en el mapa. Una conexión
correcta tampoco demuestra que el proveedor haya publicado ya todos sus eventos
o revisiones. Las diferencias contra una caché antigua se deben interpretar junto
con sus fechas y el estado de la conexión.

## Verificación de la corrección

- Regresión del normalizador: feed completo predeterminado y aceptación de SGC M1.1.
- Regresión de procedencia: fallo parcial, revisión científica, unión de referencias,
  repetición idempotente y separación de identidades.
- Prueba MySQL en `sigra_source_sync_test`: las referencias sobreviven a un guardado
  parcial y la nueva magnitud se conserva; fixtures revertidos mediante transacción.
- Suite completa de arquitectura, proveedores, presentación, filtros y contratos HTTP.
- PHPStan sin errores en su alcance configurado.

Estas pruebas no certifican exhaustividad científica ni disponibilidad permanente
de las agencias. La revisión corresponde al entorno local; no se desplegó en producción.

## Resultado del ciclo real local · 11:42 COT

Después de respetar las pausas, las seis conexiones terminaron sin errores.
La auditoría frente a sus últimas respuestas guardadas produjo:

| Fuente | Normalizados | Coinciden con fila propia | Representados por referencia | Ausentes | Diferencias científicas o de metadata |
|---|---:|---:|---:|---:|---:|
| SGC | 539 | 539 | 0 | 0 | 0 |
| USGS | 214 | 214 | 0 | 0 | 0 |
| EMSC | 1066 | 885 | 181 | 0 | 0 |
| GeoNet | 16 | 16 | 0 | 0 | 0 |
| GEOFON | 40 | 9 | 31 | 0 | 0 |
| INGV | 19 | 11 | 8 | 0 | 0 |

EMSC se recuperó del timeout anterior. El lote diagnóstico no incluyó el
publicador de notificaciones; no se cambió la configuración de alertas.
La página real del puerto 8080 respondió HTTP 200 y mostró 1673 eventos
con los mismos conteos que la consulta del catálogo. De los 539 SGC
normalizados, 538 quedaban dentro de los cinco días del mapa en esa comprobación;
un evento acababa de salir de la ventana. Los filtros del usuario pueden reducir
lo visible aún más. El evento SGC más reciente de esa respuesta era de las 07:54
hora local: SIGRA no puede mostrar como publicado un evento que el feed no entrega.

Los conteos corresponden a ese instante y a las respuestas consultadas, no
a una garantía permanente de exhaustividad de las agencias.
