# Demo WebSockets: Inspector + Panel Empresa

Escenario para ver en tiempo real cómo los cambios del inspector se reflejan en el panel de la empresa (portal).

## Requisitos

- Reverb encendido: `./vendor/bin/sail artisan reverb:start` (o el servicio `reverb` con Sail).
- Cola/Horizon procesando: `./vendor/bin/sail artisan horizon` o `queue:work`.
- Seed ejecutado: `php artisan db:seed` (crea la inspección demo `INS-DEMO-WS-001`).

## Cuentas del escenario

| Rol        | Dónde       | Email                   | Acceso                          |
|-----------|-------------|--------------------------|----------------------------------|
| Inspector | Backoffice  | `inspector@acme-mfg.com` | Contraseña: `password`           |
| Empresa   | Portal      | `viewer@acme-mfg.com`    | Magic link (ver paso 2)          |

## Pasos

### 1. Pestaña 1 — Inspector (backoffice)

1. Ir a **http://localhost/login** (o la URL de tu app).
2. Iniciar sesión: **inspector@acme-mfg.com** / **password**.
3. Ir a **Inspecciones** y abrir la inspección **INS-DEMO-WS-001** (o cualquier inspección de ACME en progreso).
4. Dejar esta pestaña abierta para editar (guardar, agregar ítems, etc.).

### 2. Pestaña 2 — Empresa (portal)

1. Ir a **http://localhost/portal/login** en **otra pestaña o ventana**.
2. Escribir **viewer@acme-mfg.com** y solicitar el magic link.
3. Con `MAIL_MAILER=log`, el enlace aparece en **storage/logs/laravel.log**. Copiar la URL del magic link y abrirla en esa misma pestaña (o en una nueva).
4. Entrar al **Dashboard** del portal. Verás el resumen y las inspecciones recientes de ACME.

### 3. Probar WebSockets

1. En la **pestaña del inspector**: editar la inspección (cambiar comentario, cantidades, guardar) o crear una nueva inspección para ACME.
2. En la **pestaña del portal**: sin recargar a mano, el dashboard (y la lista de inspecciones) debería actualizarse solo al recibir el evento por WebSocket (Reverb).

Si no se actualiza, comprueba que Reverb y la cola estén en marcha y que en el panel de empresas (backoffice) el indicador de WebSocket muestre “en línea”.
