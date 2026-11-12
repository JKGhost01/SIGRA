# Separación y primera actualización de Android

Destino previsto: `/home/azyron/Proyectos/sigra-mobile`.
La app sigue temporalmente en `mobile/`: el ejecutor solo permite escribir
dentro de SIGRA y `/tmp`, y el intento de traslado devolvió sistema de archivos
de solo lectura en el directorio padre. No se borró la carpeta original.

Desde una terminal normal en la carpeta de SIGRA:

```bash
bash tools/separate-mobile.sh
```

El script mueve la carpeta completa, sin sobrescribir un destino existente,
y después actualiza las referencias en README/guía/documentación activa y los
patrones de `.gitignore`. Se puede inspeccionar con `--dry-run`. Tras moverla,
abre `sigra-mobile` como proyecto independiente para desarrollar allí.

La primera actualización está documentada en `mobile/README.md`: contrato
API 1.1.1, filtros globales, distancias nulas, conteos ciudadanos, permisos y
corrección del registro automático después de abandonar la red. No se cambia
la versión del backend ni se crea un endpoint para publicar reportes sin
validación.

Se comprobó la sintaxis del script de separación y su destino con dry-run.
El wrapper de Gradle se dejó ejecutable para Linux. La comprobación
`bash mobile/gradlew --version` confirma que falta Java en este entorno; no
se pudo compilar, ejecutar las pruebas Kotlin ni validar el APK.
