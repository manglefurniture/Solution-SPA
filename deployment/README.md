# Despliegue verificable — Solution SPA

Este flujo adapta el patrón de Hache-Base al SPA sin guardar secretos en GitHub.

## Flujo

`CONTROLLER -> PRECHECK -> BACKUP -> FETCH/RESET -> RENDER APP_URL -> MIGRATIONS -> TESTS -> HEALTH -> DEPLOY_OK`

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
- `RATE_LIMIT_DIR` (recomendado; si se omite se usa el directorio temporal del sistema)
- `AUDIT_RETENTION_DAYS` (opcional, 180 por defecto)

No guardar estos valores reales en el repositorio.

`APP_URL` es la URL pública autoritativa del entorno, sin `/` final. El deploy la usa para renderizar canonical, `og:url`, `robots.txt` y `sitemap.xml`; así staging o producción nunca heredan como canonical la URL de la demo de GitHub Pages.

El render modifica únicamente `index.html`, `privacy.html`, `robots.txt` y `sitemap.xml` en el checkout desplegado. En la siguiente ejecución, el preflight acepta ese estado solo si coincide exactamente con el resultado determinista del origen previamente desplegado. Cualquier cambio adicional sigue haciendo fallar el precheck. Un cambio legítimo de `APP_URL` se aplica durante el siguiente deploy.

El preflight comprueba además que el almacenamiento de rate limiting sea escribible. En runtime, si ese almacenamiento deja de estar disponible, los endpoints protegidos fallan con 503 en lugar de continuar sin limitación.

## Controlador estable

`deployment/deploy.sh` delega en `deployment/release-controller.sh`. El controlador copia sus dependencias a un directorio temporal antes de cambiar el checkout, por lo que el flujo activo no desaparece durante `git reset --hard`.

Cuando se ejecuta un rollback, el controlador moderno, el preflight, el backup y el renderer se preservan además en `BACKUP_DIR/deployment-tooling`. Al terminar el rollback se instala un bootstrap mínimo en `deployment/deploy.sh`. De esta forma, el siguiente comando normal de despliegue usa el tooling preservado aunque el commit restaurado contenga scripts de despliegue históricos.

El controlador elimina/restaura ese bootstrap desde `HEAD` antes del preflight. Por tanto, un preflight antiguo estricto no bloquea el siguiente despliegue y el bootstrap no sobrevive al nuevo `git reset`.

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

También puede indicarse `TARGET_COMMIT`.

El rollback clasifica el target antes del reset:

- si el commit ya contiene el baseline SEO completo, vuelve a renderizar `APP_URL` usando el renderer preservado;
- si el commit es realmente pre-SEO, restaura el código histórico sin inventar archivos SEO modernos.

En ambos casos, el bootstrap de `deployment/deploy.sh` garantiza que el siguiente despliegue vuelva a utilizar el controlador moderno preservado. Así un rollback hacia un commit antiguo no puede obligar al siguiente deploy a usar un `deploy.sh`/`preflight.sh` obsoleto ni omitir el render SEO de la nueva revisión.

Restaurar base de datos es deliberadamente explícito y solo ocurre si se proporciona `RESTORE_DB_BACKUP=/ruta/database.sql.gz`.

## Auditoría

Los eventos vencidos se eliminan automáticamente según `AUDIT_RETENTION_DAYS`. Para una poda operativa explícita puede ejecutarse:

```bash
php database/prune_audit.php
```

Pagos y cambios de usuarios/roles requieren que su evento de auditoría se persista dentro de la misma transacción; si falla la auditoría, el cambio se revierte.

## Regla operativa

No ejecutar migraciones de producción sin backup recuperable. Un rollback de código no implica automáticamente rollback de datos; una restauración de MariaDB debe ser una decisión consciente según el cambio aplicado.
