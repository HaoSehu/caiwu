---
name: caiwu-project-orientation
description: Orient Codex inside the Caiwu repository before project work. Use when starting tasks in C:\Users\Admin\Desktop\caiwu, choosing which app to edit, checking current directories, avoiding stale frontend names, updating docs, cleaning generated or legacy files, or deciding validation commands.
---

# Caiwu Project Orientation

Start by reading `AGENTS.md`, then the narrow document for the task:

- Current architecture: `文档/开发文档/架构/架构现状说明.md`
- Startup commands: `启动指南.md`
- General development rules: `开发规范.md`
- Visual rules: `页面风格.md`
- Frontend rules: `文档/开发文档/前端/前端项目规范.md`
- Backend API rules: `文档/开发文档/后端/API格式规范.md`
- Backend directory rules: `文档/开发文档/后端/后端目录分类规范.md`

If a referenced document path is missing, search under `文档/开发文档/` before treating the rule as obsolete.

## Current Directories

Use only current real app directories:

- `backend`
- `frontend-admin-v3`
- `frontend-user-v3-www`
- `frontend-user-v4-console`
- `shared`

Treat `frontend-admin`, `frontend-client`, `frontend-user-v3-console`, `frontend-www-v2`, and `frontend-console-v2` as stale historical references unless the user explicitly asks about history.

## Hard Rules

- Keep edited text UTF-8.
- Use `127.0.0.1` for local URLs; do not introduce `localhost`.
- Start the backend with `php artisan app:serve`, not `php artisan serve`.
- Do not create ad hoc documents in the repo root. Put non-rule docs under `文档/`.
- Do not hand edit generated `文档/开发文档/后端/后端API清单.md`; update `文档/开发文档/后端/API清单导航.md` for navigation changes or regenerate with `php backend/scripts/export_api_inventory.php`.
- For repo-local skill maintenance, compare current `AGENTS.md`, `.claude/skills`, `.codex/skills`, and `.github/agents` when relevant, but keep edits scoped to the requested tree.

## Workflow

1. Check `git status --short` before editing.
2. Ignore unrelated dirty work; never revert changes you did not make.
3. Prefer `rg` / `rg --files` for discovery.
4. Keep edits small and in the established domain/module.
5. After code changes, run the affected validation from the docs.

## Validation Quick Map

- Backend: `cd backend && php artisan test`
- Admin v3: `cd frontend-admin-v3 && npm run build`
- WWW/user entry: `cd frontend-user-v3-www && npm run build`
- User console v4: `cd frontend-user-v4-console && npm run build`
- Shared: `npm run typecheck:shared && npm run test:shared`

For frontend refactors, run the app's `npm run verify:refactor` when available.
For docs or skill-only changes, self-check paths and rules against current files; no build or test command is required.
