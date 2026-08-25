# Hache-Base Hardening Pack aplicado a Solution SPA

Este cambio prueba Hache-Base sobre un proyecto existente. La regla fue seleccionar capacidades útiles y adaptarlas al SPA, no copiar Hache-Base completo.

## 1. Auditoría de mutaciones

Se añadió `audit_events` y el helper `backend/audit.php`.

Se auditan operaciones relevantes de:

- clientes;
- citas;
- pagos;
- servicios;
- usuarios/roles;
- solicitudes web y conversiones;
- auto-registro de clientes.

Los snapshots eliminan claves sensibles conocidas antes de guardarse. La auditoría no almacena contraseñas ni hashes.

## 2. Teléfonos E.164

`backend/phone.php` normaliza números nuevos antes de persistirlos. México usa `+52` por defecto y puede cambiarse con `PHONE_DEFAULT_COUNTRY`. También se convierte el formato histórico mexicano `+521`.

No se ejecuta una migración masiva de teléfonos históricos automáticamente: esos registros deben revisarse antes de modificar datos existentes.

## 3. Migraciones y CI

`database/migrate.php` ya no termina después de detectar una migración previa; aplica cada bloque pendiente por nombre. La migración `20260825_hache_base_hardening` crea la auditoría de forma idempotente.

GitHub Actions valida:

- sintaxis PHP;
- tests de teléfono y sanitización;
- sintaxis de scripts shell;
- creación del esquema en MariaDB 11.8;
- ejecución repetida del runner de migraciones;
- existencia de `audit_events`.

## 4. Configuración y entornos

`backend/db.php` mantiene compatibilidad con `backend/config.php`, pero permite sobrescribir la conexión mediante variables `DB_*`. `.env.example` documenta los nombres sin contener secretos.

La separación `development / staging / production` se documenta en `docs/ENVIRONMENTS.md`.

## 5. Backup, despliegue y rollback

`deployment/` implementa el patrón:

`PRECHECK -> BACKUP -> UPDATE -> MIGRATIONS -> TESTS -> HEALTH -> DEPLOY_OK`

El despliegue automático desde GitHub no se activa todavía. El VPS debe configurar primero sus variables/secretos y staging debe validarse antes de automatizar producción.

## Fuera de alcance deliberadamente

- multisede, porque el SPA actual no la necesita;
- migración automática de teléfonos históricos;
- workflows de n8n/WhatsApp;
- secretos o datos reales de producción;
- activación automática del VPS desde GitHub.
