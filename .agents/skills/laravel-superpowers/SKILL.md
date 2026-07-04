---
name: laravel-superpowers
description: Optional external Laravel workflow reference for TDD, migrations, queues, quality gates, controller cleanup, FormRequest validation, routing, transactions, caching, and maintainability. Use only when the user explicitly asks for Laravel Superpowers or when Caiwu-specific backend skills are insufficient; in Caiwu, AGENTS.md and caiwu-* skills override this bundle.
---

# Laravel Superpowers

Use this as a Laravel workflow bundle. It contains many focused sub-skills under `skills/`; load only the relevant one for the task.

## Priority

1. Follow the user's request and the repository's local rules first.
2. In Caiwu, read `AGENTS.md` and the `caiwu-*` skills before applying this bundle.
3. Preserve current Laravel version, auth mode, API response format, queue/scheduler model, payment/accounting rules, and existing service boundaries.
4. Do not apply package, framework, or infrastructure assumptions unless the user explicitly asks for that area.

## Routing

- Start with Caiwu-specific backend skills first. Read `skills/using-laravel-superpowers/SKILL.md` only for explicit Laravel Superpowers research.
- TDD and controller tests: `skills/tdd-with-pest/SKILL.md` or `skills/controller-tests/SKILL.md`. In projects using PHPUnit, adapt examples to PHPUnit instead of introducing Pest without approval.
- Validation and controllers: `skills/form-requests-and-validation/SKILL.md`, `skills/controller-cleanup/SKILL.md`, `skills/api-resources-and-pagination/SKILL.md`.
- Data integrity: `skills/migrations-and-factories/SKILL.md`, `skills/transactions-and-consistency/SKILL.md`, `skills/eloquent-relationships-and-loading/SKILL.md`.
- Operations and resilience: `skills/queues-and-horizon/SKILL.md`, `skills/task-scheduling/SKILL.md`, `skills/http-client-resilience/SKILL.md`, `skills/exception-handling-and-logging/SKILL.md`.
- Quality gates: `skills/quality-checks/SKILL.md`, `skills/complexity-guardrails/SKILL.md`, `skills/iterating-on-code/SKILL.md`.

## Caiwu Guardrails

- Caiwu currently uses Laravel 12, Sanctum token auth, PHPUnit, Pint, PHPStan/Larastan, database queues consumed by schedule, and `php artisan app:serve` for local backend startup.
- Do not introduce Pest, Horizon, Nova, Sail, starter-kit conventions, or Laravel 13 upgrade flows unless explicitly requested.
- Do not physically delete payment records or change third-party payment boundaries.
- Do not hand-edit generated API inventory files.
- Run the repository's affected validation command after code changes.
