---
name: laravel:using-laravel-superpowers
description: Optional Laravel Superpowers orientation. In Caiwu, do not read first; use only when explicitly requested because AGENTS.md and caiwu-* skills define local runner and workflow rules.
---

# Using Superpowers in Laravel Projects

This plugin adds Laravel-aware guidance while staying platform-agnostic. It works in any Laravel app with or without Sail.

## Runner Selection

In Caiwu, follow `AGENTS.md`: use `php artisan app:serve` for local backend startup and `php artisan test` for backend validation. Do not require Sail unless the user explicitly asks to introduce Sail.

## Core Workflows

- Test-Driven Development first: use `laravel:tdd-with-pest`
- Database changes: use `laravel:migrations-and-factories`
- Quality gates: use `laravel:quality-checks` (Pint, Insights/PHPStan)
- Queues and Horizon: use `laravel:queues-and-horizon`
- Architecture patterns: `laravel:ports-and-adapters`, `laravel:template-method-and-plugins`
- Keep complexity low: `laravel:complexity-guardrails`

## Philosophy

- Favor small, testable services; avoid fat controllers/commands/jobs
- DTOs, typed Collections, and Enums when they clarify intent
- Prefer model factories in tests and model scopes for complex queries
- Verify before completion—run tests and linters clean

When a generic Laravel skill conflicts with Caiwu rules, Caiwu rules win.
