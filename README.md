# Solution SPA

Spa de aparatología con frontend público, panel administrativo y backend PHP/MariaDB.

## Incluye

- diseño responsive y prioridad móvil;
- tratamientos faciales y corporales;
- carta de servicios y solicitud pública de valoración;
- clientes, citas, servicios, pagos y usuarios;
- autenticación por sesión, roles/permisos, CSRF y rate limiting;
- portal de cliente y flujo de registro;
- health check para backend/MariaDB;
- auditoría de mutaciones administrativas y financieras;
- teléfonos normalizados a E.164;
- migraciones versionadas, pruebas y CI;
- scripts de backup, despliegue verificable y rollback.

## Backend

El backend está preparado para PHP 8.4 + MariaDB. Consulta `BACKEND_PILOT.md` para la puesta en marcha y `deployment/README.md` para el flujo de producción.

## Hache-Base

El endurecimiento reutiliza patrones de Hache-Base adaptados al SPA. No se copió la base completa: se incorporaron únicamente las capacidades útiles para este proyecto. El detalle está en `docs/HACHE_BASE_HARDENING.md`.

## Entornos

La configuración local puede vivir en `backend/config.php` (ignorado por Git). Staging y producción pueden usar variables `DB_*` y las demás variables descritas en `.env.example`. Consulta `docs/ENVIRONMENTS.md`.

Las fotografías demostrativas provienen de Pexels y se cargan desde su CDN.
