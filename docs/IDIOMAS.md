# Idiomas de la documentación

El idioma principal de la documentación de SIGRA es el **español**. GitHub muestra
`README.md` al abrir el repositorio; `README.es.md` conserva la ruta española
anterior y contiene el mismo texto para no romper enlaces existentes.

| Idioma | Documento | Alcance |
|---|---|---|
| Español | [README.md](../README.md) | Documento principal y guías técnicas. |
| Inglés | [README.en.md](../README.en.md) | README completo. |
| Portugués | [README.pt.md](../README.pt.md) | Presentación, instalación, configuración y operación inicial. |
| Francés | [README.fr.md](../README.fr.md) | Presentación, instalación, configuración y operación inicial. |
| Alemán | [README.de.md](../README.de.md) | Presentación, instalación, configuración y operación inicial. |
| Italiano | [README.it.md](../README.it.md) | Presentación, instalación, configuración y operación inicial. |
| Japonés | [README.ja.md](../README.ja.md) | Presentación, instalación, configuración y operación inicial. |
| Chino simplificado | [README.zh-CN.md](../README.zh-CN.md) | Presentación, instalación, configuración y operación inicial. |
| Árabe | [README.ar.md](../README.ar.md) | Mismo alcance, con dirección de lectura RTL. |

Las siete guías de entrada resumen la documentación principal y enlazan los
procedimientos técnicos en español. No son traducciones completas de `docs/`.
Los comandos, rutas, claves de configuración y contratos API mantienen sus nombres.
La interfaz de la aplicación sigue disponible en español e inglés; añadir un
idioma al README no activa un idioma nuevo en la web o en Android.

## Mantenimiento

1. Actualiza primero el README español y conserva idéntico `README.es.md`.
2. Mantén la traducción inglesa y las siete guías coherentes con la versión,
   el build, los requisitos y el procedimiento de instalación.
3. Conserva los avisos sobre el carácter experimental, los límites de los datos
   y el alcance de las comprobaciones. Una traducción no amplía esas garantías.
4. Verifica los enlaces relativos y que comandos y rutas sigan siendo válidos.
5. Mantén el historial principal en `CHANGELOG.md` en español; el historial inglés
   se conserva en `CHANGELOG.en.md`. Los avisos y textos originales de licencias
   mantienen sus atribuciones.

## Por qué un push no cambia la portada de GitHub

GitHub abre la rama predeterminada, `main`. Un commit publicado en otra rama no
cambia esa portada hasta que se integra mediante un pull request. El commit
`b249ba4` recuperó SGC y se integró en main; `0a4170f` actualizó después la versión
a 1.3.1.3 en la rama del hotfix. Esta entrega incluye esa revisión de versión y
la documentación en español para integrarlas en main juntas.
