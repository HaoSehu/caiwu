---
status: current
updated: 2026-11-07
owner: backend-platform
---

# Apifox 鉴权与默认模板

## 项目级默认（新建接口自动继承）

配置在 `projectSettings`（projectId `9019820`），对所有分支共享。

| 项 | 值 | 说明 |
| --- | --- | --- |
| 默认鉴权 | `auth.type = bearer`，`token = {{access_token}}` | 新建接口默认按 Bearer 鉴权，调试时从环境变量取 token |
| 默认响应模板 | 统一信封 | `code / message / data / timestamp / request_id`，新建响应按此结构生成 |
| 模板参数 | id / category / name / tags / status | Apifox 默认 Mock 字段集，保留原样 |
| `autoGenerateRequestBody` | `true` | 新建接口时按参数自动生成请求体 |

## 安全方案

| 名称 | id | 类型 | 配置 |
| --- | --- | --- | --- |
| 管理端/客户端 Bearer Token | `1979460` | `authType: bearer` | `authConfigs: { type: http, scheme: bearer, bearerFormat: "Sanctum 明文 Token" }` |

对应后端 `Illuminate\Auth\Middleware\Authenticate:sanctum` 与 `Authorization: Bearer <token>`。

## 接口级绑定

以 `route:list --json` 的中间件为准，共 400 条路由：

| 分类 | 数量 | Apifox 上的写法 |
| --- | --- | --- |
| 需要 Bearer Token（`Authenticate:sanctum`） | 366 | `auth.type = securityscheme` + `securityScheme.schemeGroups[0].schemeIds = [1979460]` |
| 公开（无认证中间件） | 37 | `auth.type = noauth` |

公开接口集中在登录、验证码、回调、站点公开数据与健康检查，例如 `admin/login`、`client/auth/captcha-config`、`site/config`、`api/health`（共 37 个，含 2 个健康检查与 1 个签名校验资源）。

接口上**显式写 `noauth` 而不是留空**，是为了让「确实公开」和「忘记配置」在界面上可区分。

## 默认前后置处理器（可选，未启用）

以下模板常用于统一注入与断言，当前**没有批量写入**，因为断言会改变接口调试的失败判定口径：

```javascript
// 前置脚本：从环境变量注入 Token
const token = pm.environment.get('access_token');
if (token) {
  pm.request.headers.upsert({ key: 'Authorization', value: 'Bearer ' + token });
}
```

```text
// 后置断言
httpCode equals 200
$.code equals 0
```

需要启用的分支可以在 Apifox「项目设置 → 前后置操作」里配置，或按接口单独添加。

## 调试前要做的

1. 在 Apifox「环境管理」里建一个环境，变量 `access_token` 填真实登录 Token。
2. `{{access_token}}` 会同时用于项目默认鉴权和前置脚本。

## 重新同步

路由中间件变更后，按上表重新绑定。约束：

- 接口级鉴权必须同时写 `auth` 和 `securityScheme`，只写 `auth.type` 不会真正挂上方案。
- `noauth` 只能显式写，不能靠留空表达。
- 改项目默认鉴权会影响新建接口，不影响已存在的接口。

## 终验结果（2026-11-07）

```
接口总数 403
auth.type 分布: {"securityscheme":366,"noauth":37}
问题接口: 0
```

抽查：
- `GET /admin/users` → `securityscheme`，`schemeIds = [1979460]`
- `GET /site/config` → `noauth`
- `GET /api/health` → `noauth`
