# options

**请求方法**：GET  
**请求路径**：`/api/client/services/{id}/reinstall/options`  
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
| id | integer\|string | 是 | 路径参数；来自路由占位 `{id}` |
| refresh | boolean | 否 | 查询参数；控制器通过 `booleanQuery()` 读取；未发现 FormRequest 明确规则 |

### 请求示例（完整 JSON）
```json
{
    "refresh": true
}
```

### 返回参数
| 参数名 | 类型 | 说明 |
|---|---|---|
| code | integer | 业务码；成功固定为 0 |
| message | string | 响应消息 |
| data | object | 业务数据 |
| data.os | array | 真实调用返回字段 |
| data.os.os_id | string | 真实调用返回字段 |
| data.os.name | string | 真实调用返回字段 |
| data.os.group_name | string | 真实调用返回字段 |
| data.os_groups | array | 真实调用返回字段 |
| data.os_groups.group_name | string | 真实调用返回字段 |
| data.os_groups.img | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "os": [
            {
                "os_id": "254250",
                "name": "CentOS-7.6.1810-x64",
                "group_name": "CentOS"
            },
            {
                "os_id": "254251",
                "name": "Ubuntu-18.04-x64",
                "group_name": "Ubuntu"
            },
            {
                "os_id": "254253",
                "name": "Windows-2012R2-Datacenter-cn",
                "group_name": "Windows"
            },
            {
                "os_id": "254254",
                "name": "Windows7_enterprise-cn",
                "group_name": "Windows"
            },
            {
                "os_id": "254255",
                "name": "Windows10-cn",
                "group_name": "Windows"
            },
            {
                "os_id": "254256",
                "name": "Windows-2008R2-Enterprise-cn",
                "group_name": "Windows"
            },
            {
                "os_id": "259747",
                "name": "Debian-9.12.1-x64",
                "group_name": "Debian"
            },
            {
                "os_id": "259749",
                "name": "Debian-10.3.3-x64",
                "group_name": "Debian"
            },
            {
                "os_id": "259751",
                "name": "Ubuntu-20.04.1-x64",
                "group_name": "Ubuntu"
            },
            {
                "os_id": "259753",
                "name": "CentOS-8-Stream-x64",
                "group_name": "CentOS"
            },
            {
                "os_id": "259755",
                "name": "Debian-11.1-x64",
                "group_name": "Debian"
            },
            {
                "os_id": "259757",
                "name": "CentOS-7.9.2111-x64",
                "group_name": "CentOS"
            },
            {
                "os_id": "259759",
                "name": "Ubuntu-22.04-x64",
                "group_name": "Ubuntu"
            },
            {
                "os_id": "391396",
                "name": "Ubuntu-16.04-x64",
                "group_name": "Ubuntu"
            },
            {
                "os_id": "391402",
                "name": "CentOS-7.8.2003-x64",
                "group_name": "CentOS"
            },
            {
                "os_id": "391404",
                "name": "CentOS-7.8.2003-x64-BT",
                "group_name": "CentOS"
            },
            {
                "os_id": "417252",
                "name": "Windows-2019-Datacenter-cn",
                "group_name": "Windows"
            },
            {
                "os_id": "417253",
                "name": "Windows-2003-Enterprise-cn",
                "group_name": "Windows"
            },
            {
                "os_id": "517372",
                "name": "Windows-2016-Datacenter-cn",
                "group_name": "Windows"
            },
            {
                "os_id": "569394",
                "name": "CentOS-9-Stream-x64",
                "group_name": "CentOS"
            },
            {
                "os_id": "577159",
                "name": "Debian-12.0_x64",
                "group_name": "Debian"
            },
            {
                "os_id": "704645",
                "name": "Ubuntu-24.04.1-x64",
                "group_name": "Ubuntu"
            }
        ],
        "os_groups": [
            {
                "group_name": "CentOS",
                "img": ""
            },
            {
                "group_name": "Ubuntu",
                "img": ""
            },
            {
                "group_name": "Windows",
                "img": ""
            },
            {
                "group_name": "Debian",
                "img": ""
            }
        ]
    },
    "timestamp": 1783240534
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:34  
· 响应状态码：200  
· 调用方式：GET /api/client/services/{id}/reinstall/options  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ServiceController@reinstallOptions`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api, auth:sanctum, ensure.client`
