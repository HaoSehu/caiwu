# provider-types

**请求方法**：GET  
**请求路径**：`/api/admin/suppliers/provider-types`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 是 | 登录鉴权 |

### 请求参数
| 参数名 | 类型 | 必填 | 说明 |
|---|---|---|---|
| 无 | - | 否 | 无请求参数 |

### 请求示例（完整 JSON）
```json
{}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.list | array | 分页列表数据 |
| data.list.value | string | 真实调用返回字段 |
| data.list.label | string | 真实调用返回字段 |
| data.list.supplier_form | object | 真实调用返回字段 |
| data.list.supplier_form.fields | array | 真实调用返回字段 |
| data.list.supplier_form.fields.key | string | 真实调用返回字段 |
| data.list.supplier_form.fields.label | string | 真实调用返回字段 |
| data.list.supplier_form.fields.type | string | 真实调用返回字段 |
| data.list.supplier_form.fields.required | boolean | 真实调用返回字段 |
| data.list.supplier_form.fields.placeholder | string | 真实调用返回字段 |
| data.list.supplier_form.fields.description | string | 真实调用返回字段 |
| data.list.supplier_form.help | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "value": "kanghostx",
                "label": "康乐虚拟主机",
                "supplier_form": {
                    "fields": [
                        {
                            "key": "api_url",
                            "label": "康乐面板地址",
                            "type": "url",
                            "required": true,
                            "placeholder": "http://1.2.3.4:3312",
                            "description": "填写 WHM 面板根地址或 /api/index.php 完整地址。"
                        },
                        {
                            "key": "api_key",
                            "label": "访问密钥 accesshash",
                            "type": "password",
                            "required": true,
                            "secret": "***已脱敏***",
                            "placeholder": "编辑时留空则保持原密钥"
                        }
                    ],
                    "help": "康乐 WHM API 采用 accesshash 签名。这里仅保存面板连接信息，空间、数据库、流量等套餐规格请在商品目录的产品配置中维护。"
                }
            },
            {
                "value": "mofang_finance_api",
                "label": "魔方财务接口",
                "supplier_form": {
                    "fields": [
                        {
                            "key": "api_url",
                            "label": "魔方财务地址",
                            "type": "url",
                            "required": true,
                            "placeholder": "https://finance.example.com"
                        },
                        {
                            "key": "api_username",
                            "label": "登录账号",
                            "type": "text",
                            "required": true
                        },
                        {
                            "key": "api_key",
                            "label": "登录密码/API 密钥",
                            "type": "password",
                            "required": true,
                            "secret": "***已脱敏***",
                            "placeholder": "编辑时留空则保持原密钥"
                        }
                    ],
                    "help": "魔方财务插件使用供应商后台地址、账号和密码/API 密钥登录并刷新 JWT。"
                }
            }
        ]
    },
    "timestamp": 1783240517
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:17  
· 响应状态码：200  
· 调用方式：GET /api/admin/suppliers/provider-types  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\SupplierController@providerTypes`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:supplier.list`
