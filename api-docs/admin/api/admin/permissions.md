# permissions

**请求方法**：GET  
**请求路径**：`/api/admin/permissions`  
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
| data.list.key | string | 真实调用返回字段 |
| data.list.module | string | 真实调用返回字段 |
| data.list.module_label | string | 真实调用返回字段 |
| data.list.group | string | 真实调用返回字段 |
| data.list.group_label | string | 真实调用返回字段 |
| data.list.name | string | 真实调用返回字段 |
| data.list.description | string | 真实调用返回字段 |
| data.list.action | string | 真实调用返回字段 |
| data.list.action_label | string | 真实调用返回字段 |
| data.list.risk_level | string | 真实调用返回字段 |
| data.list.is_dangerous | boolean | 真实调用返回字段 |
| data.list.is_all | boolean | 真实调用返回字段 |
| data.list.sort | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "key": "*",
                "module": "system",
                "module_label": "系统",
                "group": "system_root",
                "group_label": "超级权限",
                "name": "全部权限",
                "description": "拥有全部后台权限",
                "action": "all",
                "action_label": "全部",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": true,
                "sort": 0
            },
            {
                "key": "staff.list",
                "module": "staff",
                "module_label": "员工",
                "group": "organization_staff",
                "group_label": "员工账号",
                "name": "查看员工",
                "description": "查看员工",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "staff.manage",
                "module": "staff",
                "module_label": "员工",
                "group": "organization_staff",
                "group_label": "员工账号",
                "name": "管理员工",
                "description": "管理员工",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "role.list",
                "module": "role",
                "module_label": "角色",
                "group": "organization_role",
                "group_label": "角色授权",
                "name": "查看角色",
                "description": "查看角色",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "role.manage",
                "module": "role",
                "module_label": "角色",
                "group": "organization_role",
                "group_label": "角色授权",
                "name": "管理角色",
                "description": "管理角色",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "permission.list",
                "module": "permission",
                "module_label": "权限",
                "group": "organization_permission",
                "group_label": "权限目录",
                "name": "查看权限目录",
                "description": "查看权限目录",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "dashboard.view",
                "module": "dashboard",
                "module_label": "工作台",
                "group": "dashboard_workbench",
                "group_label": "工作台",
                "name": "查看仪表盘",
                "description": "查看仪表盘",
                "action": "view",
                "action_label": "查看",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 10
            },
            {
                "key": "user.list",
                "module": "user",
                "module_label": "用户",
                "group": "customer_profile",
                "group_label": "客户资料",
                "name": "查看用户列表",
                "description": "查看用户列表",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "user.detail",
                "module": "user",
                "module_label": "用户",
                "group": "customer_profile",
                "group_label": "客户资料",
                "name": "查看用户详情",
                "description": "查看用户详情",
                "action": "detail",
                "action_label": "详情",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 30
            },
            {
                "key": "privacy.view_raw",
                "module": "privacy",
                "module_label": "隐私",
                "group": "customer_profile",
                "group_label": "客户资料",
                "name": "查看原始隐私信息",
                "description": "查看原始隐私信息",
                "action": "view_raw",
                "action_label": "查看原始隐私",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 89
            },
            {
                "key": "user.manage",
                "module": "user",
                "module_label": "用户",
                "group": "customer_profile",
                "group_label": "客户资料",
                "name": "管理用户",
                "description": "管理用户",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "user.login_as",
                "module": "user",
                "module_label": "用户",
                "group": "customer_profile",
                "group_label": "客户资料",
                "name": "代登录用户",
                "description": "代登录用户",
                "action": "login_as",
                "action_label": "login_as",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 99
            },
            {
                "key": "verification.list",
                "module": "verification",
                "module_label": "实名",
                "group": "customer_verification",
                "group_label": "实名审核",
                "name": "查看实名认证",
                "description": "查看实名认证",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "verification.unbind",
                "module": "verification",
                "module_label": "实名",
                "group": "customer_verification",
                "group_label": "实名审核",
                "name": "驳回解绑实名",
                "description": "驳回解绑实名",
                "action": "unbind",
                "action_label": "驳回/解绑",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 60
            },
            {
                "key": "invoice.list",
                "module": "invoice",
                "module_label": "账单",
                "group": "finance_invoice",
                "group_label": "账单管理",
                "name": "查看账单",
                "description": "查看账单",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "invoice.detail",
                "module": "invoice",
                "module_label": "账单",
                "group": "finance_invoice",
                "group_label": "账单管理",
                "name": "查看账单详情",
                "description": "查看账单详情",
                "action": "detail",
                "action_label": "详情",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 30
            },
            {
                "key": "invoice.manage",
                "module": "invoice",
                "module_label": "账单",
                "group": "finance_invoice",
                "group_label": "账单管理",
                "name": "管理账单",
                "description": "管理账单",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "order.list",
                "module": "order",
                "module_label": "订单",
                "group": "finance_order",
                "group_label": "订单管理",
                "name": "查看订单",
                "description": "查看订单",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "order.detail",
                "module": "order",
                "module_label": "订单",
                "group": "finance_order",
                "group_label": "订单管理",
                "name": "查看订单详情",
                "description": "查看订单详情",
                "action": "detail",
                "action_label": "详情",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 30
            },
            {
                "key": "order.manage",
                "module": "order",
                "module_label": "订单",
                "group": "finance_order",
                "group_label": "订单管理",
                "name": "管理订单",
                "description": "管理订单",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "user.recharge",
                "module": "user",
                "module_label": "用户",
                "group": "finance_funds",
                "group_label": "资金操作",
                "name": "用户充值",
                "description": "用户充值",
                "action": "recharge",
                "action_label": "充值",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 50
            },
            {
                "key": "finance.withdraw",
                "module": "finance",
                "module_label": "财务",
                "group": "finance_funds",
                "group_label": "资金操作",
                "name": "处理提现",
                "description": "处理提现",
                "action": "withdraw",
                "action_label": "提现",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 80
            },
            {
                "key": "finance.report",
                "module": "finance",
                "module_label": "财务",
                "group": "finance_report",
                "group_label": "财务报表",
                "name": "查看财务报表",
                "description": "查看财务报表",
                "action": "report",
                "action_label": "报表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 70
            },
            {
                "key": "ticket.list",
                "module": "ticket",
                "module_label": "工单",
                "group": "support_ticket",
                "group_label": "工单支持",
                "name": "查看工单",
                "description": "查看工单",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "ticket.reply",
                "module": "ticket",
                "module_label": "工单",
                "group": "support_ticket",
                "group_label": "工单支持",
                "name": "回复工单",
                "description": "回复工单",
                "action": "reply",
                "action_label": "回复",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 40
            },
            {
                "key": "ticket.manage",
                "module": "ticket",
                "module_label": "工单",
                "group": "support_ticket",
                "group_label": "工单支持",
                "name": "管理工单",
                "description": "管理工单",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "product.list",
                "module": "product",
                "module_label": "商品",
                "group": "product_catalog",
                "group_label": "商品配置",
                "name": "查看商品",
                "description": "查看商品",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "product.sync",
                "module": "product",
                "module_label": "商品",
                "group": "product_catalog",
                "group_label": "商品配置",
                "name": "同步商品",
                "description": "同步商品",
                "action": "sync",
                "action_label": "同步",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 82
            },
            {
                "key": "product.manage",
                "module": "product",
                "module_label": "商品",
                "group": "product_catalog",
                "group_label": "商品配置",
                "name": "管理商品",
                "description": "管理商品",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "supplier.list",
                "module": "supplier",
                "module_label": "供应商",
                "group": "product_supplier",
                "group_label": "供应商接口",
                "name": "查看供应商列表",
                "description": "查看供应商列表",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "supplier.detail",
                "module": "supplier",
                "module_label": "供应商",
                "group": "product_supplier",
                "group_label": "供应商接口",
                "name": "查看供应商详情",
                "description": "查看供应商详情",
                "action": "detail",
                "action_label": "详情",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 30
            },
            {
                "key": "supplier.sync",
                "module": "supplier",
                "module_label": "供应商",
                "group": "product_supplier",
                "group_label": "供应商接口",
                "name": "同步供应商数据",
                "description": "同步供应商数据",
                "action": "sync",
                "action_label": "同步",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 82
            },
            {
                "key": "supplier.secret_reveal",
                "module": "supplier",
                "module_label": "供应商",
                "group": "product_supplier",
                "group_label": "供应商接口",
                "name": "查看供应商密钥",
                "description": "查看供应商密钥",
                "action": "secret_reveal",
                "action_label": "查看密钥",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 88
            },
            {
                "key": "supplier.manage",
                "module": "supplier",
                "module_label": "供应商",
                "group": "product_supplier",
                "group_label": "供应商接口",
                "name": "管理供应商",
                "description": "管理供应商",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "content.list",
                "module": "content",
                "module_label": "内容",
                "group": "content_ops",
                "group_label": "内容运营",
                "name": "查看内容",
                "description": "查看内容",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "content.manage",
                "module": "content",
                "module_label": "内容",
                "group": "content_ops",
                "group_label": "内容运营",
                "name": "管理内容",
                "description": "管理内容",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "member_level.list",
                "module": "member_level",
                "module_label": "会员",
                "group": "marketing_growth",
                "group_label": "推广会员",
                "name": "查看会员等级",
                "description": "查看会员等级",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "referral.list",
                "module": "referral",
                "module_label": "推广",
                "group": "marketing_growth",
                "group_label": "推广会员",
                "name": "查看推广返利",
                "description": "查看推广返利",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "referral_withdrawal.list",
                "module": "referral_withdrawal",
                "module_label": "推荐提现",
                "group": "marketing_growth",
                "group_label": "推广会员",
                "name": "查看推荐提现",
                "description": "查看推荐提现",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "member_level.manage",
                "module": "member_level",
                "module_label": "会员",
                "group": "marketing_growth",
                "group_label": "推广会员",
                "name": "管理会员等级",
                "description": "管理会员等级",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "site.view",
                "module": "site",
                "module_label": "站点",
                "group": "site_ops",
                "group_label": "站点展示",
                "name": "查看站点展示",
                "description": "查看站点展示",
                "action": "view",
                "action_label": "查看",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 10
            },
            {
                "key": "site.manage",
                "module": "site",
                "module_label": "站点",
                "group": "site_ops",
                "group_label": "站点展示",
                "name": "管理站点展示",
                "description": "管理站点展示",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "settings.view",
                "module": "settings",
                "module_label": "设置",
                "group": "system_config",
                "group_label": "系统设置",
                "name": "查看系统设置",
                "description": "查看系统设置",
                "action": "view",
                "action_label": "查看",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 10
            },
            {
                "key": "settings.secret_reveal",
                "module": "settings",
                "module_label": "设置",
                "group": "system_config",
                "group_label": "系统设置",
                "name": "查看系统密钥",
                "description": "查看系统密钥",
                "action": "secret_reveal",
                "action_label": "查看密钥",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 88
            },
            {
                "key": "settings.manage",
                "module": "settings",
                "module_label": "设置",
                "group": "system_config",
                "group_label": "系统设置",
                "name": "管理系统设置",
                "description": "管理系统设置",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "integration_plugin.view",
                "module": "integration_plugin",
                "module_label": "插件",
                "group": "system_integration",
                "group_label": "集成插件",
                "name": "查看集成插件",
                "description": "查看集成插件",
                "action": "view",
                "action_label": "查看",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 10
            },
            {
                "key": "integration_plugin.test",
                "module": "integration_plugin",
                "module_label": "插件",
                "group": "system_integration",
                "group_label": "集成插件",
                "name": "测试集成插件",
                "description": "测试集成插件",
                "action": "test",
                "action_label": "测试",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 86
            },
            {
                "key": "integration_plugin.secret_reveal",
                "module": "integration_plugin",
                "module_label": "插件",
                "group": "system_integration",
                "group_label": "集成插件",
                "name": "查看插件密钥",
                "description": "查看插件密钥",
                "action": "secret_reveal",
                "action_label": "查看密钥",
                "risk_level": "high",
                "is_dangerous": true,
                "is_all": false,
                "sort": 88
            },
            {
                "key": "integration_plugin.manage",
                "module": "integration_plugin",
                "module_label": "插件",
                "group": "system_integration",
                "group_label": "集成插件",
                "name": "管理集成插件",
                "description": "管理集成插件",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            },
            {
                "key": "schedule.view",
                "module": "schedule",
                "module_label": "自动化",
                "group": "system_schedule",
                "group_label": "自动化任务",
                "name": "查看自动化任务",
                "description": "查看自动化任务",
                "action": "view",
                "action_label": "查看",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 10
            },
            {
                "key": "schedule.trigger",
                "module": "schedule",
                "module_label": "自动化",
                "group": "system_schedule",
                "group_label": "自动化任务",
                "name": "触发自动化任务",
                "description": "触发自动化任务",
                "action": "trigger",
                "action_label": "触发",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 84
            },
            {
                "key": "log.list",
                "module": "log",
                "module_label": "日志",
                "group": "system_audit",
                "group_label": "日志审计",
                "name": "查看日志",
                "description": "查看日志",
                "action": "list",
                "action_label": "列表",
                "risk_level": "low",
                "is_dangerous": false,
                "is_all": false,
                "sort": 20
            },
            {
                "key": "log.manage",
                "module": "log",
                "module_label": "日志",
                "group": "system_audit",
                "group_label": "日志审计",
                "name": "管理日志",
                "description": "管理日志",
                "action": "manage",
                "action_label": "管理",
                "risk_level": "medium",
                "is_dangerous": false,
                "is_all": false,
                "sort": 90
            }
        ]
    },
    "timestamp": 1783240511
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:11  
· 响应状态码：200  
· 调用方式：GET /api/admin/permissions  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Admin\AdminPermissionCatalogController@index`  
· 请求校验：`根据控制器签名、FormRequest 和路由参数推断`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；具体 data 字段以控制器、Resource、Service 返回为准`  
· 中间件：`api, auth:sanctum, ensure.admin, permission:permission.list`
