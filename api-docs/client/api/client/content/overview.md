# overview

**请求方法**：GET  
**请求路径**：`/api/client/content/overview`  
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
| data.notices | array | 真实调用返回字段 |
| data.notices.id | integer | 真实调用返回字段 |
| data.notices.content_type | string | 真实调用返回字段 |
| data.notices.type | string | 真实调用返回字段 |
| data.notices.type_label | string | 真实调用返回字段 |
| data.notices.category_id | integer | 真实调用返回字段 |
| data.notices.content_category_id | integer | 真实调用返回字段 |
| data.notices.title | string | 真实调用返回字段 |
| data.notices.slug | string | 真实调用返回字段 |
| data.notices.summary | string | 真实调用返回字段 |
| data.notices.excerpt | string | 真实调用返回字段 |
| data.notices.category_name | string | 真实调用返回字段 |
| data.notices.category | string | 真实调用返回字段 |
| data.notices.category_slug | string | 真实调用返回字段 |
| data.notices.category_description | null | 真实调用返回字段 |
| data.notices.category_detail | object | 真实调用返回字段 |
| data.notices.category_detail.id | integer | 真实调用返回字段 |
| data.notices.category_detail.name | string | 真实调用返回字段 |
| data.notices.category_detail.slug | string | 真实调用返回字段 |
| data.notices.category_detail.description | null | 真实调用返回字段 |
| data.notices.category_detail.status | integer | 真实调用返回字段 |
| data.notices.category_detail.sort_order | integer | 真实调用返回字段 |
| data.notices.keywords | null | 真实调用返回字段 |
| data.notices.cover_image | string | 真实调用返回字段 |
| data.notices.status | integer | 真实调用返回字段 |
| data.notices.status_label | string | 真实调用返回字段 |
| data.notices.is_pinned | integer | 真实调用返回字段 |
| data.notices.is_recommended | integer | 真实调用返回字段 |
| data.notices.sort_order | integer | 真实调用返回字段 |
| data.notices.view_count | integer | 真实调用返回字段 |
| data.notices.publish_at | string | 真实调用返回字段 |
| data.notices.last_published_at | string | 真实调用返回字段 |
| data.notices.operator | string | 真实调用返回字段 |
| data.notices.remark | string | 真实调用返回字段 |
| data.notices.trace_id | string | 真实调用返回字段 |
| data.notices.created_at | string | 真实调用返回字段 |
| data.notices.updated_at | string | 真实调用返回字段 |
| data.notices.creator | null | 真实调用返回字段 |
| data.notices.updater | null | 真实调用返回字段 |
| data.help_articles | array | 真实调用返回字段 |
| data.help_articles.id | integer | 真实调用返回字段 |
| data.help_articles.content_type | string | 真实调用返回字段 |
| data.help_articles.type | string | 真实调用返回字段 |
| data.help_articles.type_label | string | 真实调用返回字段 |
| data.help_articles.category_id | integer | 真实调用返回字段 |
| data.help_articles.content_category_id | integer | 真实调用返回字段 |
| data.help_articles.title | string | 真实调用返回字段 |
| data.help_articles.slug | string | 真实调用返回字段 |
| data.help_articles.summary | null | 真实调用返回字段 |
| data.help_articles.excerpt | string | 真实调用返回字段 |
| data.help_articles.category_name | string | 真实调用返回字段 |
| data.help_articles.category | string | 真实调用返回字段 |
| data.help_articles.category_slug | string | 真实调用返回字段 |
| data.help_articles.category_description | null | 真实调用返回字段 |
| data.help_articles.category_detail | object | 真实调用返回字段 |
| data.help_articles.category_detail.id | integer | 真实调用返回字段 |
| data.help_articles.category_detail.name | string | 真实调用返回字段 |
| data.help_articles.category_detail.slug | string | 真实调用返回字段 |
| data.help_articles.category_detail.description | null | 真实调用返回字段 |
| data.help_articles.category_detail.status | integer | 真实调用返回字段 |
| data.help_articles.category_detail.sort_order | integer | 真实调用返回字段 |
| data.help_articles.keywords | null | 真实调用返回字段 |
| data.help_articles.cover_image | null | 真实调用返回字段 |
| data.help_articles.status | integer | 真实调用返回字段 |
| data.help_articles.status_label | string | 真实调用返回字段 |
| data.help_articles.is_pinned | integer | 真实调用返回字段 |
| data.help_articles.is_recommended | integer | 真实调用返回字段 |
| data.help_articles.sort_order | integer | 真实调用返回字段 |
| data.help_articles.view_count | integer | 真实调用返回字段 |
| data.help_articles.publish_at | string | 真实调用返回字段 |
| data.help_articles.last_published_at | string | 真实调用返回字段 |
| data.help_articles.operator | string | 真实调用返回字段 |
| data.help_articles.remark | null | 真实调用返回字段 |
| data.help_articles.trace_id | string | 真实调用返回字段 |
| data.help_articles.created_at | string | 真实调用返回字段 |
| data.help_articles.updated_at | string | 真实调用返回字段 |
| data.help_articles.creator | null | 真实调用返回字段 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "notices": [
            {
                "id": 15,
                "content_type": "notice",
                "type": "notice",
                "type_label": "公告",
                "category_id": 8,
                "content_category_id": 8,
                "title": "创欧云计算 · 国庆狂欢盛典",
                "slug": "notice-14",
                "summary": "创欧云计算-国庆节活动? 活动时间：2025.10.1 – 2025.10.3 ️⃣ 充值活动 用户充值 100元以上 返现 10% 用户预存 500元 即可升级为 铂金代理（海外、国内产品 8 折 购入/续费，不包括轻量云和活动机型） ️⃣ 新购折扣 优惠码：国庆节快乐 8折 购入，续费同 8 折 注：不包括轻量云和活动机型 （活动最终解释权归创欧云计算所有）",
                "excerpt": "创欧云计算-国庆节活动? 活动时间：2025.10.1 – 2025.10.3 ️⃣ 充值活动 用户充值 100元以上 返现 10% 用户预存 500元 即可升级为 铂金代理（海外、国内产品 8 折 购入/续费，不包括轻量云和活动机型） ️⃣ 新购折扣 优惠码：国庆节快乐 8折 购入，续费同 8 折 注：不包括轻量云和活动机型 （活动最终解释权归创欧云计算所有）",
                "category_name": "官方通知",
                "category": "官方通知",
                "category_slug": "notice-6",
                "category_description": null,
                "category_detail": {
                    "id": 8,
                    "name": "官方通知",
                    "slug": "notice-6",
                    "description": null,
                    "status": 1,
                    "sort_order": 0
                },
                "keywords": null,
                "cover_image": "https://www.coyjs.cn/uploads/content/20260624/img_020935_3650.jpg",
                "status": 1,
                "status_label": "已发布",
                "is_pinned": 1,
                "is_recommended": 0,
                "sort_order": 0,
                "view_count": 529,
                "publish_at": "2025-10-01 11:47:41",
                "last_published_at": "2025-10-01 11:47:41",
                "operator": "管理员",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "70c1aff3-25b7-4b95-83be-72903f182742",
                "created_at": "2025-10-01 11:49:28",
                "updated_at": "2026-06-28 21:50:43",
                "creator": null,
                "updater": null
            },
            {
                "id": 21,
                "content_type": "notice",
                "type": "notice",
                "type_label": "公告",
                "category_id": 7,
                "content_category_id": 7,
                "title": "【关于全面推行实名认证的通知】",
                "slug": "notice-b8xbhjls",
                "summary": null,
                "excerpt": "关于全面推行实名认证的通知 尊敬的客户与合作伙伴： 为深入推进业务合规化建设，有效降低经营风险，构建规范的渠道供应体系，我...",
                "category_name": "官方政策",
                "category": "官方政策",
                "category_slug": "notice-4",
                "category_description": null,
                "category_detail": {
                    "id": 7,
                    "name": "官方政策",
                    "slug": "notice-4",
                    "description": null,
                    "status": 1,
                    "sort_order": 0
                },
                "keywords": null,
                "cover_image": null,
                "status": 1,
                "status_label": "已发布",
                "is_pinned": 0,
                "is_recommended": 1,
                "sort_order": 0,
                "view_count": 30,
                "publish_at": "2026-05-27 16:01:53",
                "last_published_at": "2026-05-27 16:01:53",
                "operator": "admin#1",
                "remark": null,
                "trace_id": "bfc627fb-7c2e-4f91-8964-18a8ee2d8927",
                "created_at": "2026-05-27 16:01:53",
                "updated_at": "2026-07-01 10:44:48",
                "creator": null,
                "updater": null
            },
            {
                "id": 14,
                "content_type": "notice",
                "type": "notice",
                "type_label": "公告",
                "category_id": 8,
                "content_category_id": 8,
                "title": "关于自动续费的说明",
                "slug": "notice-13",
                "summary": "自动续费功能可能存在系统异常，会导致自动续费失败的情况。为避免因自动续费未成功导致服务中断或数据丢失，强烈建议您手动进行续费，以确保续费流程稳定可靠。 感谢您的理解与配合！如有任何疑问，请及时联系客服协助处理。",
                "excerpt": "自动续费功能可能存在系统异常，会导致自动续费失败的情况。为避免因自动续费未成功导致服务中断或数据丢失，强烈建议您手动进行续费，以确保续费流程稳定可靠。 感谢您的理解与配合！如有任何疑问，请及时联系客服协助处理。",
                "category_name": "官方通知",
                "category": "官方通知",
                "category_slug": "notice-6",
                "category_description": null,
                "category_detail": {
                    "id": 8,
                    "name": "官方通知",
                    "slug": "notice-6",
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
                "view_count": 365,
                "publish_at": "2025-09-19 09:41:44",
                "last_published_at": "2025-09-19 09:41:44",
                "operator": "管理员",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "167f2613-75ef-4c52-8696-fbc5f6e56648",
                "created_at": "2025-09-19 09:42:29",
                "updated_at": "2026-07-01 10:51:59",
                "creator": null,
                "updater": null
            },
            {
                "id": 16,
                "content_type": "notice",
                "type": "notice",
                "type_label": "公告",
                "category_id": 8,
                "content_category_id": 8,
                "title": "宁波，成都，德阳业务调整",
                "slug": "notice-9",
                "summary": "月23日﹣6月26日，成都（沙渠）云机器将发往宁波机房。业务补偿5天，此机器／配置将不再售卖 月30日，宁波业务将搬移机柜并更换IP。业务补偿3天，业务将调整为双向计费。由于机器需要维修，中断时间可能大于3小时。 月30日前，德阳／成都（沙渠）托管业务／成都挂机宝业务将发往成都（西信）机房并更换IP。业务补偿3天，免费升级150G防御，支持屏蔽UDP。机器将当日下架当日寄出当日上架，中断不超过24小时",
                "excerpt": "月23日﹣6月26日，成都（沙渠）云机器将发往宁波机房。业务补偿5天，此机器／配置将不再售卖 月30日，宁波业务将搬移机柜并更换IP。业务补偿3天，业务将调整为双向计费。由于机器需要维修，中断时间可能大于3小时。 月30日前，德阳／成都（沙渠）托管业务／成都挂机宝业务将发往成都（西信）机房并更换IP。业务补偿3天，免费升级150G防御，支持屏蔽UDP。机器将当日下架当日寄出当日上架，中断不超过24小时",
                "category_name": "官方通知",
                "category": "官方通知",
                "category_slug": "notice-6",
                "category_description": null,
                "category_detail": {
                    "id": 8,
                    "name": "官方通知",
                    "slug": "notice-6",
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
                "view_count": 501,
                "publish_at": "2025-06-23 05:15:37",
                "last_published_at": "2025-06-23 05:15:37",
                "operator": "管理员",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "8dd85332-91d4-4b46-8969-cd1f8b9ae0d0",
                "created_at": "2025-06-23 05:16:46",
                "updated_at": "2026-05-07 01:07:15",
                "creator": null,
                "updater": null
            },
            {
                "id": 18,
                "content_type": "notice",
                "type": "notice",
                "type_label": "公告",
                "category_id": 8,
                "content_category_id": 8,
                "title": "美国三网精品迁移通知",
                "slug": "notice-8",
                "summary": "美国三网精品迁移通知 由于上游租用服务商处理效率低，网络波动且找不到原因，决定将迁移AMD节点到netlab机房，所有已开通配置带宽免费升级20M，补偿3天。 涉及Ip段38.148.241 38.148.246",
                "excerpt": "美国三网精品迁移通知 由于上游租用服务商处理效率低，网络波动且找不到原因，决定将迁移AMD节点到netlab机房，所有已开通配置带宽免费升级20M，补偿3天。 涉及Ip段38.148.241 38.148.246",
                "category_name": "官方通知",
                "category": "官方通知",
                "category_slug": "notice-6",
                "category_description": null,
                "category_detail": {
                    "id": 8,
                    "name": "官方通知",
                    "slug": "notice-6",
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
                "view_count": 813,
                "publish_at": "2025-06-23 05:14:04",
                "last_published_at": "2025-06-23 05:14:04",
                "operator": "管理员",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "0b7e8e0d-d37e-4940-a502-73173cdda3f0",
                "created_at": "2025-06-23 05:14:27",
                "updated_at": "2026-07-01 06:41:37",
                "creator": null,
                "updater": null
            },
            {
                "id": 17,
                "content_type": "notice",
                "type": "notice",
                "type_label": "公告",
                "category_id": 7,
                "content_category_id": 7,
                "title": "服务条款",
                "slug": "notice-6",
                "summary": "协议说明 本协议是创欧云数据（简称“创欧云”）与您之间就您使用创欧云服务的相关事项签订的合同。为使用创欧云服务，您应当充分阅读、理解本协议。“用户”是被创欧云提供服务的客户。 您通过勾选、点击确认或以其他方式表示接受本协议，或注册成功，或您以任何方式使用创欧云服务的，即视为您已阅读、理解本协议并同意接受本协议的约束，本协议即在您与创欧云之间产生法律效力。 禁止的内容 创欧云服务仅限合法用途使用，创欧云不对用户使用其产品/服务所产生的行为",
                "excerpt": "协议说明 本协议是创欧云数据（简称“创欧云”）与您之间就您使用创欧云服务的相关事项签订的合同。为使用创欧云服务，您应当充分阅读、理解本协议。“用户”是被创欧云提供服务的客户。 您通过勾选、点击确认或以其他方式表示接受本协议，或注册成功，或您以任何方式使用创欧云服务的，即视为您已阅读、理解本协议并同意接受本协议的约束，本协议即在您与创欧云之间产生法律效力。 禁止的内容 创欧云服务仅限合法用途使用，创欧云不对用户使用其产品/服务所产生的行为",
                "category_name": "官方政策",
                "category": "官方政策",
                "category_slug": "notice-4",
                "category_description": null,
                "category_detail": {
                    "id": 7,
                    "name": "官方政策",
                    "slug": "notice-4",
                    "description": null,
                    "status": 1,
                    "sort_order": 0
                },
                "keywords": null,
                "cover_image": null,
                "status": 1,
                "status_label": "已发布",
                "is_pinned": 0,
                "is_recommended": 1,
                "sort_order": 0,
                "view_count": 473,
                "publish_at": "2025-01-21 12:20:17",
                "last_published_at": "2025-01-21 12:20:17",
                "operator": "管理员",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "31c216aa-cbea-4d29-94d8-e2703f4b1844",
                "created_at": "2025-01-21 12:20:43",
                "updated_at": "2026-06-25 03:29:45",
                "creator": null,
                "updater": null
            }
        ],
        "help_articles": [
            {
                "id": 22,
                "content_type": "help",
                "type": "help",
                "type_label": "帮助",
                "category_id": 6,
                "content_category_id": 6,
                "title": "Linux 带宽测速脚本",
                "slug": "linux",
                "summary": null,
                "excerpt": "Linux带宽测速脚本 http://speedcs.cn 致力打造全网最好用的Linux带宽测速脚本 使用命令 curl -O http://speedcs.cn/speedtest &...",
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
                "view_count": 15,
                "publish_at": "2026-05-31 10:29:11",
                "last_published_at": "2026-05-31 10:29:11",
                "operator": "admin#1",
                "remark": null,
                "trace_id": "bb4da84c-13d4-4139-a2d3-da5d3c533a8d",
                "created_at": "2026-05-31 10:29:11",
                "updated_at": "2026-06-26 21:20:33",
                "creator": null,
                "updater": null
            },
            {
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
                "view_count": 532,
                "publish_at": "2025-08-23 17:02:07",
                "last_published_at": "2025-08-23 17:02:07",
                "operator": "mofang-migration",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "mofang-content-12",
                "created_at": "2025-08-23 17:02:43",
                "updated_at": "2026-07-05 16:32:03",
                "creator": null,
                "updater": null
            },
            {
                "id": 12,
                "content_type": "help",
                "type": "help",
                "type_label": "帮助",
                "category_id": 5,
                "content_category_id": 5,
                "title": "各产品测试IP",
                "slug": "ip-10",
                "summary": "各产品测试IP",
                "excerpt": "各产品测试IP",
                "category_name": "介绍",
                "category": "介绍",
                "category_slug": "help-3",
                "category_description": null,
                "category_detail": {
                    "id": 5,
                    "name": "介绍",
                    "slug": "help-3",
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
                "view_count": 727,
                "publish_at": "2025-08-16 12:09:56",
                "last_published_at": "2025-08-16 12:09:56",
                "operator": "mofang-migration",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "mofang-content-10",
                "created_at": "2025-08-16 12:10:12",
                "updated_at": "2026-06-09 09:29:59",
                "creator": null,
                "updater": null
            },
            {
                "id": 13,
                "content_type": "help",
                "type": "help",
                "type_label": "帮助",
                "category_id": 5,
                "content_category_id": 5,
                "title": "各线路介绍",
                "slug": "help-1",
                "summary": "中国互联网骨干网按运营商概览与比较 中国互联网骨干网按运营商概览与比较 中国电信 中国电信提供了多种骨干网服务，适用于不同层次的需求。 骨干网 (ChinaNet, AS4134) 适合人群：普通家庭用户特点：IP地址以202.97开头，价格便宜，但出国线路容易拥堵。适用场景：日常上网浏览、社交媒体使用。 CN2 GT (半程走 CN2) 适合人群：中端企业用户特点：省级骨干走163（出现202.97节点），出口才走CN2(AS4809",
                "excerpt": "中国互联网骨干网按运营商概览与比较 中国互联网骨干网按运营商概览与比较 中国电信 中国电信提供了多种骨干网服务，适用于不同层次的需求。 骨干网 (ChinaNet, AS4134) 适合人群：普通家庭用户特点：IP地址以202.97开头，价格便宜，但出国线路容易拥堵。适用场景：日常上网浏览、社交媒体使用。 CN2 GT (半程走 CN2) 适合人群：中端企业用户特点：省级骨干走163（出现202.97节点），出口才走CN2(AS4809",
                "category_name": "介绍",
                "category": "介绍",
                "category_slug": "help-3",
                "category_description": null,
                "category_detail": {
                    "id": 5,
                    "name": "介绍",
                    "slug": "help-3",
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
                "view_count": 685,
                "publish_at": "2025-01-20 17:30:16",
                "last_published_at": "2025-01-20 17:30:16",
                "operator": "mofang-migration",
                "remark": "魔方公告/帮助迁移",
                "trace_id": "mofang-content-1",
                "created_at": "2025-01-20 17:30:47",
                "updated_at": "2026-06-22 11:54:43",
                "creator": null,
                "updater": null
            }
        ],
        "notice_categories": [
            {
                "id": 7,
                "content_type": "notice",
                "type": "notice",
                "name": "官方政策",
                "slug": "notice-4",
                "description": null,
                "status": 1,
                "sort_order": 0,
                "articles_count": 4,
                "created_at": "2026-03-25 17:50:49",
                "updated_at": "2026-03-25 17:50:49"
            },
            {
                "id": 8,
                "content_type": "notice",
                "type": "notice",
                "name": "官方通知",
                "slug": "notice-6",
                "description": null,
                "status": 1,
                "sort_order": 0,
                "articles_count": 4,
                "created_at": "2026-03-25 17:50:49",
                "updated_at": "2026-03-25 17:50:49"
            }
        ],
        "help_categories": [
            {
                "id": 5,
                "content_type": "help",
                "type": "help",
                "name": "介绍",
                "slug": "help-3",
                "description": null,
                "status": 1,
                "sort_order": 0,
                "articles_count": 2,
                "created_at": "2026-03-25 17:50:49",
                "updated_at": "2026-03-25 17:50:49"
            },
            {
                "id": 6,
                "content_type": "help",
                "type": "help",
                "name": "帮助",
                "slug": "help-5",
                "description": null,
                "status": 1,
                "sort_order": 0,
                "articles_count": 2,
                "created_at": "2026-03-25 17:50:49",
                "updated_at": "2026-03-25 17:50:49"
            }
        ]
    },
    "timestamp": 1783240524
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:24  
· 响应状态码：200  
· 调用方式：GET /api/client/content/overview  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\ContentController@overview`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器 success([...]) 数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
