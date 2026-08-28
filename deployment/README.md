# Despliegue verificable — Solution SPA

Este flujo adapta el patrón de Hache-Base al SPA sin guardar secretos en GitHub.

## Flujo

`PRECHECK -> BACKUP -> FETCH/RESET -> RENDER APP_URL -> MIGRATIONS -> TESTS -> HEALTH -> DEPLOY_OK`

El runner no se activa automáticamente desde GitHub porque producción debe configurar primero el VPS, variables/secretos y una política de aprobación.

## Variables del servidor

Como mínimo:

- `APP_DIR`
- `APP_URL`
- `BACKUP_DIR`
- `DB_HOST`
- `DB_PORT` (opcional, 3306 por defecto)
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DEPLOY_BRANCH` (opcional, `main` por defecto)

No guardar estos valores reales en el repositorio.

`APP_URL` es la URL pública autoritativa del entorno, sin `/` final. El deploy la usa para renderizar canonical, `og:url`, `robots.txt` y `sitemap.xml`; así staging o producción nunca heredan como canonical la URL de la demo de GitHub Pages.

El render modifica únicamente `index.html`, `privacy.html`, `robots.txt` y `sitemap.xml` en el checkout desplegado. En la siguiente ejecución, el preflight acepta ese estado solo si coincide exactamente con el resultado determinista del `APP_URL` actual. Cualquier cambio adicional sigue haciendo fallar el precheck.

## Desplegar

Desde el servidor, con las variables ya cargadas:

```bash
bash deployment/deploy.sh
```

Para fijar exactamente el commit que se quiere desplegar:

```bash
TARGET_COMMIT=<sha> bash deployment/deploy.sh
```

## Rollback

Por defecto vuelve al commit guardado justo antes del último despliegue:

```bash
bash deployment/rollback.sh
```

También puede indicarse `TARGET_COMMIT`. Restaurar base de datos es deliberadamente explícito y solo ocurre si se proporciona `RESTORE_DB_BACKUP=/ruta/database.sql.gz`.

## Regla operativa

No ejecutar migraciones de producción sin backup recuperable. Un rollback de código no implica automáticamente rollback de datos; una restauración de MariaDB debe ser una decisión consciente según el cambio aplicado.
