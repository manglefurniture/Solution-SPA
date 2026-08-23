# Solution SPA — piloto de backend

Este piloto prepara el repositorio para funcionar en la VPS con PHP 8.4 + MariaDB. GitHub Pages seguirá mostrando únicamente el frontend estático; los archivos PHP entrarán en funcionamiento al desplegar el proyecto en la VPS.

## Incluido

- Base MariaDB `solution_spa`.
- Tablas: `clients`, `services`, `appointments`, `treatments`, `users`.
- Conexión PDO con consultas preparadas.
- API inicial para clientes, servicios y citas.
- Endpoint de salud para comprobar la conexión con MariaDB.
- Archivo de configuración de ejemplo sin credenciales reales.

## Despliegue en VPS

1. Crear un usuario de MariaDB exclusivo para Solution SPA.
2. Ejecutar `database/schema.sql`.
3. Copiar `backend/config.example.php` como `backend/config.php`.
4. Colocar las credenciales locales en `backend/config.php` (el archivo está ignorado por Git).
5. Configurar Nginx/PHP-FPM para servir el proyecto.
6. Probar `/backend/api/health.php`; debe responder `{"ok":true,"database":"connected"}`.

## Endpoints del piloto

- `GET /backend/api/clients.php` — listar/buscar clientes.
- `POST /backend/api/clients.php` — crear cliente.
- `GET /backend/api/services.php` — listar servicios.
- `POST /backend/api/services.php` — crear servicio.
- `GET /backend/api/appointments.php?date=YYYY-MM-DD` — agenda por fecha.
- `POST /backend/api/appointments.php` — crear cita.

## Antes de hacerlo público

La API es una base técnica del piloto. Antes de exponer las rutas de escritura en producción se debe añadir autenticación del panel administrativo, protección CSRF/sesiones y validaciones adicionales. No guardar contraseñas ni secretos en GitHub.
