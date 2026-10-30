---
status: current
updated: 2026-07-23
owner: docs-governance
---

# 文档治理策略

## 放置规则

- `设计文档/`：架构决策、方案、权衡和接口设计。
- `产品规格/`：用户问题、范围、非目标和验收标准。
- `执行计划/`：按 `进行中/`、`已完成/`、`技术债/` 管理的实施计划。
- `参考资料/`：稳定操作资料、外部协议和长期说明。
- `自动生成/`：脚本产物，文档内必须说明生成命令。
- 顶层领域文档：当前系统基线，避免为同一主题创建第二个“总说明”。
- `模板/`：新工件模板。建新文档从模板起手，不要另发明头部格式。

## 元数据规范

除索引文档（`README.md`、`index.md`）和 `模板/` 以外，`docs/` 下每份 Markdown 必须以 YAML frontmatter 开头，且只用这一种方式声明元数据：

```yaml
---
status: current
updated: 2026-08-19
owner: backend-platform
---
```

- `status`：见下方状态语义；执行计划可写中文 `进行中`，`catalog.json` 对应登记机器状态 `active`。
- `updated`：`YYYY-MM-DD`，指内容最后一次与实现对齐的日期，不是随手保存的日期。
- `owner`：责任角色。现用取值 `backend-platform`、`backend-data`、`frontend-platform`、`ops`、`product`、`docs-governance`；历史已完成计划保留当时的署名不改写。

正文里不再写 `文档性质`、`对齐时间`、`状态`、`日期`、`更新时间` 这类头部条目，改由 frontmatter 承载，`npm run docs:check` 会直接拦截。需要一句话说明文档讲什么，用 `文档摘要`；`读者画像`、`适用范围`、`范围` 等实质内容照常保留。自动生成物的 frontmatter 由生成脚本输出，不手工补。

## 状态真源

`catalog.json` 是状态的唯一真源。文档 frontmatter 与各索引表的状态列都是它的副本，`npm run docs:check` 会逐条比对三者，不一致即失败——改状态要同时改 `catalog.json` 和引用它的索引行。

## 生命周期

新需求先补产品规格或设计，再建立执行计划；实施中持续更新计划进度和决策日志；完成后记录验收与遗留风险并归档。仍对当前决策有解释价值的历史资料标为 `archived` 或 `needs-review`；已被当前真源替代、一次性且没有被现行计划引用的快照从 `docs/` 删除，通过 Git 历史回溯。

应用或插件源码旁的 `README.md`、`CHANGELOG.md`、`DEVELOPMENT.md` 只说明该模块的安装、接口或维护方式，可与代码同放；跨模块的方案、台账、验收记录和运行知识必须进入 `docs/`。

## 可验证性

[catalog.json](../catalog.json) 列出非索引文档的路径、状态、简介和复核期限。`npm run docs:check` 必须在合并前通过；提交涉及 `docs/` 时 `.husky/pre-commit` 会自动执行它。`npm run docs:freshness` 用于例行扫描，额外报告过期与“复核悬崖”（同一天到期记录超过 6 条）——分配 `review_by` 时按领域错峰，别让复核集中到同一天。Markdown 使用相对链接，不链接本机绝对路径，不把凭据写入文档。

## 生成与更新

生成物只能从源脚本更新。涉及路由、数据库结构、部署方式、接口契约或用户流程的代码改动，必须同步复核相关记录；若无文档变更，在执行计划或 PR 中说明理由。任何旧快照生成器不得覆盖当前真源。
