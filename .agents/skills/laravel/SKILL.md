---
name: laravel
description: Optional official Laravel reference layer. Use only for explicit Laravel framework convention checks, simplification, maintainability review, or starter-kit upgrade/sync work after local AGENTS.md and Caiwu-specific skills have been applied.
---

# Laravel

Use this skill as an official Laravel reference layer, not as a replacement for project rules.

## Priority

1. Obey the user's request.
2. In a repository, read the local `AGENTS.md` and project-specific skills before applying this skill.
3. Preserve existing architecture, routes, authentication, response formats, validation style, and tests.
4. Do not introduce Laravel starter-kit assumptions unless the user explicitly asks for starter kit upgrade or sync work.

For the Caiwu repository, the local Caiwu skills and `AGENTS.md` override this skill. Keep Laravel changes compatible with Laravel 12, Sanctum token auth, FormRequest validation, Resource/API response conventions, service-layer business logic, and the repository's payment/accounting safety rules.

## Routing

- For small Laravel code cleanup, readability review, or simplification of recently touched PHP/Laravel code, read `agents/laravel-simplifier.md`.
- For explicit requests to update, sync, migrate, or selectively pull features from Laravel starter kits, read `skills/starter-kit-upgrade/SKILL.md` and follow its safety contract exactly.
- For ordinary API creation or backend feature work in an existing project, prefer the project's own backend skill/rules and existing code patterns before using any generic Laravel examples.

## Guardrails

- Preserve behavior. Refactor how code is expressed only when behavior and public contracts remain unchanged.
- Keep controllers thin and push business logic into existing service or action layers when that is already the local pattern.
- Use project validation commands after changes. If a local project defines a specific test/build command, use that command.
- Do not require a clean working tree for ordinary review or cleanup; only the nested starter-kit upgrade workflow requires it.
- Do not overwrite customized files, manifests, lockfiles, routes, middleware, auth setup, or generated API documentation without explicit task scope and verification.
