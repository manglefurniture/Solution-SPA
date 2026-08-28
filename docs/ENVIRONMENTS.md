# Entornos — Solution SPA

## Development

Uso local. Puede usar `backend/config.php` creado desde `backend/config.example.php`. No usar datos ni credenciales reales de producción.

## Staging

Debe tener base de datos y configuración separadas de producción. Usar datos ficticios o anonimizados. Aquí se prueban migraciones, permisos, agenda, pagos y despliegue antes de promover un commit.

Variables recomendadas:

- `APP_ENV=staging`
- `APP_URL`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- `PHONE_DEFAULT_COUNTRY`
- `RATE_LIMIT_DIR` para un directorio privado y escribible por el proceso PHP
- `AUDIT_RETENTION_DAYS` (180 por defecto; rango permitido 30–3650)

`APP_URL` debe contener el origen público completo del entorno, incluyendo un subdirectorio si la aplicación vive bajo uno, y sin `/` final. Ejemplos: `https://staging.example.com` o `https://example.com/solution-spa`.

Durante el despliegue, `deployment/render-public-origin.php` deriva de `APP_URL` el canonical, `og:url`, la referencia al sitemap en `robots.txt` y los `<loc>` de `sitemap.xml`. El origen de GitHub Pages que vive en el repositorio es únicamente el valor base de la demo estática y no debe quedar publicado como canonical de staging o producción. El rollback vuelve a ejecutar el mismo render después del `git reset`, por lo que tampoco puede restaurar accidentalmente el canonical de la demo.

## Production

Solo desplegar commits que hayan pasado CI y, cuando exista staging operativo, la validación de staging. Las credenciales viven fuera de GitHub.

Antes de declarar el despliegue correcto, verificar que el origen SEO renderizado coincide con el `APP_URL` real del entorno. El preflight solo admite cambios locales en `index.html`, `privacy.html`, `robots.txt` y `sitemap.xml` cuando son exactamente el render determinista esperado para ese `APP_URL`; cualquier otra modificación sigue bloqueando el despliegue.

El preflight también comprueba que `RATE_LIMIT_DIR` pueda crearse/escribirse. Si el almacenamiento del rate limiter falla en runtime, login y formularios protegidos fallan de forma segura con 503 en lugar de continuar sin limitación.

### Auditoría y minimización

- Los eventos de auditoría no conservan en claro nombre, correo, teléfono, notas, referencias ni otros campos personales definidos por la política; se sustituyen por `[MINIMIZED]`.
- Secretos, contraseñas y tokens se sustituyen por `[REDACTED]`.
- Las direcciones IPv4 se reducen a `/24` y las IPv6 a `/64` antes de persistirse.
- `AUDIT_RETENTION_DAYS` controla la retención. Cada escritura de auditoría poda eventos vencidos y también puede ejecutarse explícitamente `php database/prune_audit.php`.
- Pagos y cambios de usuarios/roles usan auditoría obligatoria dentro de la misma transacción: si el evento no puede persistirse, el cambio se revierte.

## Promoción recomendada

`development -> pull request/CI -> staging -> aprobación -> production`

La rama `main` representa código aceptado, pero hacer merge no debe confundirse con desplegar automáticamente al VPS.

## Datos

- Nunca copiar dumps de producción al repositorio.
- No usar producción como entorno de prueba.
- Antes de migraciones de producción debe existir un backup recuperable.
- Las pruebas con escritura deben usar datos aislados.
