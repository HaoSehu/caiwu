---
name: laravel:ai-sdk
description: Build AI features with the first-party Laravel AI SDK (Laravel 13+); agents, embeddings, images, audio, and tool calling with provider-agnostic APIs
---

# Laravel AI SDK Essentials (Laravel 13+)

Use the first-party AI SDK for provider-agnostic text generation, agents, embeddings, images, and audio. Keep provider details in config; keep app code portable across OpenAI, Anthropic, Gemini, Bedrock, Ollama, and others.

## Commands

```
# Install the SDK
sail composer require laravel/ai        # or: composer require laravel/ai

# Basic agent prompt
use App\Ai\Agents\SalesCoach;

$response = SalesCoach::make()->prompt('Analyze this sales transcript...');
return (string) $response;

# Image generation
use Laravel\Ai\Image;
$image = Image::of('A donut sitting on the kitchen counter')->generate();

# Audio synthesis
use Laravel\Ai\Audio;
$audio = Audio::of('I love coding with Laravel.')->generate();

# Embeddings (also see laravel:vector-search)
use Illuminate\Support\Str;
$embeddings = Str::of('Napa Valley has great wine.')->toEmbeddings();
```

## Patterns

- Keep API keys in `.env`; never hardcode provider credentials
- Define agents as dedicated classes with focused instructions; one responsibility per agent
- Use structured output with validation when the response feeds business logic
- Give agents the fewest tools needed; treat tool calling like any other authorization surface
- Configure provider/model failover for production resilience
- Queue long-running generations (images, audio, batch embeddings) instead of blocking requests
- Cache embeddings for stable inputs; regenerating them costs money and time

## Testing

- Fake AI responses in tests; never call real providers from the test suite
- Assert on prompts/inputs sent to the SDK, not just outputs
- Feature test the surrounding flow (queueing, persistence, authorization) with the AI layer faked
