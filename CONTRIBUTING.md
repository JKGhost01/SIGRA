# Contribuir a SIGRA

Esta guía establece cómo preparar contribuciones claras y verificables. La documentación principal se escribe en **español**. El README inglés se conserva en [README.en.md](README.en.md); los nombres de archivos, claves de API y contratos de código se mantienen estables.

## Antes de empezar

- Revisa los issues y pull requests existentes.
- Para una función amplia, abre primero un issue para acordar el alcance.
- No publiques credenciales, telemetría privada ni datos personales en reportes públicos.

## Preparar el entorno

Sigue el [procedimiento de instalación](docs/INSTALLATION.md):

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
# Crea una base vacía y configura su conexión en .env.
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

CI4 se distribuye en `system/`; Composer instala las dependencias fijadas en el lock y PHPStan. Las comprobaciones requieren Node 22+, Python 3 y PHP-CGI. La integración MySQL usa una base de pruebas independiente seleccionada expresamente.

## Estilo y arquitectura

- PHP 8.2+ y `declare(strict_types=1);` en clases nuevas; sigue PSR-12.
- Respeta la [arquitectura modular](docs/architecture/README.md): HTTP recibe solicitudes; Application coordina casos de uso mediante contratos; Domain conserva reglas independientes de CI4; Infrastructure integra persistencia, proveedores y entregas; Presentation muestra datos preparados.
- Los nombres de compatibilidad en `app/Libraries` y `app/Models` delegan a módulos. El comportamiento nuevo pertenece al módulo correspondiente.
- Escapa datos externos con `esc()` en PHP y `escaparHtml()` en JavaScript.
- No incluyas credenciales, claves, teléfonos ni dominios de producción en código.
- Conserva JavaScript sin frameworks y compatible con navegadores actuales.

## Comprobaciones

```bash
python3 tools/check-git.py
composer test
composer analyse
php -l app/Ruta/Archivo.php
node --check public/assets/js/mapa-sismos.js
```

Comprueba manualmente mapa, histórico y detalle cuando cambie la interfaz. Un normalizador nuevo necesita fixtures para eventos válidos, campos científicos ausentes, conversión horaria, identificadores largos y duplicados entre fuentes.

## Commits y pull requests

- Escribe mensajes claros, por ejemplo: `Corrige el filtro de radio`.
- Mantén un cambio lógico por pull request y explica qué cambia, por qué y cómo se comprobó.
- Actualiza la documentación y `docs/VERSIONES.md` si cambia el comportamiento público.
- Cambia la versión cuando el mantenedor lo solicite.
- Incluye módulos nuevos y rutas retiradas en la misma entrega. Consulta la [guía de publicación](docs/architecture/git-publication.md).
- Un push a una rama no modifica `main`: abre un pull request hacia la rama principal y verifica sus comprobaciones antes de integrarlo.

## Reportar errores

Incluye la versión de SIGRA (`/api/health`), versiones de PHP y base de datos, navegador/sistema o dispositivo Android, pasos para reproducir, resultado esperado y observado, y fragmentos relevantes de `writable/logs/` sin secretos ni datos personales.
