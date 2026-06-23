# Claude Code 项目规则

本文件与 `AGENTS.md` 共享同一套规则，避免维护两份。

所有项目规则、技术基线、开发约定、禁止项等，统一以 `AGENTS.md` 为准。

## Agent Skills 集成

项目已安装 `addyosmani/agent-skills` 的完整技能集：

### Skills (24 个)

位于 `.claude/skills/`，覆盖完整开发生命周期：

| 阶段 | 技能 |
|------|------|
| **Define** | `spec-driven-development`, `idea-refine`, `interview-me` |
| **Plan** | `planning-and-task-breakdown` |
| **Build** | `incremental-implementation`, `test-driven-development`, `frontend-ui-engineering`, `api-and-interface-design` |
| **Verify** | `debugging-and-error-recovery`, `browser-testing-with-devtools` |
| **Review** | `code-review-and-quality`, `code-simplification`, `security-and-hardening`, `performance-optimization` |
| **Ship** | `shipping-and-launch`, `documentation-and-adrs`, `observability-and-instrumentation` |

### Slash Commands (8 个)

位于 `.claude/commands/`：

| 命令 | 功能 |
|------|------|
| `/spec` | 编写结构化规格说明 |
| `/plan` | 分解任务为可验证的小步骤 |
| `/build` | 增量实现下一个任务 |
| `/test` | TDD 工作流：红、绿、重构 |
| `/review` | 五轴代码审查 |
| `/code-simplify` | 简化代码而不改变行为 |
| `/ship` | 上线前检查清单 |
| `/webperf` | Web 性能审计 |

### Agent Personas (4 个)

位于 `.claude/agents/`：

- `code-reviewer` — 代码审查专家
- `test-engineer` — 测试工程师
- `security-auditor` — 安全审计专家
- `web-performance-auditor` — Web 性能审计专家

### 自动激活规则

当任务匹配技能时，Claude Code 应自动调用对应技能：

- 新功能 → `spec-driven-development` → `incremental-implementation` + `test-driven-development`
- Bug 修复 → `debugging-and-error-recovery` → `test-driven-development`
- 代码审查 → `code-review-and-quality`
- 重构 → `code-simplification`
- UI 工作 → `frontend-ui-engineering`
