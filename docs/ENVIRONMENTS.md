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

## Production

Solo desplegar commits que hayan pasado CI y, cuando exista staging operativo, la validación de staging. Las credenciales viven fuera de GitHub.

## Promoción recomendada

`development -> pull request/CI -> staging -> aprobación -> production`

La rama `main` representa código aceptado, pero hacer merge no debe confundirse con desplegar automáticamente al VPS.

## Datos

- Nunca copiar dumps de producción al repositorio.
- No usar producción como entorno de prueba.
- Antes de migraciones de producción debe existir un backup recuperable.
- Las pruebas con escritura deben usar datos aislados.
