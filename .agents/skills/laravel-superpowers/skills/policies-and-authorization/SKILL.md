---
name: laravel:policies-and-authorization
description: Enforce access via Policies and Gates; use authorize() and authorizeResource() to standardize controller protections
---

# Policies and Authorization

Use Policies for per-model actions; use Gates for cross-cutting checks.

## Commands

```
# Generate a policy
sail artisan make:policy PostPolicy --model=Post   # or: php artisan make:policy PostPolicy --model=Post

# Apply in routes (resource controllers)
Route::resource('posts', PostController::class);
// In controller constructor
$this->authorizeResource(Post::class, 'post');

# One-off checks
$this->authorize('update', $post);           // in controller
Gate::allows('manage-billing', $user);       // ad-hoc gate
```

## Patterns

- Use resource policy methods: `viewAny, view, create, update, delete, restore, forceDelete`
- Prefer policy methods over inline checks; keeps controllers clean
- Register policies in `AuthServiceProvider`
- Use `can` middleware for quick route protection: `->middleware('can:update,post')`
- In tests, assert `actingAs($user)->get(...)->assertForbidden()` for denied cases

## Laravel 13+: Authorization Attributes

```php
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class CommentController
{
    #[Authorize('create', [Comment::class, 'post'])]
    public function store(Post $post) { /* ... */ }

    #[Authorize('delete', 'comment')]
    public function destroy(Comment $comment) { /* ... */ }
}
```

- Attributes keep the policy check visible at the action; equivalent to `$this->authorize(...)` — pick one style per codebase (see `laravel:php-attributes`)

