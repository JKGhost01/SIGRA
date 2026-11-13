-- Alternativa a php spark migrate --all. Ejecutar en la base actual, después de subir el código.
-- Sin prefijos. Conserva valores anteriores en metadata. Repetirlo no vuelve a invertir filas.
START TRANSACTION;
DROP TEMPORARY TABLE IF EXISTS sigra_usgs_coordinate_repair;
CREATE TEMPORARY TABLE sigra_usgs_coordinate_repair AS
SELECT id, latitud, longitud, sgc_metadata FROM sismos
WHERE fuente = 'USGS' AND ABS(longitud) <= 90 AND ABS(latitud) <= 180
AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(sgc_metadata), sgc_metadata, '{}'), '$.coordinate_order')), '') <> 'lat-lon-v2';

UPDATE sismos AS current_event
JOIN sigra_usgs_coordinate_repair AS original_event ON current_event.id = original_event.id
SET current_event.latitud = original_event.longitud,
    current_event.longitud = original_event.latitud,
    current_event.sgc_metadata = JSON_SET(IF(JSON_VALID(original_event.sgc_metadata), original_event.sgc_metadata, '{}'),
        '$.coordinate_order', 'lat-lon-v2',
        '$.coordinate_repair', JSON_OBJECT('from', 'legacy-usgs-lon-lat',
            'previous_latitude', original_event.latitud, 'previous_longitude', original_event.longitud,
            'previous_metadata', original_event.sgc_metadata, 'at_utc', UTC_TIMESTAMP()))
WHERE current_event.fuente = 'USGS'
  AND current_event.latitud = original_event.latitud AND current_event.longitud = original_event.longitud
  AND current_event.sgc_metadata <=> original_event.sgc_metadata;
DROP TEMPORARY TABLE sigra_usgs_coordinate_repair;
COMMIT;
