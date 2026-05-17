# API 格式规范

- 文档性质：现行方向
- 对齐时间：`2026-04-23`
- 读者画像：后端开发、前端联调、接口测试、文档维护人员
- 适用范围：`backend` 下所有返回 JSON 的 HTTP API，主要覆盖 `/api/admin/*`、`/api/client/*`、`/api/site/*`

## 1. 文档目标

本文定义本仓库后端 API 的统一返回格式、错误码区间、分页结构、校验失败结构和新增接口的落地约束。

发生冲突时按以下顺序处理：

1. 当前代码真实行为
2. `AGENTS.md`
3. 本文档
4. 旧接口习惯或历史蓝图

## 2. 统一响应外层

除文件流、受控资源查看等非 JSON 响应外，API 统一返回以下外层结构：

```json
{
  "code": 0,
  "message": "操作成功",
  "data": {},
  "timestamp": 1760000000
}
```

字段说明：

- `code`
  - 业务码
  - 成功固定为 `0`
  - 失败为非 `0` 整数
- `message`
  - 给前端和联调使用的可读提示
- `data`
  - 业务数据
  - 可为对象、数组、标量或 `null`
- `timestamp`
  - Unix 秒级时间戳

当前实现来源：

- `backend/app/Support/ApiResponseBuilder.php`
- `backend/app/Traits/ApiResponse.php`

## 3. 成功响应规范

### 3.1 普通成功

```json
{
  "code": 0,
  "message": "操作成功",
  "data": {
    "id": 123,
    "name": "示例"
  },
  "timestamp": 1760000000
}
```

### 3.2 无返回数据

用于删除、退出、纯状态切换等操作：

```json
{
  "code": 0,
  "message": "已退出登录",
  "data": null,
  "timestamp": 1760000000
}
```

### 3.3 列表/分页成功

标准分页结构放在 `data` 内：

```json
{
  "code": 0,
  "message": "操作成功",
  "data": {
    "list": [],
    "total": 0,
    "page": 1,
    "page_size": 20
  },
  "timestamp": 1760000000
}
```

分页字段含义：

- `list`: 当前页数据
- `total`: 总条数
- `page`: 当前页码
- `page_size`: 当前每页数量

当前标准分页由 `ApiResponseBuilder::pagination(...)` 生成。

## 4. 失败响应规范

失败时外层结构不变，只是 `code != 0`。

```json
{
  "code": 40400,
  "message": "商品不存在或已下架",
  "data": null,
  "timestamp": 1760000000
}
```

### 4.1 业务码到 HTTP 状态码映射

默认映射规则：

| 业务码区间 | 默认 HTTP 状态 |
| --- | --- |
| `50000+` | `500` |
| `42200+` | `422` |
| `40900+` | `409` |
| `40400+` | `404` |
| `40300+` | `403` |
| `40100+` | `401` |
| `40000+` | `400` |

说明：

- 当前映射逻辑位于 `ApiResponseBuilder::httpStatusForErrorCode(...)`
- 个别接口可显式覆盖 HTTP 状态码，例如回调验签失败当前返回 `code=40001`，HTTP 状态为 `401`

### 4.2 当前常见业务码

| 业务码 | 语义 | 当前常见来源 |
| --- | --- | --- |
| `0` | 成功 | 全局统一 |
| `40001` | 签名验证失败 | `VerifyCallbackSignature` |
| `40100` | 未登录、未认证或登录已过期 | 认证异常、权限中间件 |
| `40300` | 无权限、角色不匹配、账号被禁用 | `ensure.admin` / `ensure.client` / `permission` |
| `40301` | 邮箱验证或实名认证前置条件未满足 | `EnsureEmailIsVerified` |
| `40400` | 资源不存在 | 控制器内资源查询失败 |
| `42200` | 参数验证失败或一般业务校验失败 | `ValidationException`、`BusinessException` |
| `50000` | 通用服务端错误 | 未分类服务异常 |

## 5. 参数校验失败格式

Laravel `ValidationException` 已统一改写为 JSON：

```json
{
  "code": 42200,
  "message": "参数验证失败",
  "data": {
    "errors": {
      "email": [
        "邮箱不能为空"
      ],
      "page_size": [
        "page size 必须大于 0"
      ]
    }
  },
  "timestamp": 1760000000
}
```

约定：

- HTTP 状态码固定 `422`
- 详细字段错误放在 `data.errors`
- `errors` 的值保持 Laravel 原生数组结构，前端不要假设只有一条消息

当前实现来源：`backend/bootstrap/app.php`

## 6. 鉴权与权限相关格式

### 6.1 Token 认证

管理端与用户端都通过 Header 传 Bearer Token：

```http
Authorization: Bearer <token>
Accept: application/json
```

### 6.2 认证失败

示例：

```json
{
  "code": 40100,
  "message": "未登录或登录已过期",
  "data": null,
  "timestamp": 1760000000
}
```

### 6.3 权限不足/角色不匹配

示例：

```json
{
  "code": 40300,
  "message": "无操作权限",
  "data": null,
  "timestamp": 1760000000
}
```

### 6.4 前置条件不满足

例如邮箱未验证或未完成实名认证：

```json
{
  "code": 40301,
  "message": "请先完成实名认证",
  "data": null,
  "timestamp": 1760000000
}
```

## 7. 分页与查询参数规范

### 7.1 标准分页参数

新增接口统一使用：

- `page`
- `page_size`

示例：

```http
GET /api/admin/products?page=1&page_size=20
```

### 7.2 兼容参数

当前代码里有少量历史兼容：

- 部分接口仍接受 `per_page`

规则：

- 新接口不要再新增 `per_page`
- 老接口若已兼容 `per_page`，在不破坏前端的前提下可以继续兼容
- 返回结构统一落到 `page_size`
- 如需兼容旧前端，可额外返回 `per_page`，但这不是新增接口的默认要求

## 8. 字段与命名规范

### 8.1 通用命名

- URL 路径：小写、按资源语义命名
- 请求字段：`snake_case`
- 响应字段：`snake_case`
- 表名、字段名、筛选参数名保持一致优先

### 8.2 布尔参数

如果控制器通过 `booleanQuery(...)` 读取 query 参数，应接受常见布尔表达：

- `1` / `0`
- `true` / `false`
- `on` / `off`
- `yes` / `no`

非法布尔值应抛出校验异常，统一进入 `42200`

## 9. 文件上传与非 JSON 例外

### 9.1 文件上传

上传类接口请求体允许使用 `multipart/form-data`，但响应仍应回到统一 JSON 外层。

### 9.2 非 JSON 例外

以下接口可不走统一 JSON 外层：

- 直接文件流响应
- 受控资源预览
- WebSocket

当前典型例外：

- `/api/secure-assets/view`

除这些例外，普通 HTTP API 不应返回裸数组、裸字符串或自定义包裹结构。

## 10. 新增接口落地规则

新增 API 时遵循以下实现方式：

1. 控制器保持薄层，只做参数接收、鉴权、调用服务、返回结果。
2. 成功响应优先使用：
   - `$this->success(...)`
   - `$this->paginate(...)`
3. 失败响应优先使用：
   - `$this->error(...)`
   - `throw new BusinessException(...)`
4. 参数校验优先使用 `FormRequest`。
5. 列表接口默认提供 `page`、`page_size`。
6. 不要手写一套与统一外层不同的 JSON 结构。

### 10.1 推荐控制器写法

```php
public function index(ListProductsRequest $request)
{
    $paginator = $this->productService->paginate($request->validated());

    return $this->paginate($paginator, ProductResource::class);
}

public function show(int $id)
{
    $product = $this->productService->findVisible($id);

    if (! $product) {
        return $this->error(40400, '商品不存在或已下架');
    }

    return $this->success(new ProductResource($product));
}
```

## 11. 联调注意事项

- 前端成功判断以 `code === 0` 为准，不要只看 HTTP 200
- 前端失败提示优先读取 `message`
- 表单校验错误优先读取 `data.errors`
- 新接口变更字段名或嵌套层级时，必须同步更新前端调用
- 自动化或第三方回调接口如果要覆盖 HTTP 状态码，必须保持业务码和 `message` 仍可读

## 12. 维护建议

- 返回格式发生变化时，优先更新本文，再更新前后端调用
- 路由清单变化后，重刷 `文档/后端/后端API清单.md`
- 若新增特殊错误码区间，应同步补充本文第 4 节
