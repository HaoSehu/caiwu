# Laravel 11.x–13.x — Common Good Patterns (Skills Map)

This document summarizes patterns present across Laravel 11.x, 12.x, and 13.x documentation and maps them to skills, subagents, and commands provided by this plugin. It focuses on stable areas that change little between versions and are broadly applicable, plus a small set of 13.x-specific skills.

## Core Skills (Docs Intersection)

- Form Requests and Validation → `skills/form-requests-and-validation/SKILL.md`
- Policies and Authorization → `skills/policies-and-authorization/SKILL.md`
- Eloquent Relationships and Loading → `skills/eloquent-relationships-and-loading/SKILL.md`
- Transactions and Consistency → `skills/transactions-and-consistency/SKILL.md`
- HTTP Client Resilience → `skills/http-client-resilience/SKILL.md`
- Task Scheduling → `skills/task-scheduling/SKILL.md`
- API Resources and Pagination → `skills/api-resources-and-pagination/SKILL.md`
- Blade Components and Layouts → `skills/blade-components-and-layouts/SKILL.md`
- Performance Caching (with tags/locks/invalidation) → `skills/performance-caching/SKILL.md`
- Exception Handling and Logging → `skills/exception-handling-and-logging/SKILL.md`
- Filesystem Uploads and URLs → `skills/filesystem-uploads-and-urls/SKILL.md`
- Rate Limiting and Throttle → `skills/rate-limiting-and-throttle/SKILL.md`

These complement existing skills such as runner selection, migrations/factories, queues/Horizon, performance (eager loading, select columns, caching), and TDD.

## Laravel 13.x Skills

Version-specific features from the 13.x release (March 2026) are covered by dedicated skills, and stable skills carry clearly-marked "Laravel 13+" sections:

- Laravel AI SDK (agents, embeddings, images, audio) → `skills/ai-sdk-essentials/SKILL.md`
- Semantic / Vector Search (pgvector) → `skills/vector-semantic-search/SKILL.md`
- First-Party PHP Attributes → `skills/php-attributes/SKILL.md`
- Request Forgery Protection (origin-aware CSRF) → `skills/request-forgery-protection/SKILL.md`
- Upgrading 12.x → 13.x → `skills/upgrade-to-laravel-13/SKILL.md`

## Subagents

This repo keeps subagents minimal to avoid overlap with skills. Use the existing controller-focused subagent:

- Controller Cleaner → `agents/laravel-controller-cleaner.md`

Other topics above are covered as skills rather than subagents.

## Command Wrappers

Each new skill has a matching command in `commands/` following the existing convention. For example, `commands/laravel-form-requests.md` activates `laravel:form-requests`, and `commands/laravel-upgrade-13.md` activates `laravel:upgrade-13`.

## Notes on Source Material

The intersection patterns are stable across 11.x–13.x. Version‑specific features are annotated inline as "Laravel 13+" or split into dedicated skills so guidance remains safe on older apps.
