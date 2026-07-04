---
name: caiwu-backend-api
description: Work on Caiwu backend Laravel 12 APIs, services, payments, invoices, orders, callbacks, upstream providers, migrations, tests, and API documentation. Use for backend changes under backend/, API behavior, response format, FormRequest/Resource placement, Sanctum auth, permission middleware, payments, billing, queues, and upstream integrations.
---

# Caiwu Backend API

Read first:

- `AGENTS.md`
- `文档/开发文档/后端/API格式规范.md`
- `文档/开发文档/后端/后端目录分类规范.md`
- `文档/开发文档/架构/架构现状说明.md`
- For upstream/payment work: `文档/开发文档/集成/本地对接说明.md`

## Core Rules

- Laravel 12, PHP 8.2+, Sanctum Token auth.
- Admin routes: `routes/admin.php`, controllers in `app/Http/Controllers/Admin/`.
- Client routes: `routes/client.php`, controllers in `app/Http/Controllers/Client/`.
- Public site routes: `routes/api.php`, public controllers at `app/Http/Controllers/`.
- Admin and client tokens stay separate as `admin_token` and `client_token`; permission middleware uses `permission:{code}`.
- Keep controllers thin; move business work to `app/Services/<Domain>/`.
- Use `FormRequest` for writes, complex reads, financial operations, callbacks, imports/exports, and batch actions.
- Use `Resource` or explicit DTOs for response fields.
- Return via `App\Traits\ApiResponse` / `App\Support\ApiResponseBuilder`.
- Success code is always `0`; pagination is `list`, `total`, `page`, `page_size`.
- Local backend startup is `php artisan app:serve`; do not replace it with `php artisan serve`.
- Production queue work is triggered through `schedule:run`; do not require a resident production `queue:work`.

## Financial And Integration Safety

- Do not physically delete `payments` rows.
- `payments` records only third-party real money inflow; balance/manual/free flows do not create Payment records.
- Preserve any existing historical non-third-party Payment rows; the rule is no new balance/manual/free Payment records and no physical deletion.
- Financial reversals need reverse ledger/status/audit records, not deletion.
- Payment, order, invoice, balance, referral, and callback flows need transaction, idempotency, and audit fields.
- Third-party calls must go through Service/Driver layers, never direct `Http::*` in controllers.
- `mofang_finance_api` remains an independent provider key; do not alias it to `hosting_panel_api`.
- Callback routes must use signature middleware and idempotent service logic.

## Database And API Docs

- Migrations must be new files; do not modify historical migrations or replay early aggressive migrations.
- Treat real database `information_schema` as truth when schema docs and live structure disagree.
- Do not hand edit `文档/开发文档/后端/后端API清单.md`; update `文档/开发文档/后端/API清单导航.md` for business navigation or regenerate inventory with the script when requested.

## Validation

Run affected tests first when possible, then broader backend validation:

```bash
cd backend
php artisan test
```

For backend refactors or formatting-sensitive work, also run `php vendor\bin\pint --test`. If API inventory changes and the user asks for regenerated docs, run `php backend/scripts/export_api_inventory.php`.
