---
name: laravel:vector-search
description: Add semantic search with native vector queries (Laravel 13+); pgvector similarity clauses, embedding workflows, and hybrid search patterns
---

# Semantic / Vector Search (Laravel 13+)

Use native vector query support to build semantic search on PostgreSQL + `pgvector`. Generate embeddings via the AI SDK and query them directly from the query builder.

## Commands

```
# Migration: vector column (requires pgvector extension)
Schema::table('documents', function (Blueprint $table) {
    $table->vector('embedding', dimensions: 1536);
});

# Generate an embedding
use Illuminate\Support\Str;
$embedding = Str::of('Best wineries in Napa Valley')->toEmbeddings();

# Similarity search from the query builder
$documents = DB::table('documents')
    ->whereVectorSimilarTo('embedding', 'Best wineries in Napa Valley')
    ->limit(10)
    ->get();
```

## Patterns

- Store embeddings alongside the source row; regenerate when source text changes (model observer or queued job)
- Always `limit()` similarity queries; unbounded vector scans are expensive
- Add a vector index (HNSW/IVFFlat) for large tables; measure recall vs speed trade-offs
- Combine vector similarity with conventional `where` filters (tenant, status, locale) to scope results
- Consider hybrid search: merge keyword matches with semantic results for better precision
- Keep embedding model/dimensions in config; changing models invalidates stored vectors

## Testing

- Fake embedding generation in tests; assert stored dimensions and invalidation triggers
- Integration test similarity queries against a Postgres service with pgvector enabled (skip gracefully if unavailable)
