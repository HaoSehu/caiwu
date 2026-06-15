---
name: caiwu-backend-api
description: Work on Caiwu backend Laravel 12 APIs, services, payments, invoices, orders, callbacks, upstream providers, migrations, tests, and API documentation. Use for backend changes under backend/, API behavior, response format, FormRequest/Resource placement, Sanctum auth, permission middleware, payments, billing, queues, and upstream integrations.
---

# Caiwu Backend API

Read first:

- `AGENTS.md`
- `文档/后端/API格式规范.md`
- `文档/后端/后端目录分类规范.md`
- `文档/架构/架构现状说明.md`
- For upstream/payment work: `文档/集成/本地对接说明.md`

## Core Rules

- Laravel 12, PHP 8.2+, Sanctum Token auth.
- Admin routes: `routes/admin.php`, controllers in `app/Http/Controllers/Admin/`.
- Client routes: `routes/client.php`, controllers in `app/Http/Controllers/Client/`.
- Public site routes: `routes/api.php`, public controllers at `app/Http/Controllers/`.
- Keep controllers thin; move business work to `app/Services/<Domain>/`.
- Use `FormRequest` for writes, complex reads, financial operations, callbacks, imports/exports, and batch actions.
- Use `Resource` or explicit DTOs for response fields.
- Return via `App\Traits\ApiResponse` / `App\Support\ApiResponseBuilder`.
- Success code is always `0`; pagination is `list`, `total`, `page`, `page_size`.

## Financial And Integration Safety

- Do not physically delete `payments` rows.
- `payments` records only third-party real money inflow; balance/manual/free flows do not create Payment records.
- Financial reversals need reverse ledger/status/audit records, not deletion.
- Third-party calls must go through Service/Driver layers, never direct `Http::*` in controllers.
- `mofang_finance_api` remains an independent provider key; do not alias it to `hosting_panel_api`.
- Callback routes must use signature middleware and idempotent service logic.

## Validation

Run affected tests first when possible, then:

```bash
cd backend
php artisan test
```

If API inventory changes and the user asks for regenerated docs, run `php backend/scripts/export_api_inventory.php`; do not hand edit `文档/后端/后端API清单.md`.
