# Reportes de Sorteo y Retrabajo

Aplicación para digitalizar y consultar reportes de inspección de sorteo y retrabajo. Cuenta con dos portales separados:

- **Backoffice interno** (`/app`): Para inspectores, supervisores y administradores. CRUD completo de inspecciones.
- **Portal Empresa** (`/portal`): Para empresas/clientes. Solo lectura con dashboard y detalle de inspecciones.

## Stack Tecnológico

- Laravel 12 + PostgreSQL
- Inertia.js + Vue 3 + TypeScript
- Tailwind CSS v4 + shadcn-vue (reka-ui)
- Spatie Laravel Permission (roles y permisos backoffice)
- Laravel Reverb (WebSockets tiempo real)
- barryvdh/laravel-dompdf (exportación PDF)
- Laravel Sail (entorno de desarrollo Docker)

## Requisitos

- Docker Desktop (para Sail)
- Node.js 18+
- Composer (local, PHP 8.2+)

## Instalación

### 1. Clonar y configurar

```bash
git clone <repo-url> draw-app
cd draw-app
cp .env.example .env
```

### 2. Instalar dependencias

```bash
composer install
npm install
```

### 3. Levantar contenedores con Sail

```bash
./vendor/bin/sail up -d
```

### 4. Generar key y ejecutar migraciones

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

### 5. Compilar assets

```bash
./vendor/bin/sail npm run dev
```

### 6. Reverb y Horizon (tiempo real y cola)

El `compose.yaml` incluye los servicios **reverb** y **horizon**. Con `sail up -d` ya se levantan. Para que funcionen correctamente, en tu `.env` con Sail usa:

- `REVERB_HOST=reverb`
- `QUEUE_CONNECTION=redis`
- `REDIS_HOST=redis`

(VITE_REVERB_HOST puede quedarse en `localhost` para que el navegador se conecte al puerto 8080 del host.)

**Validar** que Reverb y Horizon estén activos (requiere `REVERB_HOST=reverb` en `.env`):

```bash
./vendor/bin/sail exec laravel.test bash scripts/validate-realtime.sh
```

Si no usas Sail, Reverb y el worker de cola se pueden gestionar con Supervisor; ver `deploy/supervisor/README.md`.

### Telescope (depuración)

[Telescope](https://laravel.com/docs/telescope) está instalado para inspeccionar requests, jobs, excepciones, broadcasts, etc. En local está en `/telescope` (requiere estar logueado en el backoffice). En producción solo pueden acceder usuarios con rol `super_admin`.

## Configuración

### PostgreSQL

Ya configurado en `.env` con credenciales de Sail:

```
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

### Mail (Magic Links)

Para desarrollo, los emails se guardan en el log:

```
MAIL_MAILER=log
```

Para ver los emails, revisa `storage/logs/laravel.log` o usa [Mailtrap](https://mailtrap.io):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=<tu-usuario>
MAIL_PASSWORD=<tu-password>
```

### Reverb (WebSockets)

```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

## Credenciales de Demo

### Backoffice (`/login`)

| Email | Password | Rol |
|---|---|---|
| admin@pluss.com | password | super_admin |
| admin@acme-mfg.com | password | company_admin |
| supervisor@acme-mfg.com | password | supervisor_calidad |
| inspector@acme-mfg.com | password | inspector |
| auditor@acme-mfg.com | password | auditor_interno |

### Portal Empresa (`/portal/login`)

Ingresar cualquiera de estos correos para recibir un Magic Link:

| Email | Empresa |
|---|---|
| viewer@acme-mfg.com | ACME Manufacturing |
| quality@acme-mfg.com | ACME Manufacturing |
| contacto@acme-mfg.com | ACME Manufacturing |
| viewer@beta-ind.com | Beta Industries |

**Nota**: Con `MAIL_MAILER=log`, el enlace aparece en `storage/logs/laravel.log`.

## Arquitectura

### Dos Guards de Autenticación

- `web`: Para usuarios internos del backoffice (modelo `User`)
- `portal`: Para viewers de empresa (modelo `CompanyViewer`)

### Aislamiento de Empresa (Portal)

- Middleware `EnsureCompanyAccess` aplica automáticamente un scope `WHERE company_id = ?` a todos los modelos.
- El `company_id` siempre se obtiene de la sesión del portal, nunca de parámetros del request.
- Route model binding adicional verifica que el recurso pertenezca a la empresa.

### Roles y Permisos (Backoffice)

| Rol | Permisos |
|---|---|
| super_admin | Todos |
| company_admin | CRUD inspecciones, usuarios, etiquetas de su empresa |
| supervisor_calidad | Crear, editar, cerrar inspecciones |
| inspector | Crear y editar sus inspecciones |
| auditor_interno | Solo lectura + exportar |

### Tiempo Real (Reverb)

- Canal `company.{id}`: Para backoffice
- Canal `portal.company.{id}`: Para portal empresa
- Eventos: `InspectionUpdated`, `InspectionCompleted`
- Fallback: Polling cada 30s si WebSocket falla

## Rutas Principales

### Backoffice (`/app`)

- `/app/dashboard` - Dashboard
- `/app/inspections` - CRUD inspecciones
- `/app/companies` - CRUD empresas
- `/app/users` - CRUD usuarios
- `/app/defect-tags` - Etiquetas de defecto

### Portal Empresa (`/portal`)

- `/portal/login` - Login con Magic Link
- `/portal/dashboard` - Dashboard read-only
- `/portal/inspections` - Listado con filtros
- `/portal/inspections/{id}` - Detalle de inspección

## Tests

```bash
./vendor/bin/sail artisan test
```

Tests incluidos:

- **PortalIsolationTest**: Verifica que empresa A no puede ver datos de empresa B
- **MagicLinkTest**: Valida flujo de magic link, expiración, emails permitidos
- **BackofficePolicyTest**: Valida permisos por rol (inspector, auditor, admin)

## Comandos Útiles

```bash
# Levantar entorno
./vendor/bin/sail up -d

# Migraciones
./vendor/bin/sail artisan migrate --seed

# Fresh (borrar y recrear todo)
./vendor/bin/sail artisan migrate:fresh --seed

# WebSocket server
./vendor/bin/sail artisan reverb:start

# Queue worker
./vendor/bin/sail artisan queue:listen

# Vite dev server
./vendor/bin/sail npm run dev

# Build producción
./vendor/bin/sail npm run build

# Linter PHP
./vendor/bin/sail composer lint

# Tests
./vendor/bin/sail artisan test
```
