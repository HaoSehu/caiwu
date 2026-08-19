---
name: 任务/工作项
about: 追踪跨端迁移、重构、技术债或一次性实施工作
title: "[Task] 简述任务"
labels: task
assignees: ""
---

## 目标

<!-- 本次任务要交付的最终结果 -->

## 背景与动机

<!-- 为什么需要做这件事？相关上下文 -->

## 影响范围

- [ ] `backend`（API、业务服务、插件、命令）
- [ ] `frontend-admin-v3`（管理端）
- [ ] `frontend-user-v3-www`（官网与用户入口）
- [ ] `frontend-user-v4-console`（用户控制台）
- [ ] `shared`（跨端共享）
- [ ] `docs`（文档）

## 实施步骤

- [ ]
- [ ]
- [ ]

## 验证清单

<!-- 按受影响范围填写最小验证命令及结果 -->

- [ ] `backend`：`php artisan test`
- [ ] `frontend-admin-v3`：`npm run build`
- [ ] `frontend-user-v3-www`：`npm run build`
- [ ] `frontend-user-v4-console`：`npm run build`
- [ ] `shared`：`npm run typecheck:shared && npm run test:shared`
- [ ] `docs`：`npm run docs:check`

## 风险与注意事项

<!-- 迁移、上线/回滚、破坏性变更、依赖项、待决策事项等 -->

## 关联文档/参考资料

<!-- 设计文档、执行计划、接口规范等 -->
