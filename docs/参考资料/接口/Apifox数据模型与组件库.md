---
status: current
updated: 2026-11-07
owner: backend-platform
---

# caiwu 创欧云 API — 数据模型与组件库

本项目的「数据模型」与「响应组件」不是手工编造，而是从后端源码静态提取的。

## 数据模型（117 个，1601 个顶层字段）

来源：`backend/app/Http/Resources/**/*.php` 的 `toArray()` 返回数组。

- 提取方式：解析每个 Resource 的 `return [...]` 字面量，取顶层键与值表达式，按 `(int)` / `(float)` / `(bool)` / `->map()` / `::collection` 推断类型；`extends` 的 Resource 会合并父类字段。
- 命名：保持 Resource 类名（如 `AdminUserListItemResource`），便于直接对照源码。
- `displayName` 去掉了 Resource 后缀，`description` 记录完整源码路径。

**字段类型的边界：** 静态推断只能区分 integer / number / boolean / string / array / object。嵌套对象在无法静态确定子字段时标为 `object`（Apifox 中显示为空对象），数组元素标为 `object`。动态结构（如 `AdminProductOperationPayloadResource` 经 `sanitizeArray` 运行时脱敏、`SiteConfigResource` 从 `const FIELDS` 白名单生成）无法静态提取，因此没有建模型。

**模型间引用：**

| 父模型 | 属性 | 引用 |
| --- | --- | --- |
| `SiteHomeOverviewResource` | `hero` | `SiteHomeHeroResource` |
| `ClientLedgerSummaryResource` | `financeLedger` | `FinanceLedgerResource` |

引用使用 Apifox 的 `$ref: "#/definitions/{schemaId}"`，改动被引用模型时下游自动同步。

## 响应组件（7 个）

来源：`backend/app/Support/ApiResponseBuilder.php` 与 `backend/app/Traits/ApiResponse.php`。

| 组件 | HTTP | 用途 |
| --- | --- | --- |
| 统一响应 - 操作成功（无数据） | 200 | 删除、退出、纯状态切换，`data` 为 `null` |
| 统一响应 - 列表分页 | 200 | `data` 为 `{ list, total, page, page_size }` |
| 统一响应 - 参数校验失败 | 422 | `data.errors` 为字段名 → 消息数组。**组件 id 342592997**，被 169 个接口引用 |
| 统一响应 - 认证失败 | 401 | `code 40100` |
| 统一响应 - 权限不足 | 403 | `code 40300`；账户策略前置条件为 40301/40302/40303 |
| 响应 - AdminProductSummaryResource | 200 | `data` 直接引用该模型 |
| 响应 - ClientUserInfoResource | 200 | `data` 直接引用该模型 |

## 安全方案（1 个）

`管理端/客户端 Bearer Token`：`authType: bearer`，`authConfigs.type: http`、`scheme: bearer`。对应后端 `auth:sanctum` 与 `Authorization: Bearer <token>`。

## 公共参数（2 组）

| 名称 | 内容 |
| --- | --- |
| 通用分页参数 | query `page`（默认 1）、`page_size`（默认 20，最大 100） |
| 通用请求头 | header `Accept: application/json`、`X-Request-Id`（可选） |

## 接口绑定情况

177 条路由能静态确定返回的 Resource，其中 **169 条**绑定了具体数据模型（其余 8 条返回的是动态结构 Resource，见上文边界说明）。其余 226 条路由返回服务层原始数据，没有固定结构可提取。

绑定后每条接口的成功响应为：

- `data` 指向对应数据模型（分页接口则为 `list` 数组元素指向该模型）
- 追加复用的 401 / 403 / 422 错误响应组件（`/site/*` 公开接口不追加，因为它们不需要鉴权）

**已知边界：** `AdminProductOperationPayloadResource`（运行时脱敏，字段由输入决定）与 `SiteConfigResource`（从 `const FIELDS` 白名单生成）没有固定字段可建模，这 8 条接口的 `data` 保持通用 `object`。

## 重新同步

Resource 源码变动后，重跑提取脚本即可。关键约束：

- 一个 Resource 被多个接口使用时，不要给它建多个模型。
- `extends` 的 Resource 必须合并父类字段，否则详情接口会缺失列表字段。
- 修改已有模型时，`jsonSchema` 是整体替换，必须先读再改。
- 删除模型不会清理接口里的 `$ref`，会留下悬空引用——删除前先查引用方（本次就曾因误删 422 组件导致 177 个接口悬空，已重建并重新绑定）。
- 删除响应组件同样不会清理接口的 `reference`，务必先迁移引用。
