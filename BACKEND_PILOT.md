# Solution SPA — backend PHP/MariaDB

El repositorio contiene un backend funcional para PHP 8.4 + MariaDB. GitHub Pages puede seguir mostrando el frontend estático; las rutas PHP funcionan al desplegar el proyecto en un servidor compatible.

## Incluido

- Base MariaDB `solution_spa`.
- Clientes, servicios, citas, tratamientos, pagos, usuarios, tokens de sesión persistente y solicitudes web.
- Conexión PDO con consultas preparadas.
- Autenticación por sesión y opción de recordar sesión.
- Roles `admin`, `operator` y `client` con permisos.
- Protección CSRF para mutaciones autenticadas.
- Rate limiting para formularios públicos.
- Portal/registro de cliente.
- Health check de MariaDB.
- Auditoría de mutaciones (`audit_events`).
- Normalización de teléfonos E.164.
- Migraciones versionadas, pruebas y CI.
- Scripts de backup, deploy y rollback en `deployment/`.

## Puesta en marcha

1. Crear un usuario de MariaDB exclusivo para Solution SPA.
2. Ejecutar `database/schema.sql` en instalaciones nuevas.
3. En instalaciones existentes, ejecutar `php database/migrate.php` después de un backup.
4. Configurar `backend/config.php` o variables `DB_*`.
5. Configurar Nginx/PHP-FPM para servir el proyecto.
6. Probar `/backend/api/health.php`; debe responder con `ok: true` y conexión de base operativa.
7. Ejecutar `bash tests/run.sh`.

## Producción

No publicar rutas de escritura sin HTTPS y sin conservar las protecciones del backend. Los secretos no deben almacenarse en GitHub.

El flujo recomendado de despliegue está en `deployment/README.md` y la separación de entornos en `docs/ENVIRONMENTS.md`.
