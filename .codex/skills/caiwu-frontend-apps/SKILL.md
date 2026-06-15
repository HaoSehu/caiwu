---
name: caiwu-frontend-apps
description: Work on Caiwu frontend apps and shared UI code. Use for Vue pages, routing, API calls, styles, TDesign admin, TDesign user console, Element Plus website/user entry, shared status/runtime components, frontend builds, and visual consistency in frontend-admin-v3, frontend-user-v3-www, frontend-user-v4-console, or shared.
---

# Caiwu Frontend Apps

Read first:

- `AGENTS.md`
- `文档/前端/前端项目规范.md`
- `页面风格.md`
- For backend contracts: `文档/后端/API格式规范.md`

## App Selection

- `frontend-admin-v3`: admin console, TDesign Vue Next, TypeScript.
- `frontend-user-v3-www`: website/login/user entry, Element Plus.
- `frontend-user-v4-console`: user console, TDesign Vue Next, TypeScript.
- `shared`: cross-app status/runtime/content/user-console components.

Do not target missing old paths: `frontend-admin`, `frontend-client`, `frontend-user-v3-console`.

## Implementation Rules

- Use Vue 3 `script setup` and Composition API.
- API calls go through each app's `src/api/*` and request/runtime utilities.
- Auth/session/token storage goes through existing utilities/stores.
- Move complex page logic into `domains`, `composables`, `features`, `services`, or `utils`.
- User-visible text must be Simplified Chinese.
- Show loading, empty, error, and disabled/submitting states for real workflows.
- Status labels and mappings should come from `@caiwu/shared` or `shared/user-v3`.

## UI Boundaries

- TDesign apps (`frontend-admin-v3`, `frontend-user-v4-console`): use `tdesign-vue-next` and `tdesign-icons-vue-next`; never add Element Plus.
- Element Plus app (`frontend-user-v3-www`): use Element Plus and `@element-plus/icons-vue`; do not add TDesign for normal pages.
- Admin and console pages stay light, work-focused, and dense enough for repeated use.
- Website and login pages may be more expressive, but should not leak Hero-style decoration into ordinary business pages.

## Validation

```bash
cd frontend-admin-v3 && npm run build
cd frontend-user-v3-www && npm run build
cd frontend-user-v4-console && npm run build
npm run typecheck:shared && npm run test:shared
```

Run only the affected commands for small changes; run app `verify:refactor` after structural refactors.
