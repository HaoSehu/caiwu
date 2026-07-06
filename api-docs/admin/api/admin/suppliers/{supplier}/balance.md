# balance

**请求方法**：GET  
**请求路径**：`/api/admin/suppliers/{supplier}/balance`  
**调试状态**：⬜ 待调试

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| supplier | string | 是 | 路径参数；来自路由占位 `{supplier}` |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0，失败为非 0 |
| message | string | 响应消息；成功默认“操作成功” |
| data | object\|array\|null | 业务数据；具体结构见 data.* 字段 |
| timestamp | integer | Unix 秒级时间戳 |
| data.supplier_id | integer | 业务字段；由源码静态提取 |
| data.supplier_name | string | 业务字段；由源码静态提取 |
| data.balance | string(decimal) | 业务字段；由源码静态提取 |
| data.client | string | 业务字段；由源码静态提取 |
| data.connection_status | array | 业务字段；由源码静态提取 |
| data.connection_message | string | 业务字段；由源码静态提取 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "余额获取成功",
    "data": {
        "supplier_id": 1,
        "supplier_name": "string",
        "balance": "0.00",
        "client": "string",
        "connection_status": [],
        "connection_message": "string"
    },
    "timestamp": 1760000000
}
```

### 调用记录
· 调试时间：待调试后补充  
· 响应状态码：待调试后补充  
· 验证方式：未真实调用；根据代码文件补充  
· 未调用原因：接口可能消费令牌、触发外部调用、修改配置或启动业务流程，按源码补充，未真实调用

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\SupplierController@balance`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:supplier.sync`
