## 关联 Issue

<!-- 填写关联的 Issue 编号，如 #123；无则填「无」 -->

## 改动摘要

<!-- 简要描述本次改动内容与目的，按子任务分点列出 -->

-
-

## 影响范围

- [ ] `backend`（Laravel API、业务服务、插件、Artisan 命令）
- [ ] `frontend-admin-v3`（管理端）
- [ ] `frontend-user-v3-www`（官网与用户入口）
- [ ] `frontend-user-v4-console`（用户控制台）
- [ ] `app_flutter`（Android App）
- [ ] `shared`（跨端共享）

## 验证

<!-- 按受影响范围执行最小验证并填写结果 -->

- [ ] `backend`：`composer analyse && composer format:check`
- [ ] `backend`：`php artisan test`
- [ ] `frontend-admin-v3`：`npm run build`
- [ ] `frontend-user-v3-www`：`npm run build`
- [ ] `frontend-user-v4-console`：`npm run build`
- [ ] `app_flutter`：`flutter analyze && flutter test && dart run tool/check_no_webview.dart && dart run tool/check_visual_rules.dart`
- [ ] `shared`：`npm run typecheck:shared && npm run test:shared`

验证结果说明：

## 提交信息格式

<!-- 遵循 Conventional Commits：类型为 Feat / Fix / Docs / Refactor / Perf / Test / Chore / CI / Build / Revert，冒号后写中文描述 -->

```
Feat:中文描述
```

## 测试补充

<!-- 前端变更是否已追加对应 e2e 测试；后端变更是否已补充或调整单元测试 -->

- [ ] 已追加 / 调整测试
- [ ] 无需测试变更（说明原因）

## 风险与注意事项

<!-- 数据库迁移、上线 / 回滚风险、破坏性变更、待办事项等 -->
