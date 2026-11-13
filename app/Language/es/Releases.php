<?php

return array (
  'history' => 
  array (
        '1.3.1' => [
            'name' => 'Cabeceras HTTP y recuperación de fuentes',
            'summary' => 'Lectura de cabeceras Cache-Control repetidas sin rechazar respuestas válidas de los proveedores.',
            'changes' => [
                'Lectura de cabeceras Cache-Control repetidas sin rechazar respuestas válidas de los proveedores.',
                'Respuesta HTTP entrante sin las cabeceras predeterminadas de páginas HTML de CodeIgniter.',
                'Retry-After múltiple respeta la espera más larga; validadores ETag y Last-Modified contradictorios no se reutilizan.',
                'Rutas con métodos GET/POST en mayúsculas para eliminar avisos deprecados repetidos.',
                'Pruebas con respuestas reales de CodeIgniter para cabeceras múltiples, 304 y no-store; pausas y límites conservados.',
            ],
        ],
        '1.3.0' => [
            'name' => 'Fuentes globales, consultas responsables y mapa corregido',
            'summary' => 'Seis fuentes oficiales, protección persistente de proveedores y reparación de las coordenadas USGS del mapa y el histórico.',
            'changes' => [
                'EMSC, GeoNet, GEOFON/GFZ e INGV integrados con SGC y USGS en catálogo, API y filtros; referencias conservadas al correlacionar.',
                'Consultas compartidas entre mapa y cron, mínimo de cinco minutos por proveedor, caché condicional y pausas persistentes ante 403/429.',
                'Respeto de Retry-After y Cache-Control, espera progresiva ante fallos e identificación pública de SIGRA; USGS usa su feed estático cuando es compatible.',
                'Latitud y longitud USGS corregidas; migración idempotente y SQL alternativo para reparar datos antiguos conservando auditoría.',
                'Validación de coordenadas al dibujar y límites verticales del mapa; estado degradado por fuente y traducciones ES/EN actualizados.',
                'Regresiones de normalización, seis fuentes, pausas HTTP, reparación MySQL y proyección Leaflet en navegador.',
            ],
        ],
        '1.2.1' => [
            'name' => 'Instalación reproducible y robustez del backend',
            'summary' => 'Instalación Composer reproducible, registro seguro de dispositivos, proveedores validados y estado de actualización visible.',
            'changes' => [
                'Identidad y autoload Composer corregidos, lock compatible con PHP 8.2 e instalación documentada desde cero.',
                'Generación local de secretos sin exponer valores ni reemplazar claves existentes; reinicio remoto por HTTP retirado.',
                'Registro de UUID existente protegido con el token vigente; rotación autenticada que invalida el anterior.',
                'Validación de campos y enlaces externos, verificación TLS, reintentos acotados y umbral de magnitud en la correlación.',
                'Estado de actualización del catálogo visible, assets compatibles con ambas raíces web y APIs fuera de la caché PWA.',
                'Documentación ES/EN coherente, Android separado y experimentalidad explícita; PHPStan y pruebas MySQL aisladas en CI.',
            ],
        ],
    '1.2.0' => [
        'name' => 'Arquitectura modular y sincronización automática',
        'summary' => 'Monolito modular con casos de uso, vistas de presentación, auditoría y actualización automática de fuentes desde el mapa sin cron obligatorio.',
        'changes' => [
            'Módulos por capacidad: catálogo, ingesta, reportes ciudadanos, sensores, alertas y sistema, con contratos y dependencias explícitos.',
            'Controladores ligeros y vistas de presentación; consulta y procesamiento fuera de Views, con JavaScript del catálogo en archivos públicos.',
            'Actualización automática del mapa con caché compartida de 60 segundos, bloqueo de concurrencia y conservación de datos ante fallos; cron opcional.',
            'Rutas antiguas conservadas, endpoints /api/v1, errores versionados, IDs de correlación y contrato OpenAPI.',
            'Auditoría de lotes y decisiones de alerta en dos tablas nuevas, con alerta y decisión en la misma transacción antes de la entrega.',
            'Simulaciones aisladas, interruptor de publicación y cancelación de alertas desde la consola del servidor.',
            'Correlación SGC/USGS con referencias de ambas fuentes y conservación de candidatos ambiguos.',
            'Pruebas de arquitectura, contratos HTTP, actualización automática y persistencia en MySQL aislado, además de documentación de despliegue.',
        ],
    ],
    '1.1.1' => [
            'name' => 'Reportes contrastados con SGC',
            'summary' => 'Los reportes ciudadanos permanecen privados y solo aportan un conteo a un sismo oficial del SGC tras alcanzar el umbral de participantes.',
            'changes' => [
                'Una sola tabla citizen_reports en la misma base MySQL; los reportes no crean eventos sísmicos.',
                'Mínimo de tres participantes distintos y coincidencia única con SGC en tiempo y distancia; casos ambiguos siguen pendientes.',
                'Conteo “Reportado por N usuarios” en mapa, listado, histórico, detalle y API, separado del dato oficial de reportes sentidos.',
                'CSRF, desafío de sesión de un solo uso, campo trampa y límites de frecuencia por sesión y red.',
                'Identificadores HMAC, deduplicación por red e IPv6 /64 y retención acotada de datos privados.',
                'Reportes antiguos ocultos en las consultas públicas, sin borrarlos ni contarlos como participantes identificados.',
                'Edición y eliminación públicas bloqueadas para impedir cambios sin autenticación en eventos oficiales.',
                'Pruebas de lógica y de protección HTTP; prueba MySQL con datos temporales revertidos en la base existente.',
            ],
        ],
        '1.1.0' => [
            'name' => 'API y contratos',
            'summary' => 'Validación de entradas y respuestas JSON coherentes en la API de sismos y de la red ciudadana.',
            'changes' => [
                'Validación de coordenadas, radio, orden y parámetros numéricos antes de consultar datos.',
                'Validación del cuerpo JSON y normalización de booleanos en las solicitudes de dispositivos.',
                'Respuestas JSON y Cache-Control no-store también en errores de autenticación y persistencia.',
                'Cabeceras X-SIGRA-Version y X-API-Version en las respuestas de ambas APIs.',
                'Comparación de claves con hash_equals y rechazo de rutas protegidas sin clave configurada en producción.',
                'Tokens de dispositivo opcionales inválidos devuelven 401 en lugar de tratarse como anónimos.',
                'Rutas y campos de Android conservados; el contrato de la API sigue en v1.',
                'Documentación de ambas APIs y pruebas de rutas CGI y respuestas con persistencia aislada.',
            ],
        ],
        '1.0.4' => array (
  'name' => 'Filtros y presentación',
  'summary' => 'Correcciones del filtro por distancia, traducción del historial e iconos vectoriales.',
  'changes' => 
  array (
    0 => 'Selector de radio disponible desde el inicio y solicitud de ubicación al elegir una distancia.',
    1 => 'Tiempo límite y reintento de geolocalización por IP; preferencias utilizables sin almacenamiento local.',
    2 => 'Traducción completa del historial y del canal de versiones en español e inglés.',
    3 => 'Sustitución de emojis de la interfaz por iconos SVG locales.',
    4 => 'Renovación de caché y actualización por red de scripts y estilos.',
    5 => 'Pruebas de regresión del filtro de distancia sin navegador ni MySQL.',
  ),
),
        '1.0.3' => 
    array (
      'name' => 'Alcance global',
      'summary' => 'SIGRA deja de ser solo Colombia: unifica SGC y USGS en un único mapa mundial con filtro por radio desde la ubicación del usuario.',
      'changes' => 
      array (
        0 => 'Ingesta unificada de SGC (Colombia) y USGS (mundo) en la misma tabla y el mismo mapa.',
        1 => 'Deduplicación automática entre fuentes para no mostrar dos veces el mismo sismo.',
        2 => 'Filtro “Cerca de mí” con radio configurable (50 km a 5.000 km) y orden por distancia.',
        3 => 'Filtro por fuente y búsqueda por lugar en todo el mundo.',
        4 => 'Filtro por rango de magnitud (M2.5+ a M6+) y panel de filtros compacto y plegable.',
        5 => 'Interfaz bilingüe español/inglés con selector en el navbar y traducción automática.',
        6 => 'Nuevos iconos y colores para “Reportar sismo” y las alertas del navegador.',
        7 => 'Avisos legales de terceros en LICENSE y THIRD_PARTY_NOTICES.md.',
        8 => 'Sincronización global con caché en disco y comando `php spark sigra:sync` para cron.',
        9 => 'API `/api/sismos/recent` con lat, lng, radius_km y order=distancia.',
        10 => 'Ficha técnica adaptada a USGS (alerta, tsunami, significancia y reporte oficial).',
        11 => 'Preparación open source: README, licencia, guía de contribución, seguridad y .env de ejemplo.',
      ),
    ),
    '1.0.2' => 
    array (
      'name' => 'Red en vivo',
      'summary' => 'La red ciudadana pasa al navbar, se eliminan los SMS y llegan las alertas del navegador, la PWA y la propagación animada de ondas.',
      'changes' => 
      array (
        0 => '“Red en vivo” como entrada principal del navbar, con contador de teléfonos y estado de alerta.',
        1 => 'Alertas del navegador con Web Notifications, sonido y overlay de emergencia con cuenta atrás hasta la onda S.',
        2 => 'PWA instalable: manifest, service worker, iconos y página offline.',
        3 => 'Propagación animada de ondas P y S sobre el mapa de /red.',
        4 => 'Panel “Tu riesgo ahora” con distancia, ETA y nivel de riesgo usando la ubicación solo en el navegador.',
        5 => 'Reproducción animada de la actividad sísmica en /sismos.',
        6 => 'Alertas compartibles desde /red con Web Share API o portapapeles.',
        7 => 'Eliminación completa de SMS, OTP, Twilio y la tabla sms_subscriptions.',
        8 => 'Endurecimiento de .htaccess, health check con versión y documentación de pruebas.',
      ),
    ),
    '1.0.1' => 
    array (
      'name' => 'Red de teléfonos',
      'summary' => 'Primera versión de la red ciudadana de ondas P y de la app Android.',
      'changes' => 
      array (
        0 => 'Backend de red: dispositivos, detecciones, alertas y entregas.',
        1 => 'Fusión TDOA, estimación de magnitud, confianza y ETA de onda S.',
        2 => 'API /api/red/* para registro, latido, detección y alertas.',
        3 => 'Panel público /red con estadísticas y mapa de la red.',
        4 => 'App Android con servicio en primer plano, sensor y notificaciones.',
        5 => 'FCM opcional y polling de respaldo.',
      ),
    ),
    '1.0.0' => 
    array (
      'name' => 'Mapa sísmico',
      'summary' => 'Primera versión pública del visor de eventos del SGC.',
      'changes' => 
      array (
        0 => 'Mapa interactivo con Leaflet y OpenStreetMap.',
        1 => 'Sincronización con el feed oficial del SGC.',
        2 => 'Histórico consultable, filtros y ficha técnica del evento.',
        3 => 'Reporte ciudadano de ubicación.',
        4 => 'Temas claro/oscuro y alertas visuales y sonoras.',
      ),
    ),
  ),
);
