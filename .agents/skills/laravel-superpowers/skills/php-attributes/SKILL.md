---
name: laravel:php-attributes
description: Use first-party PHP attributes (Laravel 13+) for controllers, authorization, queue jobs, and models; declarative configuration colocated with code
---

# First-Party PHP Attributes (Laravel 13+)

Laravel 13 expands attribute support across the framework. Prefer attributes when they colocate configuration with the class it affects and reduce boilerplate.

## Commands

```
# Controller middleware + authorization
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class CommentController
{
    #[Middleware('subscribed')]
    #[Authorize('create', [Comment::class, 'post'])]
    public function store(Post $post) { /* ... */ }
}

# Queue job controls
use Illuminate\Queue\Attributes\{Tries, Backoff, Timeout, FailOnTimeout};

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(120)]
#[FailOnTimeout]
class ProcessPodcast implements ShouldQueue { /* ... */ }

# Eloquent model attributes
use Illuminate\Database\Eloquent\Attributes\{DateFormat, WithoutTimestamps};

#[DateFormat('Y-m-d')]
#[WithoutTimestamps]
class Post extends Model { /* ... */ }
```

## Patterns

- Pick one style per concern and stay consistent: either attributes or properties/methods, not both
- Attributes shine for static, per-class config (tries, timeouts, middleware); keep dynamic logic in methods
- `#[Authorize]` on controller actions keeps policy checks visible at the call site — see `laravel:policies-and-authorization`
- Queue attributes replace `$tries`/`$backoff`/`$timeout` properties — see `laravel:queues-and-horizon`
- On mixed 11.x/12.x codebases, keep property-based config until the app is fully on 13.x

## Testing

- Behavior is unchanged by the declaration style; test middleware/authorization/queue behavior as usual
- Feature tests should assert 403s for `#[Authorize]` denials and retry behavior for `#[Tries]`
