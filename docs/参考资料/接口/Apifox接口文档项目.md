---
status: current
updated: 2026-11-07
owner: backend-platform
---

# Apifox 接口文档项目

## 项目信息

| 项 | 值 |
| --- | --- |
| 项目名 | caiwu 创欧云 API |
| projectId | `9019820` |
| teamId | `4587262`（个人团队） |
| 主分支 branchId | `8830235`（main） |
| 主分支模块 moduleId | `8852868` |
| 接口总数 | 402（源路由 400 条；原 403，2026-11-08 删除 `/client/finance/ledger` 三条冗余接口） |
| 总览文档 docId | `9654609`（「00 API 总览与规范」） |

## 目录结构

| 文件夹 | folderId | 接口数 |
| --- | --- | --- |
| 管理端 | `99233685` | 254 |
| 客户端 | `99233686` | 127 |
| 站点 | `99233687` | 18 |
| 系统 | `99233688` | 3 |

> 说明：Apifox 接口树的「根目录」与顶层文件夹的 `parentId` 同为 `0`，无法用 `parentId` 区分，因此未建「范围 → 模块」二级子文件夹；模块信息体现在接口名与描述中（接口名格式 `{范围}/{模块} - {路径}`）。

## 数据来源与映射规则

Apifox 是接口文档的唯一载体（管理端 `/admin/notifications/api-directory` 页面已于 2026-11-07 下线，
配套的 `backend/scripts/generate_frontend_api_catalog.php` 与
`frontend-admin-v3/src/data/apiCatalog.generated.json` 一并删除）。

| 源 | Apifox |
| --- | --- |
| `php artisan route:list --json`（实时路由） | `path` / `method`（`GET|POST` 拆为两条） |
| `backend/app/Http/Requests/**` 的 `rules()` | GET/DELETE → `parameters.query`；其余 → `requestBody.jsonSchema` |
| 路径占位符 `{name}` | `parameters.path` |
| 中间件（`permission` / `throttle` / 签名校验）与控制器类名 | 接口 `description` |
| 响应 | 统一信封 `code / message / data / timestamp / request_id` + 成功示例 |

> 历史说明：上一版映射直接消费已删除的生成物 JSON。该 JSON 的字段（`callPath` / `requestParams` /
> `pathParams` / `access` / `permission` / `handler` / `prohibitedParams` 等）可由上述源按
> 本表规则原样推导，因此删除生成物不影响 Apifox 侧口径，只是改由 Apifox 直接对接源码与路由。

## 响应说明的边界

响应字段由后端 Resource 在运行时构造。**基础文档只描述统一信封，不伪造业务字段**；需要具体返回字段时看下方「数据模型与组件库」的总览文档，或直接读控制器与 Resource 源码。

## 数据模型与组件库

项目内已建立可复用的数据模型与响应组件，均由后端源码静态提取，详见 [Apifox 数据模型与组件库](Apifox数据模型与组件库.md)。

| 类别 | 数量 | 说明 |
| --- | --- | --- |
| 数据模型 | 117 | 来自 `backend/app/Http/Resources`，共 1601 个顶层字段（2026-11-08 按源码重建，原 2250） |
| 响应组件 | 10 | 5 个统一响应（成功/分页/422/401/403）+ 模型专属响应 |
| 安全方案 | 1 | Bearer Token（对应 auth:sanctum） |
| 公共参数 | 1 组 | page / page_size 分页参数 + Accept / X-Request-Id 请求头 |

截至 2026-11-08：
- **117 个数据模型**按 `toArray()` 源码重建（1601 个顶层字段，原 2250）
- **362 / 363 应鉴权接口**（99.7%）已复用 401/403/422 错误响应组件
- **40 个详情类接口**的 `data` 使用 `{ invoice }`、`{ product }` 等包装层，与控制器真实返回一致

## MCP 接入

Apifox 新版 MCP 走 Streamable HTTP：服务地址 `https://api.apifox.com/mcp`，请求头 `X-Apifox-Api-Version: 2025-09-01` 与 `Authorization: Bearer <令牌>`。可用工具为 `discoverMethods`（发现方法）、`readMethod` / `writeMethod` / `deleteMethod`（按风险分派的执行器）。

令牌属于凭据，只放在本机用户级配置里，**不得写入仓库的 `.mcp.json` 或任何文档**。本机接入位置：

- Claude Code：用户级 `~/.claude.json` 的 `mcpServers.apifox-new-mcp`（type `http`）。
- 桌面客户端：`claude_desktop_config.json` 中经 `supergateway` 桥接；客户端只在启动时读配置，改完必须完全退出再打开。`mcp-remote` 桥接不可用——Apifox 把 OAuth 探测路径 302 到帮助页，桥接会把 HTML 当 JSON 解析而崩溃。

## 强制同步规则

**Apifox 是接口文档的唯一载体。任何接口新增、删除、修改（路由、FormRequest 参数、Resource 响应字段、错误码、鉴权）都必须经 Apifox MCP 落到本项目，代码与 Apifox 两侧都完成才算完成。**

| 变更 | MCP 方法 | 约束 |
| --- | --- | --- |
| 新增接口 | `createEndpoint`（`writeMethod`） | `GET\|POST` 拆两条；folderId 按范围选；鉴权按中间件写 |
| 修改接口 | `getEndpoint` → `updateEndpoint`（`writeMethod`） | 先读全量；`responses` / `responseExamples` 整体替换 |
| 删除接口 | `deleteEndpoint`（`deleteMethod`） | `writeMethod` 会拒绝 destructive 方法 |
| 数据模型 | `createDataSchema` / `updateDataSchema` / `deleteDataSchema` | 删除前查引用方，不级联清理 |
| 响应组件 / 安全方案 / 公共参数 | 对应 component 方法 | 同上，改前先查引用 |

执行顺序：`discoverMethods` 确认方法名 → 读 workflow skill（`endpoint-authoring` / `component-payload-authoring`）→ 读 `schemaUri` → 调用执行器 → `getEndpoint` 回读确认。

MCP 不可用或同步失败时，必须在改动摘要里列出未同步的接口与原因，不得静默跳过。

## 重新同步方式

路由或请求类变更后，按本页映射规则重跑同步。关键约束：

- `createEndpoint` 的 `method` 必须是单个方法，`GET|POST` 要拆成两条。
- 删除接口必须走 `deleteMethod` 执行器（`writeMethod` 会拒绝 destructive 风险方法）。
- 更新接口的 `responses`/`responseExamples` 是整体替换而非增量，改前要先 `getEndpoint` 读全量。

## 相关文档

- [后端 API 清单（自动生成）](../../自动生成/接口/后端API清单.md)
- [API 清单导航](../接口/API清单导航.md)
- [API 格式规范](../接口/API格式规范.md)
