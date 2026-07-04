---
name: laravel:upgrade-13
description: Upgrade an app from Laravel 12.x to 13.x safely; PHP 8.3 baseline, dependency bumps, breaking-change checklist, and verification steps
---

# Upgrading to Laravel 13

Laravel 13 (released March 2026) is a deliberately small upgrade. Work through this checklist in a dedicated branch with green tests before and after.

## Prerequisites

- PHP 8.3+ (13.x supports PHP 8.3–8.5; 8.2 is dropped)
- Green test suite on 12.x before starting

## Commands

```
# Bump core dependencies in composer.json
"laravel/framework": "^13.0",
"laravel/tinker": "^3.0",
"phpunit/phpunit": "^12.0",
"pestphp/pest": "^4.0"

sail composer update                 # or: composer update

# Verify
sail artisan about                   # or: php artisan about
sail artisan test --parallel         # or: php artisan test --parallel
```

## Breaking-Change Checklist

High impact:
- Replace `VerifyCsrfToken` references with `PreventRequestForgery` (old name is a deprecated alias) — see `laravel:request-forgery-protection`
- PHP 8.3 minimum; upgrade PHP before `composer update`

Medium impact:
- `config/cache.php` gains `serializable_classes` (default `false`); allow-list any classes you store in cache as objects
- `upsert()` now throws `InvalidArgumentException` on empty `uniqueBy` — fix call sites instead of relying on invalid SQL
- Default cache prefixes / session cookie names switched underscores → hyphens; pin `CACHE_PREFIX`, `REDIS_PREFIX`, `SESSION_COOKIE` in `.env` if you relied on framework defaults (avoids logging everyone out and cold caches)

Low impact (scan if applicable):
- Serialized Eloquent collections now restore eager-loaded relations (affects queued jobs)
- `JobAttempted` event: `$exceptionOccurred` (bool) → `$exception` (`?Throwable`); `QueueBusy`: `$connection` → `$connectionName`
- Domain routes now take precedence over non-domain routes during matching
- MySQL `DELETE` with JOIN now compiles `ORDER BY`/`LIMIT` instead of silently dropping them
- Custom cache stores need `touch()`; custom queue drivers need size-inspection methods

## Patterns

- Upgrade one major version at a time; never skip 12.x → 13.x steps
- Read the full upgrade guide: https://laravel.com/docs/13.x/upgrade
- Laravel Boost ^2.0 offers an AI-assisted `/upgrade-laravel-v13` flow — useful, but review its diff like any PR
- Adopt new 13.x features (attributes, JSON:API resources, AI SDK) in follow-up PRs, not the upgrade PR

## Verification

- Full test suite + static analysis (PHPStan) clean
- Boot the app; verify sessions persist and caches warm correctly (prefix changes)
- Exercise queues, scheduler, and webhooks in staging before production
