# API 直接重构 Goal 模式提示词

将下面提示词作为 Goal 模式的任务输入。提示词刻意保持短格式，完整方案引用 `文档/开发文档/后端/API直接重构方案.md`。

```text
你是 Caiwu 项目的资深后端架构师和全栈执行代理。请进入 Goal 模式，目标是按文档完成 API 直接重构，并持续执行到目标完成或遇到真实阻塞。

必须先阅读并遵守：
- AGENTS.md
- 文档/开发文档/后端/API格式规范.md
- 文档/开发文档/后端/API直接重构方案.md
- 文档/开发文档/架构/架构现状说明.md
- api-docs/README.md

核心决策：
- 旧接口保留冻结，不做结构兼容，不在旧接口里添加新字段或兼容参数。
- 新接口直接重构，优先使用 /api/v2/admin、/api/v2/client、/api/v2/site 命名空间。
- 不改变核心业务逻辑，只重构 API 边界、请求校验、响应 Resource、字段投影和前端调用。
- 每个接口实现前必须查旧接口文档、路由、Controller、FormRequest、Resource、Service，禁止猜接口。

执行目标：
1. 按 `API直接重构方案.md` 的 P0、P1、P2 顺序执行。
2. P0 必须优先完成：
   - 优惠券商品树
   - 产品分组/分类/catalog
   - 产品列表/详情
   - 服务详情/连接信息
   - 工单详情/回复分页
3. 每组新接口必须完成：
   - v2 路由
   - FormRequest
   - Controller 薄层
   - Service/查询投影复用或新增
   - Resource/Response DTO
   - Feature 测试
   - 字段白名单和敏感字段测试
   - 响应大小或大响应防控测试
   - 对应前端 API 封装迁移
4. 每完成一个子任务，立即运行最小相关测试；全部相关子任务完成后运行完整受影响验证。

测试要求：
- 后端改动执行 `cd backend && php artisan test`，必要时先跑受影响测试文件。
- 后端格式敏感改动执行 `cd backend && php vendor\bin\pint --test`。
- 改 `frontend-admin-v3` 执行 `npm run build`。
- 改 `frontend-user-v3-www` 执行 `npm run build`；涉及重构收口再执行 `npm run verify:refactor`。
- 改 `frontend-user-v4-console` 执行 `npm run build`；涉及重构收口再执行 `npm run verify:refactor`。

验收标准：
- 新接口统一 `{code,message,data,timestamp}` 外层。
- 分页统一 `{list,total,page,page_size}`。
- 新接口不接受 `per_page`。
- 列表与详情 Resource 分离。
- 默认 JSON 响应低于 100KB；公开站点首屏低于 50KB；树选择器首包低于 70KB。
- 新接口默认不返回 `password`、`secret`、`api_key`、第三方原始响应。
- 用户端资源归属隔离，管理端权限码生效。
- 不物理删除 Payment，不别名 `mofang_finance_api`。

工作方式：
- 先输出一个基于当前代码核查后的执行计划，按 P0/P1/P2 拆分可提交任务。
- 然后从 P0 第一个任务开始实施。
- 仓库可能是脏工作区，禁止回滚非本人改动。
- 使用 CMD 和 UTF-8。
- 每个阶段报告已改文件、测试结果、剩余风险和下一步。
```

