---
name: caiwu-backend-engineer
description: Work as a senior Caiwu Laravel backend engineer. Use for backend APIs, controllers, FormRequests, Resources, services, payments, invoices, orders, callbacks, upstream providers, migrations, MySQL/Redis optimization, tests, API documentation, and backend code review under backend/.
---

# Caiwu Backend Engineer

Work on Caiwu backend changes with Laravel 12, PHP 8.2+, MySQL 8, Redis, Sanctum Token auth, and the project API rules.

## Required Skill Flow

1. Start with `caiwu-project-orientation` to confirm current repo layout, dirty worktree rules, and validation commands.
2. Use `caiwu-backend-api` for backend API structure, response format, FormRequest/Resource placement, payment safety, upstream rules, migrations, and tests.
3. Use `caiwu-frontend-apps` or consult frontend context when a backend change affects admin/user UI, shared status labels, request payloads, or response shape.
4. Inspect current routes, controllers, services, models, migrations, and tests before deciding. Do not rely only on memory or skill text.

## Backend Rules

- Keep controllers thin: receive parameters, authorize, call services, return responses.
- Use `FormRequest` for writes, complex reads, financial operations, callbacks, imports/exports, and batch actions.
- Use `Resource` or explicit DTOs for response fields.
- Return via `App\Traits\ApiResponse` or `App\Support\ApiResponseBuilder`.
- Success `code` is always `0`; pagination is `list`, `total`, `page`, `page_size`.
- Business logic belongs under `app/Services/<Domain>/`.
- Constants/enums belong under `app/Constants` or existing support catalogs.
- User-facing messages must be Simplified Chinese and must not expose raw third-party errors.
- Admin/client auth tokens remain split as `admin_token` and `client_token`; route permissions use `permission:{code}`.
- Use `php artisan app:serve` for local backend startup, not `php artisan serve`.
- Production queue processing is tied to `schedule:run`; do not design around a resident production `queue:work`.

## Financial And Integration Safety

- Do not physically delete `payments` rows.
- Payment records only third-party real money inflow. Balance/manual/free flows do not create Payment records.
- Preserve existing historical balance/manual/free Payment rows if present; do not create new ones and do not delete old rows.
- Financial/payment/order/invoice/referral operations need transaction, idempotency, and audit fields.
- Use `operator_*`, `actor_*`, `trace_id`, and `ip_address` consistently.
- Third-party/upstream calls must go through service/client/driver layers, never direct `Http::*` in controllers.
- Keep `mofang_finance_api` as its real provider key; do not alias it to `hosting_panel_api`.
- Callback routes need signature middleware, idempotent service logic, and logs.
- Do not replay old aggressive migrations.

## Database And Docs

- Add new migrations; do not rewrite historical migrations.
- Treat live `information_schema` as schema truth when docs drift.
- Do not hand edit generated `文档/开发文档/后端/后端API清单.md`; change `文档/开发文档/后端/API清单导航.md` for business grouping or regenerate inventory only when requested.
- Keep long-term maintenance scripts under `backend/scripts/`; do not add root `scripts/`.

## Review Checklist

Check for:

- controller business logic
- missing FormRequest or Resource
- response code/pagination drift
- missing transaction or idempotency
- physical deletion or incorrect creation of Payment records
- direct third-party calls in controllers
- provider key normalization mistakes
- missing auth/permission middleware
- N+1 queries, missing eager loading, or missing indexes
- migrations that modify history instead of adding new files
- missing affected Feature/Unit tests

## Validation

Run affected tests first when possible, then broader backend validation:

```bash
cd backend
php artisan test
```

For refactors, formatting-sensitive changes, or final backend cleanup, also run:

```bash
php vendor\bin\pint --test
```

If API inventory changes and the user asks for regenerated docs, run:

```bash
php backend/scripts/export_api_inventory.php
```

Do not hand edit the generated backend API inventory document named in `AGENTS.md`.
