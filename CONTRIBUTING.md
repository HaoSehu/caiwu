# 贡献指南

感谢关注本项目。提交代码前请先阅读以下约定。

## 提交 Pull Request 之前

1. **先建 Issue**。较大的改动（新功能、重构、跨端调整）请先开 Issue 说明动机与影响范围，避免做完才发现方向不一致。
2. **保持改动最小**。一次 PR 只做一件事；不夹带无关的格式化、重命名或依赖升级。
3. **跑通受影响范围的验证**，并在 PR 描述里填写结果。各端的验证命令见 [README](README.md#-构建与验证)。

## 代码约定

### 通用

- 提交信息遵循 Conventional Commits，类型限定为 `Feat` / `Fix` / `Docs` / `Refactor` / `Perf` / `Test` / `Chore` / `CI` / `Build` / `Revert`，冒号后使用中文描述。
- 注释与用户可见文案使用中文。
- 不提交个人配置文件、临时脚本、日志、数据库备份或任何凭据。

### 后端（`backend/`）

- 控制器保持薄层，业务逻辑收敛到 `app/Services/`。
- 第三方调用只能出现在 `plugins/` 或专用上游层，控制器不直接发起外部 HTTP 请求。
- 数据库迁移只新增，不修改已发布的历史迁移。
- 插件按能力域放在 `plugins/{domain}/{slug}/`，不得注册系统级路由、调度或全局中间件。
- `payments` 相关记录禁止物理删除。

### 前端（`frontend-*/`）

- 统一使用 TDesign Vue Next，不混用其他 UI 框架。
- 设计令牌以根目录 `theme.css` 为唯一真源，不在组件中硬编码品牌色。
- 新页面需同时适配桌面与移动端断点。
- 交互改动需补充对应的 e2e 测试。

### Android App（`app_flutter/`）

- **禁止任何 WebView 或网页嵌套**，由 `tool/check_no_webview.dart` 强制校验。
- 视觉保持「平实移动端列表流」基线，禁止渐变头、浮卡、玻璃拟态等装饰性模板风格，由 `tool/check_visual_rules.dart` 校验。
- 提交前执行 `flutter analyze`、`flutter test` 与上述两个守卫脚本。

## 安全相关

- 发现安全漏洞**不要**创建公开 Issue，请走 [Security Advisory](https://github.com/HaoSehu/caiwu/security/advisories/new)，详见 [SECURITY.md](SECURITY.md)。
- 涉及资金、鉴权、凭据的改动请在 PR 中显著标注，便于优先审查。

## 许可

提交代码即表示同意以本项目的 [AGPL-3.0-or-later](LICENSE) 许可分发您的贡献。
