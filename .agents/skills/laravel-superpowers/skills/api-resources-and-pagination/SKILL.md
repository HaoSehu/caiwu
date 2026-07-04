---
name: laravel:api-resources-and-pagination
description: Use API Resources with pagination and conditional fields; keep response shapes stable and cache-friendly
---

# API Resources and Pagination

Represent models via Resources; keep transport concerns out of Eloquent.

## Commands

```
# Resource
sail artisan make:resource PostResource    # or: php artisan make:resource PostResource

# Controller usage
return PostResource::collection(
    Post::with('author')->latest()->paginate(20)
);

# Resource class
public function toArray($request)
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'author' => new UserResource($this->whenLoaded('author')),
        'published_at' => optional($this->published_at)->toAtomString(),
    ];
}
```

## Patterns

- Prefer `Resource::collection($query->paginate())` over manual arrays
- Use `when()` / `mergeWhen()` for conditional fields
- Keep pagination cursors/links intact for clients
- Version resources when contracts change; avoid breaking fields silently

## Laravel 13+: JSON:API Resources

First-party JSON:API resources handle resource objects (`type`/`id`/`attributes`/`relationships`), `?include=` relationship inclusion, `?fields[...]=` sparse fieldsets, links, and `application/vnd.api+json` headers.

```php
class PostJsonApiResource extends JsonApiResource
{
    public function toArray($request)
    {
        return [
            'type' => 'posts',
            'id' => $this->id,
            'attributes' => ['title' => $this->title, 'body' => $this->body],
            'relationships' => ['author' => new UserJsonApiResource($this->author)],
        ];
    }
}
```

- Reach for JSON:API resources when clients need the spec (Ember, JSON:API tooling); plain Resources remain fine otherwise
- Don't hand-roll `included`/sparse-fieldset logic on top of plain Resources — use the first-party classes

