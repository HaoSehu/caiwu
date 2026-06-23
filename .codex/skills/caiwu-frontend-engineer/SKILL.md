---
name: caiwu-frontend-engineer
description: Work as a senior Caiwu frontend engineer. Use for Vue 3 frontend implementation, review, refactoring, routing, API calls, shared UI/status components, visual consistency, performance, builds, and frontend engineering decisions across frontend-admin-v3, frontend-user-v3-www, frontend-user-v4-console, and shared.
---

# Caiwu Frontend Engineer

Work on Caiwu frontend apps with senior-engineer judgment. Prefer current code patterns over new abstractions.

## Required Skill Flow

1. Start with `caiwu-project-orientation` to confirm current app directories, dirty worktree rules, and validation commands.
2. Use `caiwu-frontend-apps` for app selection, UI framework boundaries, shared status/runtime rules, and frontend validation.
3. Use `caiwu-backend-api` or consult backend context when a change depends on API contracts, auth, payment/order/invoice data, permissions, or upstream response shape.
4. Inspect current files before deciding. Do not rely only on memory or skill text when exact paths or behavior matter.

## App Boundaries

- `frontend-admin-v3`: TDesign Vue Next, TypeScript, admin console. Do not add Element Plus or marketing-style hero/page-head cards.
- `frontend-user-v3-www`: Element Plus, website/login/user entry. Do not add TDesign for normal pages.
- `frontend-user-v4-console`: TDesign Vue Next, TypeScript, user console. Keep it light, restrained, and work-focused.
- `shared`: shared status/runtime/content/user-console components.
- Do not target stale paths: `frontend-admin`, `frontend-client`, `frontend-user-v3-console`, `frontend-www-v2`, or `frontend-console-v2`.

## Implementation Rules

- Use Vue 3 `script setup` and Composition API.
- Route requests through each app's existing `src/api/*` and request/runtime utilities.
- Route auth/session/token access through existing auth/runtime utilities, not direct storage calls.
- Keep pages thin. Move complex logic into `domains`, `composables`, `features`, `services`, or `utils`.
- Reuse `@caiwu/shared`, `shared/statusConfig.js`, `shared/extraStatusMaps.js`, `StatusTag.vue`, and `shared/user-v3` components when applicable.
- Use the app's existing style tokens and variables. Do not introduce a second UI system.
- Include loading, empty, error, disabled, submitting, and confirmation states for real workflows.
- Use `127.0.0.1` for local URLs; do not hardcode backend hosts or token keys.
- If PowerShell blocks `npm.ps1`, use `npm.cmd`.
- Keep `frontend-user-v4-console` pages under `src/pages/client/`; prefer `src/domains/`, `src/composables/`, and `src/api/` for business logic.

## Review Checklist

Check for:

- wrong app or stale directory target
- UI library mixing
- duplicated status mapping
- direct axios instance or direct localStorage/sessionStorage access
- page templates with too much business logic
- missing loading/empty/error/submitting states
- admin standalone page-head or hero cards
- console website-style hero/decorative layouts
- `any` or weak TypeScript in TS apps
- text/layout overflow on common viewports
- missing build or refactor verification

## Validation

Run the smallest affected command first, then broader validation if the change is structural:

```bash
cd frontend-admin-v3 && npm run build
cd frontend-user-v3-www && npm run build
cd frontend-user-v4-console && npm run build
npm run typecheck:shared && npm run test:shared
```

Use `npm run verify:refactor` in the affected frontend app after structural refactors when available.
