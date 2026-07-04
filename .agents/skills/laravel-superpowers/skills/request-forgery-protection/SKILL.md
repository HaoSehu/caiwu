---
name: laravel:request-forgery-protection
description: Configure CSRF and origin-aware request forgery protection; PreventRequestForgery middleware (Laravel 13+) with token fallback and exclusions
---

# Request Forgery Protection

Protect state-changing routes from cross-site request forgery. Laravel 13 formalizes this as `PreventRequestForgery`, adding origin-aware verification (`Sec-Fetch-Site`) on top of token-based CSRF.

## Commands

```
# Blade forms still emit tokens
<form method="POST" action="/profile">
    @csrf
    ...
</form>

# Laravel 13+: reference the new middleware class
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;   // was: VerifyCsrfToken

->withoutMiddleware([PreventRequestForgery::class]);

# Optional origin-only mode (bootstrap/app.php)
->withMiddleware(function (Middleware $middleware) {
    $middleware->preventRequestForgery(originOnly: true);
})

# Exclude webhook URIs from verification
$middleware->validateCsrfTokens(except: ['stripe/*']);
```

## Patterns

- Keep `@csrf` in forms; origin verification is additive, token fallback covers older browsers/HTTP
- Only enable `originOnly` when all clients are modern browsers over HTTPS
- Exclude third-party webhook endpoints explicitly and verify their signatures instead
- When upgrading to 13.x, replace `VerifyCsrfToken` references with `PreventRequestForgery` (old name remains a deprecated alias)
- Never disable forgery protection globally to "fix" a failing integration — scope exclusions per URI

## Testing

- Feature tests bypass CSRF by default; add explicit tests for excluded webhook routes and their signature checks
- Assert 419 responses for missing/invalid tokens where relevant
