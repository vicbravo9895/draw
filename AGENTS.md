# AGENTS.md – Guía para agentes IA

Este documento da contexto a asistentes y agentes que trabajan en el repositorio **draw-app**: aplicación de reportes de inspección (sorteo y retrabajo) con backoffice interno y portal para empresas.

---

## Stack y entorno

- **Backend**: Laravel 12, PHP 8.2+, PostgreSQL
- **Frontend**: Inertia.js + Vue 3 + TypeScript, Tailwind CSS v4, shadcn-vue (reka-ui)
- **Auth**: Laravel Fortify (web), Magic Link para portal
- **Permisos**: Spatie Laravel Permission (roles/permisos en backoffice)
- **Tiempo real**: Laravel Reverb (WebSockets)
- **Exportación**: barryvdh/laravel-dompdf (PDF)
- **Desarrollo**: Laravel Sail (Docker)

Comandos típicos: `./vendor/bin/sail artisan …`, `./vendor/bin/sail npm run dev`, `./vendor/bin/sail artisan test`.

---

## Dos portales

1. **Backoffice** (prefijo `/app`)
   - Usuarios internos (modelo `User`), guard `web`.
   - CRUD de inspecciones, empresas, usuarios, etiquetas de defecto.
   - Rutas en `routes/app.php`, controladores en `app/Http/Controllers/App/`, páginas en `resources/js/pages/App/`.
   - Layout: revisar `resources/js/layouts/` (no PortalLayout).

2. **Portal Empresa** (prefijo `/portal`)
   - Viewers de empresa (modelo `CompanyViewer`), guard `portal`.
   - Login por Magic Link; solo lectura: dashboard y detalle de inspecciones.
   - Rutas en `routes/portal.php`, controladores en `app/Http/Controllers/Portal/`, páginas en `resources/js/pages/Portal/`.
   - Layout: `resources/js/layouts/PortalLayout.vue`.

No mezclar lógica ni vistas entre backoffice y portal; el aislamiento de empresa aplica solo al portal.

---

## Aislamiento por empresa (portal)

- Middleware `EnsureCompanyAccess`: aplica scope por `company_id` (sesión del portal).
- El `company_id` se toma de la sesión del portal, **nunca** de query/body.
- Route model binding en portal debe validar que el recurso pertenezca a la empresa del viewer.
- Modelos con empresa: revisar `BelongsToCompany`, `CompanyScope` y `app/Models/`.

Al añadir recursos en portal, mantener este patrón de aislamiento y no exponer `company_id` como parámetro de entrada.

---

## Autenticación

- **Backoffice**: login clásico (Fortify), 2FA disponible; sesión `web`.
- **Portal**: solo Magic Link; enlace firmado y expiración; sesión `portal`.
- Emails de magic link: en desarrollo con `MAIL_MAILER=log` se escriben en `storage/logs/laravel.log`.

---

## Roles y permisos (backoffice)

Roles principales: `super_admin`, `company_admin`, `supervisor_calidad`, `inspector`, `auditor_interno`. Permisos: `companies.view`, `users.view`, `defect_tags.manage`, etc. Usar policies (`app/Policies/`) y middleware `permission:` en rutas. No hardcodear comprobaciones por rol; usar permisos o policies.

---

## Estructura relevante

- **Rutas**: `routes/web.php` (entrada, redirects), `routes/app.php` (backoffice), `routes/portal.php` (portal), `routes/channels.php` (broadcasting).
- **Modelos**: `User`, `Company`, `CompanyViewer`, `Inspection`, `InspectionPart`, `InspectionItem`, `DefectTag`.
- **Eventos**: `app/Events/` (p. ej. `InspectionUpdated`, `InspectionCompleted`) para Reverb.
- **Frontend**: componentes en `resources/js/components/`, UI en `resources/js/components/ui/`, composables en `resources/js/composables/`, tipos en `resources/js/types/`.
- **Tests**: `tests/Feature/` (PortalIsolationTest, MagicLinkTest, BackofficePolicyTest, etc.).

---

## Convenciones de código

- **PHP**: PSR-12, Laravel Pint (`./vendor/bin/sail composer lint`). Controladores del backoffice en `App\Http\Controllers\App`, del portal en `App\Http\Controllers\Portal`.
- **Vue/TypeScript**: componentes en PascalCase, composables con prefijo `use`. Preferir tipos en `resources/js/types/` y props tipadas.
- **Inertia**: controladores devuelven `Inertia::render('NombrePagina', [...])`; nombres de página alineados con la ruta (p. ej. `App/Inspections/Index.vue` para `/app/inspections`).
- **Seguridad**: validar siempre en backend; no confiar en permisos solo en frontend. En portal, no exponer datos de otras empresas.

---

## Tests

Ejecutar: `./vendor/bin/sail artisan test`. Mantener y extender tests de aislamiento (PortalIsolationTest), magic link (MagicLinkTest) y políticas backoffice (BackofficePolicyTest) al tocar auth, portal o permisos.

---

## Resumen rápido para tareas

- **Cambios solo en backoffice**: rutas en `app.php`, controladores en `App/`, páginas en `pages/App/`.
- **Cambios solo en portal**: rutas en `portal.php`, controladores en `Portal/`, páginas en `pages/Portal/`, middleware `ensure_company_access`.
- **Nuevos modelos con empresa**: usar trait/scope de empresa y comprobar que el portal solo acceda a la empresa de la sesión.
- **Nuevas pantallas**: añadir ruta, controlador, página Inertia y enlace en el layout/sidebar correspondiente (App vs Portal).
