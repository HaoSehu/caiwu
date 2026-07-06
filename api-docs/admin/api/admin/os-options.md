# 操作系统选项列表（用于手动开通服务时选择系统）

**请求方法**：GET  
**请求路径**：`/api/admin/os-options`  
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
| data.groups | array | 真实调用返回字段 |
| data.groups.label | string | 真实调用返回字段 |
| data.groups.children | array | 真实调用返回字段 |
| data.groups.children.label | string | 真实调用返回字段 |
| data.groups.children.value | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "groups": [
            {
                "label": "CentOS",
                "children": [
                    {
                        "label": "CentOS 7",
                        "value": "CentOS 7"
                    },
                    {
                        "label": "CentOS 8",
                        "value": "CentOS 8"
                    },
                    {
                        "label": "CentOS 9",
                        "value": "CentOS 9"
                    }
                ]
            },
            {
                "label": "Ubuntu",
                "children": [
                    {
                        "label": "Ubuntu 20.04",
                        "value": "Ubuntu 20.04"
                    },
                    {
                        "label": "Ubuntu 22.04",
                        "value": "Ubuntu 22.04"
                    },
                    {
                        "label": "Ubuntu 24.04",
                        "value": "Ubuntu 24.04"
                    }
                ]
            },
            {
                "label": "Debian",
                "children": [
                    {
                        "label": "Debian 11",
                        "value": "Debian 11"
                    },
                    {
                        "label": "Debian 12",
                        "value": "Debian 12"
                    }
                ]
            },
            {
                "label": "Rocky Linux",
                "children": [
                    {
                        "label": "Rocky Linux 8",
                        "value": "Rocky Linux 8"
                    },
                    {
                        "label": "Rocky Linux 9",
                        "value": "Rocky Linux 9"
                    }
                ]
            },
            {
                "label": "AlmaLinux",
                "children": [
                    {
                        "label": "AlmaLinux 8",
                        "value": "AlmaLinux 8"
                    },
                    {
                        "label": "AlmaLinux 9",
                        "value": "AlmaLinux 9"
                    }
                ]
            },
            {
                "label": "Windows Server",
                "children": [
                    {
                        "label": "Windows Server 2019",
                        "value": "Windows Server 2019"
                    },
                    {
                        "label": "Windows Server 2022",
                        "value": "Windows Server 2022"
                    },
                    {
                        "label": "Windows Server 2025",
                        "value": "Windows Server 2025"
                    }
                ]
            }
        ]
    },
    "timestamp": 1783240511
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:11  
· 响应状态码：200  
· 调用方式：GET /api/admin/os-options  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\UserController@osOptions`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:user.manage`
