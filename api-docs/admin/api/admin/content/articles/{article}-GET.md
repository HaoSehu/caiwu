# {article}

**请求方法**：GET  
**请求路径**：`/api/admin/content/articles/{article}`  
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
| article | integer\|string | 是 | 路径参数；来自路由占位 `{article}` |

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
| data.id | integer | 真实调用返回字段 |
| data.content_type | string | 真实调用返回字段 |
| data.type | string | 真实调用返回字段 |
| data.type_label | string | 真实调用返回字段 |
| data.category_id | integer | 真实调用返回字段 |
| data.content_category_id | integer | 真实调用返回字段 |
| data.title | string | 真实调用返回字段 |
| data.slug | string | 真实调用返回字段 |
| data.summary | string | 真实调用返回字段 |
| data.excerpt | string | 真实调用返回字段 |
| data.content | string | 真实调用返回字段 |
| data.category_name | string | 真实调用返回字段 |
| data.category | string | 真实调用返回字段 |
| data.category_slug | string | 真实调用返回字段 |
| data.category_description | null | 真实调用返回字段 |
| data.category_detail | object | 真实调用返回字段 |
| data.category_detail.id | integer | 真实调用返回字段 |
| data.category_detail.name | string | 真实调用返回字段 |
| data.category_detail.slug | string | 真实调用返回字段 |
| data.category_detail.description | null | 真实调用返回字段 |
| data.category_detail.status | integer | 真实调用返回字段 |
| data.category_detail.sort_order | integer | 真实调用返回字段 |
| data.keywords | null | 真实调用返回字段 |
| data.cover_image | null | 真实调用返回字段 |
| data.status | integer | 真实调用返回字段 |
| data.status_label | string | 真实调用返回字段 |
| data.is_pinned | integer | 真实调用返回字段 |
| data.is_recommended | integer | 真实调用返回字段 |
| data.sort_order | integer | 真实调用返回字段 |
| data.view_count | integer | 真实调用返回字段 |
| data.publish_at | string | 真实调用返回字段 |
| data.last_published_at | string | 真实调用返回字段 |
| data.operator | string | 真实调用返回字段 |
| data.remark | string | 真实调用返回字段 |
| data.trace_id | string | 真实调用返回字段 |
| data.created_at | string | 真实调用返回字段 |
| data.updated_at | string | 真实调用返回字段 |
| data.creator | null | 真实调用返回字段 |
| data.updater | null | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "id": 11,
        "content_type": "help",
        "type": "help",
        "type_label": "帮助",
        "category_id": 6,
        "content_category_id": 6,
        "title": "win登录到远程Linux服务器",
        "slug": "winlinux-12",
        "summary": "Windows 登录远程 Linux 服务器指南 在 Windows 系统中，可以通过 SSH 协议登录远程 Linux 服务器。以下是基本操作步骤。 步骤 1：打开命令行工具 按下 Win + R，输入 cmd 或 powershell，然后回车，打开命令提示符或 PowerShell。 步骤 2：确认 SSH 客户端可用 在命令行中输入以下命令检查是否支持 ssh： ssh -V 如果显示版本信息（如 OpenSSHx.x），说明已",
        "excerpt": "Windows 登录远程 Linux 服务器指南 在 Windows 系统中，可以通过 SSH 协议登录远程 Linux 服务器。以下是基本操作步骤。 步骤 1：打开命令行工具 按下 Win + R，输入 cmd 或 powershell，然后回车，打开命令提示符或 PowerShell。 步骤 2：确认 SSH 客户端可用 在命令行中输入以下命令检查是否支持 ssh： ssh -V 如果显示版本信息（如 OpenSSHx.x），说明已",
        "content": "# Windows 登录远程 Linux 服务器指南\n\n在 Windows 系统中，可以通过 SSH 协议登录远程 Linux 服务器。以下是基本操作步骤。\n\n## 步骤 1：打开命令行工具\n\n按下 Win + R，输入 cmd 或 powershell，然后回车，打开命令提示符或 PowerShell。\n\n## 步骤 2：确认 SSH 客户端可用\n\n在命令行中输入以下命令检查是否支持 ssh：\n\nssh -V\n如果显示版本信息（如 OpenSSH_x.x），说明已安装。如果没有，需在“设置 → 应用 → 可选功能”中添加“OpenSSH 客户端”。\n\n## 步骤 3：使用 SSH 命令登录\n\n输入以下格式的命令：\n\nssh 用户名@服务器IP地址\n例如：\n\nssh root@192.168.1.100\n将“root”替换为你的 Linux 用户名，IP 地址替换为实际服务器地址。\n\n## 步骤 4：处理首次连接提示\n\n第一次连接时，会提示是否信任该主机，输入 yes 继续。\n\nThe authenticity of host '192.168.1.100' can't be established.\nAre you sure you want to continue connecting (yes/no)? yes\n\n## 步骤 5：输入密码\n\n按提示输入用户密码（输入时无显示），然后按回车。登录成功后，即可执行 Linux 命令。\n\n## 使用非默认端口\n\n如果服务器 SSH 端口不是 22，使用 -p 参数指定端口：\n\nssh -p 2222 root@192.168.1.100\n\n## 使用密钥登录（可选）\n\n如果你配置了 SSH 密钥，确保私钥文件（如 id_rsa）放在 C:\Users\你的用户名\.ssh\ 目录下，ssh 命令会自动使用。\n\n## 退出登录\n\n完成操作后，输入 exit 或 logout 即可断开连接。\n\n以上就是在 Windows 上登录远程 Linux 服务器的基本方法。",
        "category_name": "帮助",
        "category": "帮助",
        "category_slug": "help-5",
        "category_description": null,
        "category_detail": {
            "id": 6,
            "name": "帮助",
            "slug": "help-5",
            "description": null,
            "status": 1,
            "sort_order": 0
        },
        "keywords": null,
        "cover_image": null,
        "status": 1,
        "status_label": "已发布",
        "is_pinned": 0,
        "is_recommended": 0,
        "sort_order": 0,
        "view_count": 534,
        "publish_at": "2025-08-23 17:02:07",
        "last_published_at": "2025-08-23 17:02:07",
        "operator": "mofang-migration",
        "remark": "魔方公告/帮助迁移",
        "trace_id": "mofang-content-12",
        "created_at": "2025-08-23 17:02:43",
        "updated_at": "2026-07-05 16:34:03",
        "creator": null,
        "updater": null
    },
    "timestamp": 1783240484
}
```

### 调用记录
· 调试时间：2026-07-05 16:34:44  
· 响应状态码：200  
· 调用方式：GET /api/admin/content/articles/{article}  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\ContentArticleController@show`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:content.list`
