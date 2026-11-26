<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\ViewModels;

/** Operational policies shared by the website and the offline Android legal reader. */
final class LegalContent
{
    public static function documents(string $locale): array
    {
        if ($locale === 'en') {
            return self::english();
        }
        return [
            'privacy' => ['title' => 'Política de privacidad', 'sections' => [
                ['Consulta y funciones opcionales', [
                    'Puedes consultar sismos sin crear una cuenta ni participar como sensor. El servidor y los proveedores de mapas reciben datos técnicos de conexión, como dirección IP y agente de usuario. Navegar no equivale a autorizar el envío de ubicación, sensores o reportes.',
                    'La ubicación se solicita para distancias y filtros. La web la conserva en el navegador cuando activas sus avisos; las consultas de la app pueden enviar coordenadas al backend por HTTPS. No se obtiene ubicación por IP como sustitución silenciosa de un permiso denegado.',
                ]],
                ['App Android, sensores y notificaciones', [
                    'Los avisos del catálogo consultan sismos publicados con el radio y magnitud que eliges. Se guarda un conjunto acotado de identificadores vistos para evitar avisos repetidos. La primera consulta establece una referencia y no notifica todo el histórico.',
                    'Participar en la red ciudadana es una opción separada: se registra un identificador aleatorio, un token de autenticación, modelo y versión del sistema y app, ubicación, nivel de batería y estado del monitoreo. Las detecciones transmiten hora, coordenadas y características calculadas de la aceleración. No se graba audio ni se solicitan contactos, SMS o fotografías.',
                    'Android solicita sus permisos por separado. La autorización de tratamiento no se presume por conceder un permiso. El servicio de avisos muestra una notificación persistente y puede detenerse. El sistema, el ahorro de energía, la conexión o un cierre forzado pueden retrasar o impedir avisos.',
                    'Firebase Cloud Messaging es opcional y solo funciona si el operador lo configura. En ese caso Google recibe el identificador de instalación, token y datos técnicos necesarios para entregar mensajes; no se promete push si esa configuración falta.',
                ]],
                ['Reportes y datos públicos', [
                    'Los reportes ciudadanos contienen ubicación, hora, intensidad y referencia del sismo. Permanecen privados durante la conciliación; la interfaz publica conteos agregados confirmados. No incluyas nombres, teléfonos, salud u otros datos sensibles en el texto libre.',
                    'El control de abuso utiliza identificadores de sesión y red protegidos con HMAC. Las tablas de reportes no conservan la IP original para ese control; esto no significa que el proveedor de hosting carezca de registros técnicos. Los mapas de la red muestran posiciones redondeadas y no nombres de propietarios.',
                ]],
                ['Destinatarios, seguridad y conservación', [
                    'El hosting procesa las solicitudes del servicio. El navegador y la app contactan proveedores como OpenStreetMap, Esri, OpenTopoMap y GEM al utilizar sus capas. La web también carga recursos de CDN y tipografías de Google. Esos proveedores reciben datos de conexión y pueden operar fuera de Colombia. Sus políticas y licencias se aplican a sus propios servicios; SIGRA no controla sus registros.',
                    'Los reportes tienen una retención configurada inicialmente en 30 días; detecciones, entregas y alertas expiradas de la red, 7 días. La eliminación depende de ejecutar los procesos de limpieza configurados. El catálogo público se conserva inicialmente hasta 730 días. Los registros de dispositivos se mantienen para autenticar y gestionar la participación hasta su eliminación conforme a una solicitud válida u obligación aplicable.',
                    'Los plazos de logs y respaldos del hosting deben ser definidos por su operador; no se afirma una eliminación inmediata en copias de seguridad. Se usan HTTPS, tokens y controles de acceso, sin garantizar seguridad absoluta. No publiques tokens o claves en solicitudes de soporte.',
                ]],
                ['Control y derechos', [
                    'Puedes desactivar avisos y sensores por separado, retirar permisos desde Android o el navegador y borrar el almacenamiento local. Borrar la app o su caché no elimina automáticamente registros del servidor. Para acceso, corrección, revocación o supresión utiliza el canal del responsable que figura en esta página y la política de tratamiento.',
                    'SIGRA no se dirige a recopilar datos de menores ni datos sensibles. Si detectas un tratamiento no autorizado, solicita su revisión. Las nuevas finalidades que requieran autorización deben informarse antes de aplicarse; continuar navegando no reemplaza esa autorización.',
                ]],
            ]],
            'processing' => ['title' => 'Política de tratamiento de datos personales', 'sections' => [
                ['Marco, responsable y alcance', [
                    'Esta política cubre el tratamiento realizado por SIGRA web y Android, con referencia al régimen colombiano de protección de datos personales, Ley 1581 de 2012 y su reglamentación compilada en el Decreto 1074 de 2015. La identidad y el canal de atención del responsable se muestran al final. No constituye una certificación de cumplimiento.',
                    'Las finalidades son prestar la consulta sísmica, calcular cercanía, entregar avisos solicitados, administrar la participación voluntaria como sensor, contrastar reportes, prevenir abuso y atender derechos. No se solicita autorización para publicidad, comercialización de datos o finalidades indeterminadas.',
                ]],
                ['Autorización y retirada', [
                    'Antes de enviar ubicación, sensores o reportes se debe explicar qué se recoge, para qué y cómo retirar la autorización. La participación como sensor requiere una decisión afirmativa separada de la consulta y de los avisos. La app conserva localmente la versión del aviso aceptado; los permisos del sistema no sustituyen esa decisión.',
                    'Puedes dejar de participar y detener la recolección futura desde Ajustes. La revocación y supresión están sujetas a las obligaciones legales o contractuales válidamente aplicables; no se exige mantener sensores activos para consultar el catálogo. La retirada no convierte en ilícito el tratamiento anterior autorizado.',
                ]],
                ['Derechos del titular y procedimiento', [
                    'Puedes conocer, actualizar y rectificar datos; solicitar prueba de autorización e información sobre su uso; revocar la autorización o pedir supresión cuando proceda; acceder gratuitamente a tus datos y acudir a la Superintendencia de Industria y Comercio conforme a los requisitos legales.',
                    'Envía una solicitud al canal del responsable indicando el derecho que deseas ejercer y una referencia que permita localizar el registro, por ejemplo identificador del dispositivo o reporte. La identidad se verifica de forma proporcional: no envíes contraseñas, tokens ni documentos completos sin que se justifique un mecanismo seguro.',
                    'Las consultas se atienden dentro de 10 días hábiles desde su recepción; si es necesario se comunica la razón y una ampliación de hasta 5 días hábiles. Los reclamos tienen un plazo de 15 días hábiles desde el día siguiente al recibo y, con información al interesado, una ampliación de hasta 8 días hábiles. Se aplican las reglas legales de solicitudes incompletas, competencia y reclamación previa.',
                ]],
                ['Retención, proveedores y vigencia', [
                    'Las categorías de datos, destinatarios, transferencias técnicas y plazos operativos están descritos en la política de privacidad. La existencia de copias de seguridad o deberes de conservación debe explicarse al atender una solicitud, sin prometer borrados que no se hayan verificado.',
                    'La versión y fecha del aviso se muestran en esta página. Los cambios relevantes se informarán de forma visible y se obtendrá una nueva autorización cuando cambien las finalidades que la requieran. El operador debe completar su identificación, canal de derechos y procedimientos antes de habilitar tratamientos personales.',
                ]],
            ]],
            'cookies' => ['title' => 'Cookies y almacenamiento del dispositivo', 'sections' => [
                ['Qué se utiliza', [
                    'SIGRA no incorpora analítica publicitaria ni cookies de anuncios en su código. Se usan sesión y protección CSRF para funciones que las necesitan, y almacenamiento local para tema, idioma, capa, ancho del panel, radio, avisos vistos y preferencias de notificaciones. No se guarda la posición de la cámara del mapa.',
                    'El service worker guarda archivos estáticos y la pantalla sin conexión. Las API y consultas de actualización no se sirven desde esa caché. El navegador puede mantener además su propia caché HTTP. Cerrar una pestaña no borra necesariamente esos datos.',
                ]],
                ['Decisiones y duración', [
                    'El aviso sobre almacenamiento técnico es informativo: el botón Entendido cierra el aviso y no autoriza publicidad ni tratamientos adicionales. Las preferencias de localStorage permanecen hasta borrarlas; las cookies de sesión siguen la configuración del servidor y navegador. La política de tratamiento regula los datos que llegan al servidor.',
                    'Puedes borrar los datos del sitio, retirar permisos y desinstalar la PWA desde el navegador. Se perderán preferencias y registros locales de avisos; no se borra automáticamente el servidor. Si se añaden tecnologías no necesarias, deberán permanecer desactivadas hasta la decisión aplicable del usuario y ofrecer retirada de esa decisión.',
                ]],
                ['Android y terceros', [
                    'La app nativa usa DataStore para ajustes, consentimientos, tokens e historial acotado, y osmdroid para caché de teselas. Esto no es una cookie del navegador. Los respaldos automáticos de datos de la app están desactivados. Desinstalarla o borrar sus datos elimina el almacenamiento local, con las limitaciones que imponga el sistema.',
                    'Abrir enlaces externos usa el navegador elegido, con sus propias cookies. Los proveedores de mapas, fuentes y CDN reciben las conexiones necesarias para servir recursos; no se debe describirlos como si no recibieran datos técnicos. Consulta también la política de privacidad.',
                ]],
            ]],
            'terms' => ['title' => 'Términos de uso', 'sections' => [
                ['Naturaleza del servicio', [
                    'SIGRA permite consultar publicaciones sísmicas y una red ciudadana experimental. No es una autoridad, un sistema certificado de alerta temprana ni un servicio de emergencia. Los registros publicados no predicen terremotos; las estimaciones de la red pueden ser erróneas, tardías o inexistentes. No esperes una notificación si percibes un sismo: sigue a las autoridades.',
                    'La capa GEM representa amenaza sísmica a largo plazo, no frecuencia reciente ni riesgo individual de un edificio. Las fuentes y condiciones científicas están indicadas en el mapa. No sustituye estudios de suelo, normas de construcción ni asesoría profesional.',
                ]],
                ['Disponibilidad y uso responsable', [
                    'Puede haber interrupciones, revisiones científicas, errores de catálogo y diferencias entre agencias. Android, el fabricante, el estado de los permisos, la batería y la conectividad condicionan los avisos. Un servicio en primer plano no garantiza funcionamiento ininterrumpido ni entrega inmediata.',
                    'No presentes simulaciones como eventos reales, no envíes reportes fraudulentos y no intentes acceder a registros ajenos. Respeta límites de consulta y licencias; no utilices la API para extraer datos personales ni para eludir medidas de seguridad. El operador puede limitar el abuso de forma proporcionada.',
                ]],
                ['Licencias, datos y derechos', [
                    'El código y los datos no comparten necesariamente licencia. Se deben respetar los créditos de las agencias y proveedores. En particular, las teselas GEM v2026.1 se ofrecen bajo CC BY-NC-SA 4.0; la licencia del código de SIGRA no concede usos adicionales sobre esas teselas.',
                    'No hay un precio de suscripción establecido por estas condiciones. Las aportaciones voluntarias no compran garantías de aviso ni privilegios sobre derechos de datos. Las normas imperativas y los derechos del usuario prevalecen; estas condiciones no excluyen responsabilidades que legalmente no puedan excluirse.',
                    'La protección de datos se rige por las políticas de privacidad y tratamiento, con autorización separada para las funciones opcionales. Los cambios sustanciales se identificarán por versión y fecha. Las consultas operativas y reclamaciones se tramitan mediante los datos del responsable publicados aquí.',
                ]],
            ]],
        ];
    }

    private static function english(): array
    {
        return [
            'privacy' => ['title' => 'Privacy policy', 'sections' => [
                ['Browsing and optional features', ['You can browse earthquakes without creating an account or contributing sensor data. Hosting, map, font and CDN providers receive technical connection data such as IP address and user agent. Browser permissions do not replace informed authorization for personal data processing.', 'Location supports distance filters and nearby notifications. Android requests may send coordinates to the SIGRA backend over HTTPS. Denied location access is not silently replaced by IP geolocation.']],
                ['Android and sensors', ['Catalog notifications are separate from voluntary sensor participation. A visible service checks published events using your chosen radius and magnitude; the initial fetch establishes a baseline instead of notifying the entire history. Android and network restrictions can delay or prevent delivery.', 'Sensor participation can send a random device identifier, authentication token, app/device information, location, battery status and calculated acceleration features with timestamps. No microphone, contacts, SMS or photos are requested. Firebase delivery is optional and requires operator configuration; Google then processes installation identifiers and technical delivery data.']],
                ['Reports, providers and retention', ['Citizen reports contain location, time, intensity and a reference. They remain private during reconciliation; only confirmed aggregate counts are displayed. Anti-abuse identifiers use HMAC; this does not imply the hosting provider has no IP logs.', 'Initial configured retention is 30 days for reports, 7 days for expired sensor detections, deliveries and alerts, and 730 days for the public catalog; deletion depends on scheduled cleanup. Device records remain for participation management until applicable deletion. The operator must specify hosting log and backup retention.', 'OpenStreetMap, Esri, OpenTopoMap, GEM, font/CDN services and optional Firebase receive technical connections and may process data abroad. Their own policies apply. HTTPS and access controls reduce risks without guaranteeing absolute security.']],
                ['Control and rights', ['You may stop notifications and sensors separately, withdraw permissions and clear local storage. Removing the app does not automatically delete server records. Use the controller contact and data processing policy to request access, correction, withdrawal or deletion. Do not include sensitive information in free text. The service is not directed at collecting children’s data.']],
            ]],
            'processing' => ['title' => 'Personal data processing policy', 'sections' => [
                ['Scope and purpose', ['This policy covers SIGRA web and Android with reference to Colombian Law 1581 of 2012 and Decree 1074 of 2015. Controller details appear below. It is not a compliance certification.', 'Purposes include browsing seismic information, distance filtering, requested notifications, voluntary sensor participation, report reconciliation, abuse prevention and data rights. It does not request authorization for advertising or unrestricted reuse.']],
                ['Authorization and rights', ['Optional data collection requires prior informed affirmative authorization. Sensor participation is separate from catalog browsing and notifications. The app records the accepted notice version locally; Android permissions alone are insufficient. Participation can be withdrawn in Settings.', 'You may access, update and rectify data, request proof of authorization and information about processing, and seek withdrawal or deletion where applicable. Contact the controller with a proportionate reference to your device or report; never send passwords or device tokens.']],
                ['Requests and policy changes', ['Under the referenced Colombian procedure, access inquiries normally have a 10 working day deadline with a reasoned extension of up to 5; complaints have a 15 working day deadline, counted from the following day, with a reasoned extension of up to 8. Statutory completeness and prior complaint requirements apply.', 'Categories, technical providers and operational retention are described in the privacy policy. Local deletion does not erase server records or verified backup obligations. Material purpose changes require the appropriate new authorization. Controller contact and operational procedures must be completed before enabling personal data processing.']],
            ]],
            'cookies' => ['title' => 'Cookies and device storage', 'sections' => [
                ['Technical storage', ['SIGRA code includes no advertising analytics. Session/CSRF controls and local preferences support theme, language, map layers, radius, panel width and seen notifications. Camera coordinates are not persisted. The service worker stores static resources, not dynamic API responses.', 'The acknowledgment button closes the technical storage notice; it is not consent to advertising or additional processing. Local preferences remain until cleared. Session cookies follow browser/server configuration. Any future nonessential technologies require the applicable user choice before activation.']],
                ['Android and third parties', ['The native app uses DataStore for settings, consent, tokens and bounded alert history, and osmdroid for map tile caching. Automatic app data backups are disabled. Clearing app/site data removes local preferences but not server records.', 'External links open your chosen browser with its own cookies. Map, font and CDN providers receive technical connections; optional Firebase delivery has separate processing. Review the privacy policy.']],
            ]],
            'terms' => ['title' => 'Terms of use', 'sections' => [
                ['Informational service', ['SIGRA is an informational seismic catalog and experimental citizen network, not an official authority or certified emergency warning service. Published earthquakes are not predictions. Alerts may be late, inaccurate or absent; follow official instructions and never wait for an app when you feel shaking.', 'GEM colors describe long-term seismic hazard, not recent frequency or individual building risk. They do not replace geotechnical studies, building codes or professional advice.']],
                ['Availability and responsible use', ['Service interruptions, scientific revisions, device power management and permission/network restrictions can prevent delivery. A foreground service does not guarantee uninterrupted operation. Do not submit fraudulent reports, represent simulations as real events or access other users’ data. Respect rate limits and source licenses.', 'Code and data licenses differ. GEM v2026.1 tiles use CC BY-NC-SA 4.0; the SIGRA code license grants no additional rights to those tiles. Voluntary donations do not buy notification guarantees. Mandatory rights and liabilities that cannot legally be excluded remain unaffected. Optional personal data collection requires separate authorization. Contact and version details appear below.']],
            ]],
        ];
    }
}
