# Publicación de la reestructuración en Git

La versión 1.2.1 incluye archivos nuevos, cambios de ubicación, adaptadores antiguos y
eliminaciones de vistas ejecutables. Estos cambios forman una actualización conjunta:
el commit debe incluir las rutas nuevas y las eliminaciones al mismo tiempo.

## Archivos que se versionan

- `app/Modules/`, `app/Shared/`, controladores/adaptadores, configuración y migraciones.
- Assets públicos, `sw.js`, traducciones e historial de versión.
- Pruebas, fixtures sintéticos, scripts de desarrollo y workflow de CI.
- Documentación de arquitectura, OpenAPI y los documentos de propuesta.
- `deploy/LEEME-SUBIDA.md` y `deploy/auditoria-modular.sql`: son instrucciones y
  esquema sin datos; no son una copia de la base de datos.
- `.env.example`, `env` y lockfiles de dependencias, que permiten reproducir la instalación.

## Archivos que se quedan locales

`.env`, credenciales, `vendor/`, `node_modules/`, logs, sesiones, cachés, archivos de
`writable/`, resultados de pruebas, respaldos y paquetes de `dist/`. Las copias de
manifiestos extraídas del ZIP bajo `docs/deployment/` también se ignoran. Los archivos
de protección y `.gitkeep` de `writable/` sí se conservan para crear su estructura.

```bash
python3 tools/check-git.py
```

El comprobador verifica las reglas incluso para rutas que todavía no existen, detecta
fuentes ocultas por error y archivos ignorados que ya estén versionados. `.gitignore`
no deja de seguir automáticamente un archivo que ya estuviera en un commit.

## Subir la actualización

Desde la raíz del proyecto, con `main` actualizado:

```bash
git status --short
git switch -c release/1.2.1
python3 tools/check-git.py
bash tools/test-architecture.sh
git add -A
git diff --cached --stat
git diff --cached --name-status
git commit -m "release: prepara SIGRA v1.2.1 con arquitectura modular y mejoras del backend"
git push -u origin release/1.2.1
```

Después abre un Pull Request de `release/1.2.1` hacia `main`. Revisa los
cambios y el resultado de CI antes de integrarlo. No se necesita `push --force`,
borrar el repositorio ni volver a mover archivos con `git mv`: `git add -A` registra
también las eliminaciones y Git puede reconocer las vistas trasladadas.

Los nombres marcados `D` son rutas antiguas retiradas; los módulos nuevos deben estar
incluidos en el mismo commit. Los marcados `??` todavía no están preparados para el
commit. Si solo se preparan modificaciones de archivos conocidos con `git add -u`,
los módulos nuevos quedarían fuera y el commit resultaría incompleto.

Publicar el repositorio y actualizar Hostinger son operaciones distintas. El ZIP de
hosting se genera con `python3 tools/build-upload.py` y queda fuera de Git. Sus pasos
de instalación y las dos tablas nuevas están documentados en `deploy/LEEME-SUBIDA.md`.
