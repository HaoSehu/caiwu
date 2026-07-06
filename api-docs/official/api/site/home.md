# home

**请求方法**：GET  
**请求路径**：`/api/site/home`  
**调试状态**：✅ 通过

### 请求头
| 参数名 | 值 | 必填 | 说明 |
|---|---|---|---|
| Content-Type | application/json | 是 | - |
| Accept | application/json | 是 | 期望 JSON 响应 |
| Authorization | Bearer {token} | 否 | 公开接口，可不传 |

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
| data.site_config | object | 真实调用返回字段 |
| data.site_config.site_name | string | 真实调用返回字段 |
| data.site_config.browser_title | string | 真实调用返回字段 |
| data.site_config.site_logo | string | 真实调用返回字段 |
| data.site_config.site_favicon | string | 真实调用返回字段 |
| data.site_config.client_console_icon | string | 真实调用返回字段 |
| data.site_config.service_qq_group | string | 真实调用返回字段 |
| data.site_config.service_phone | string | 真实调用返回字段 |
| data.site_config.service_email | string | 真实调用返回字段 |
| data.site_config.service_hours | string | 真实调用返回字段 |
| data.site_config.support_group_title | string | 真实调用返回字段 |
| data.site_config.support_group_text | string | 真实调用返回字段 |
| data.site_config.support_group_qr | string | 真实调用返回字段 |
| data.site_config.support_group_link | string | 真实调用返回字段 |
| data.site_config.terms_url | string | 真实调用返回字段 |
| data.site_config.privacy_url | string | 真实调用返回字段 |
| data.hero | object | 真实调用返回字段 |
| data.hero.slides | array | 真实调用返回字段 |
| data.hero.slides.key | string | 真实调用返回字段 |
| data.hero.slides.rail_title | string | 真实调用返回字段 |
| data.hero.slides.title | string | 真实调用返回字段 |
| data.hero.slides.desc | string | 真实调用返回字段 |
| data.hero.slides.primary_text | string | 真实调用返回字段 |
| data.hero.slides.primary_path | string | 真实调用返回字段 |
| data.hero.slides.secondary_text | string | 真实调用返回字段 |
| data.hero.slides.secondary_path | string | 真实调用返回字段 |
| data.hero.slides.shape | string | 真实调用返回字段 |
| data.hero.slides.video | string | 真实调用返回字段 |
| data.hero.slides.ribbon | string | 真实调用返回字段 |
| data.hero.slides.ribbon_type | string | 真实调用返回字段 |
| data.hero.features | array | 真实调用返回字段 |
| data.hero.features.key | string | 真实调用返回字段 |
| data.hero.features.kicker | string | 真实调用返回字段 |
| data.hero.features.title | string | 真实调用返回字段 |
| data.hero.features.desc | string | 真实调用返回字段 |
| data.hero.features.path | string | 真实调用返回字段 |
| data.notices | array | 真实调用返回字段 |
| data.notices.id | integer | 真实调用返回字段 |
| data.notices.title | string | 真实调用返回字段 |
| data.notices.summary | string | 真实调用返回字段 |
| data.notices.excerpt | string | 真实调用返回字段 |
| data.notices.cover_image | string | 真实调用返回字段 |
| data.notices.category | string | 真实调用返回字段 |
| data.notices.is_pinned | integer | 真实调用返回字段 |
| data.notices.is_recommended | integer | 真实调用返回字段 |
| data.notices.publish_at | string | 真实调用返回字段 |
| data.notices.updated_at | string | 真实调用返回字段 |
| data.help_articles | array | 真实调用返回字段 |
| data.help_articles.id | integer | 真实调用返回字段 |
| data.help_articles.title | string | 真实调用返回字段 |
| data.help_articles.summary | null | 真实调用返回字段 |
| data.help_articles.excerpt | string | 真实调用返回字段 |
| data.help_articles.cover_image | null | 真实调用返回字段 |
| data.help_articles.category | string | 真实调用返回字段 |
| data.help_articles.is_pinned | integer | 真实调用返回字段 |
| data.help_articles.is_recommended | integer | 真实调用返回字段 |
| data.help_articles.publish_at | string | 真实调用返回字段 |
| data.help_articles.updated_at | string | 真实调用返回字段 |
| data.root_groups | array | 真实调用返回字段 |
| data.root_groups.id | integer | 真实调用返回字段 |
| data.root_groups.name | string | 真实调用返回字段 |
| data.root_groups.slogan | string | 真实调用返回字段 |
| data.root_groups.product_count | integer | 真实调用返回字段 |
| data.root_groups.product_type | string | 真实调用返回字段 |
| data.root_groups.product_type_id | integer | 真实调用返回字段 |
| data.root_groups.product_type_label | string | 真实调用返回字段 |
| data.group_catalog_map | object | 真实调用返回字段 |
| data.group_catalog_map.12 | object | 真实调用返回字段 |
| data.group_catalog_map.12.preview_products | array | 真实调用返回字段 |
| data.group_catalog_map.12.preview_products.id | integer | 真实调用返回字段 |
| data.group_catalog_map.12.preview_products.effective_product_group_id | integer | 真实调用返回字段 |
| data.group_catalog_map.12.preview_products.name | string | 真实调用返回字段 |
| data.group_catalog_map.12.preview_products.display_name | string | 真实调用返回字段 |
| data.group_catalog_map.12.preview_products.instance_spec_text | string | 真实调用返回字段 |
| data.group_catalog_map.12.preview_products.instance_spec_alias | string | 真实调用返回字段 |
| data.group_catalog_map.12.preview_products.primary_price | string | 真实调用返回字段 |
| data.group_catalog_map.12.featured_product | object | 真实调用返回字段 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "site_config": {
            "site_name": "创欧云",
            "browser_title": "创欧云",
            "site_logo": "http://127.0.0.1:5175/media/logo1.svg",
            "site_favicon": "http://127.0.0.1:5175/media/logo1.svg",
            "client_console_icon": "http://127.0.0.1:5175/media/logo.svg",
            "service_qq_group": "1028089905",
            "service_phone": "1028089905",
            "service_email": "2908990438@qq.com",
            "service_hours": "",
            "support_group_title": "",
            "support_group_text": "",
            "support_group_qr": "http://127.0.0.1:5175/media/img_181926_2929.jpg",
            "support_group_link": "https://qm.qq.com/q/oqTRUMHiiQ",
            "terms_url": "https://www.coyjs.cn/notices/17?category=7",
            "privacy_url": "https://www.coyjs.cn/notices/20?category=7"
        },
        "hero": {
            "slides": [
                {
                    "key": "refresh",
                    "rail_title": "官网换新",
                    "title": "官网焕新 · 云上新体验",
                    "desc": "产品目录、下单支付、自动开通、账单结算与服务支持统一打通；首页即是控制台的入口，让资源采购和后续管理始终在同一条链路里完成。",
                    "primary_text": "立即体验",
                    "primary_path": "/products",
                    "secondary_text": "查看详情",
                    "secondary_path": "/about",
                    "shape": "computer",
                    "video": "/uploads/hero-videos/hero-2.mp4",
                    "ribbon": "",
                    "ribbon_type": "new"
                },
                {
                    "key": "global",
                    "rail_title": "全球互联",
                    "title": "多地节点 · 全球低延迟互联",
                    "desc": "覆盖香港、美国与国内多地优质节点，三网 CN2 / BGP 线路优化回国；跨区域组网、跨境业务秒级响应，适合建站、代理、跨境电商与出海 SaaS。",
                    "primary_text": "选购节点",
                    "primary_path": "/products",
                    "secondary_text": "查看线路",
                    "secondary_path": "/help",
                    "shape": "connection",
                    "video": "/uploads/hero-videos/hero-3.mp4",
                    "ribbon": "",
                    "ribbon_type": "new"
                },
                {
                    "key": "security",
                    "rail_title": "安全防护",
                    "title": "企业级安全 · 稳定可靠交付",
                    "desc": "T3+ 数据中心 + BGP 多线接入 + 100G 抗 DDoS 防护；实名认证、权限分级与操作留痕保障账户安全，长期业务和合规场景稳定承载。",
                    "primary_text": "查看防护",
                    "primary_path": "/products",
                    "secondary_text": "在线咨询",
                    "secondary_path": "/help",
                    "shape": "security",
                    "video": "/uploads/hero-videos/hero-5.mp4",
                    "ribbon": "",
                    "ribbon_type": "new"
                },
                {
                    "key": "value",
                    "rail_title": "实惠专区",
                    "title": "低门槛套餐 · 直享实惠价",
                    "desc": "新客首单 2 核 2G 云服务器 99 元/年起，轻量云电脑按月订阅即开即用；优惠券、折扣券灵活叠加，配置随业务弹性升级，先用后付更省心。",
                    "primary_text": "立即抢购",
                    "primary_path": "/products",
                    "secondary_text": "查看优惠",
                    "secondary_path": "/products",
                    "shape": "value",
                    "video": "/uploads/hero-videos/hero-4.mp4",
                    "ribbon": "",
                    "ribbon_type": "warm"
                },
                {
                    "key": "support",
                    "rail_title": "企业客服",
                    "title": "企业客服 · 一对一专属服务",
                    "desc": "7×24 小时工单、官方QQ群与一对一商务对接，覆盖选型、部署、迁移、运维与结算；支持对公开票、批量采购、子账号协作与统一对账。",
                    "primary_text": "联系客服",
                    "primary_path": "/help",
                    "secondary_text": "企业采购",
                    "secondary_path": "/about",
                    "shape": "support",
                    "video": "/uploads/hero-videos/hero-1.mp4",
                    "ribbon": "",
                    "ribbon_type": "new"
                }
            ],
            "features": [
                {
                    "key": "dynamic",
                    "kicker": "产品动态",
                    "title": "香港 CN2 精品线路 上线",
                    "desc": "三网 CN2 GIA 优化回国，跨境业务低时延稳定承载。",
                    "path": "/products"
                },
                {
                    "key": "activity",
                    "kicker": "活动内容",
                    "title": "新客首单 99 元/年",
                    "desc": "2H2G 云服务器覆盖建站、代理、轻量业务全场景。",
                    "path": "/products"
                },
                {
                    "key": "enterprise",
                    "kicker": "企业专区",
                    "title": "IDC 企业采购通道",
                    "desc": "统一账单、多子账号协作与对公开票能力同步上线。",
                    "path": "/about"
                },
                {
                    "key": "cloud-desktop",
                    "kicker": "轻量产品",
                    "title": "西安云电脑 即开即用",
                    "desc": "西安节点低延迟，支持远程办公、外包协作、教学实训。",
                    "path": "/products"
                },
                {
                    "key": "new",
                    "kicker": "新开产品",
                    "title": "十堰高防独立服务器",
                    "desc": "100G 抗 DDoS + BGP 多线，面向长期稳定业务承载。",
                    "path": "/products"
                }
            ]
        },
        "notices": [
            {
                "id": 15,
                "title": "创欧云计算 · 国庆狂欢盛典",
                "summary": "创欧云计算-国庆节活动? 活动时间：2025.10.1 – 2025.10.3 ️⃣ 充值活动 用户充值 100元以上 返现 10% 用户预存 500元 即可升级为 铂金代理（海外、国内产品 8 折 购入/续费，不包括轻量云和活动机型） ️⃣ 新购折扣 优惠码：国庆节快乐 8折 购入，续费同 8 折 注：不包括轻量云和活动机型 （活动最终解释权归创欧云计算所有）",
                "excerpt": "创欧云计算-国庆节活动? 活动时间：2025.10.1 – 2025.10.3 ️⃣ 充值活动 用户充值 100元以上 返现 10% 用户预存 500元 即可升级为 铂金代理（海外、国内产品 8 折 购入/续费，不包括轻量云和活动机型） ️⃣ 新购折扣 优惠码：国庆节快乐 8折 购入，续费同 8 折 注：不包括轻量云和活动机型 （活动最终解释权归创欧云计算所有）",
                "cover_image": "https://www.coyjs.cn/uploads/content/20260624/img_020935_3650.jpg",
                "category": "官方通知",
                "is_pinned": 1,
                "is_recommended": 0,
                "publish_at": "2025-10-01 11:47:41",
                "updated_at": "2026-06-28 21:50:43"
            },
            {
                "id": 21,
                "title": "【关于全面推行实名认证的通知】",
                "summary": null,
                "excerpt": "关于全面推行实名认证的通知 尊敬的客户与合作伙伴： 为深入推进业务合规化建设，有效降低经营风险，构建规范的渠道供应体系，我...",
                "cover_image": null,
                "category": "官方政策",
                "is_pinned": 0,
                "is_recommended": 1,
                "publish_at": "2026-05-27 16:01:53",
                "updated_at": "2026-07-01 10:44:48"
            },
            {
                "id": 14,
                "title": "关于自动续费的说明",
                "summary": "自动续费功能可能存在系统异常，会导致自动续费失败的情况。为避免因自动续费未成功导致服务中断或数据丢失，强烈建议您手动进行续费，以确保续费流程稳定可靠。 感谢您的理解与配合！如有任何疑问，请及时联系客服协助处理。",
                "excerpt": "自动续费功能可能存在系统异常，会导致自动续费失败的情况。为避免因自动续费未成功导致服务中断或数据丢失，强烈建议您手动进行续费，以确保续费流程稳定可靠。 感谢您的理解与配合！如有任何疑问，请及时联系客服协助处理。",
                "cover_image": null,
                "category": "官方通知",
                "is_pinned": 0,
                "is_recommended": 0,
                "publish_at": "2025-09-19 09:41:44",
                "updated_at": "2026-07-01 10:51:59"
            },
            {
                "id": 16,
                "title": "宁波，成都，德阳业务调整",
                "summary": "月23日﹣6月26日，成都（沙渠）云机器将发往宁波机房。业务补偿5天，此机器／配置将不再售卖 月30日，宁波业务将搬移机柜并更换IP。业务补偿3天，业务将调整为双向计费。由于机器需要维修，中断时间可能大于3小时。 月30日前，德阳／成都（沙渠）托管业务／成都挂机宝业务将发往成都（西信）机房并更换IP。业务补偿3天，免费升级150G防御，支持屏蔽UDP。机器将当日下架当日寄出当日上架，中断不超过24小时",
                "excerpt": "月23日﹣6月26日，成都（沙渠）云机器将发往宁波机房。业务补偿5天，此机器／配置将不再售卖 月30日，宁波业务将搬移机柜并更换IP。业务补偿3天，业务将调整为双向计费。由于机器需要维修，中断时间可能大于3小时。 月30日前，德阳／成都（沙渠）托管业务／成都挂机宝业务将发往成都（西信）机房并更换IP。业务补偿3天，免费升级150G防御，支持屏蔽UDP。机器将当日下架当日寄出当日上架，中断不超过24小时",
                "cover_image": null,
                "category": "官方通知",
                "is_pinned": 0,
                "is_recommended": 0,
                "publish_at": "2025-06-23 05:15:37",
                "updated_at": "2026-05-07 01:07:15"
            },
            {
                "id": 18,
                "title": "美国三网精品迁移通知",
                "summary": "美国三网精品迁移通知 由于上游租用服务商处理效率低，网络波动且找不到原因，决定将迁移AMD节点到netlab机房，所有已开通配置带宽免费升级20M，补偿3天。 涉及Ip段38.148.241 38.148.246",
                "excerpt": "美国三网精品迁移通知 由于上游租用服务商处理效率低，网络波动且找不到原因，决定将迁移AMD节点到netlab机房，所有已开通配置带宽免费升级20M，补偿3天。 涉及Ip段38.148.241 38.148.246",
                "cover_image": null,
                "category": "官方通知",
                "is_pinned": 0,
                "is_recommended": 0,
                "publish_at": "2025-06-23 05:14:04",
                "updated_at": "2026-07-01 06:41:37"
            },
            {
                "id": 17,
                "title": "服务条款",
                "summary": "协议说明 本协议是创欧云数据（简称“创欧云”）与您之间就您使用创欧云服务的相关事项签订的合同。为使用创欧云服务，您应当充分阅读、理解本协议。“用户”是被创欧云提供服务的客户。 您通过勾选、点击确认或以其他方式表示接受本协议，或注册成功，或您以任何方式使用创欧云服务的，即视为您已阅读、理解本协议并同意接受本协议的约束，本协议即在您与创欧云之间产生法律效力。 禁止的内容 创欧云服务仅限合法用途使用，创欧云不对用户使用其产品/服务所产生的行为",
                "excerpt": "协议说明 本协议是创欧云数据（简称“创欧云”）与您之间就您使用创欧云服务的相关事项签订的合同。为使用创欧云服务，您应当充分阅读、理解本协议。“用户”是被创欧云提供服务的客户。 您通过勾选、点击确认或以其他方式表示接受本协议，或注册成功，或您以任何方式使用创欧云服务的，即视为您已阅读、理解本协议并同意接受本协议的约束，本协议即在您与创欧云之间产生法律效力。 禁止的内容 创欧云服务仅限合法用途使用，创欧云不对用户使用其产品/服务所产生的行为",
                "cover_image": null,
                "category": "官方政策",
                "is_pinned": 0,
                "is_recommended": 1,
                "publish_at": "2025-01-21 12:20:17",
                "updated_at": "2026-06-25 03:29:45"
            },
            {
                "id": 20,
                "title": "隐私政策",
                "summary": "致力于保护您的隐私 本隐私政策最后更新于2025年8月31日。 本协议是创欧云数据（简称“创欧云”）与您之间就您使用创欧云服务的相关事项签订的合同。为使用创欧云服务，您应当充分阅读、理解本协议。“用户”是被创欧云提供服务的客户。 您通过勾选、点击确认或以其他方式表示接受本协议，或注册成功，或您以任何方式使用创欧云服务的，即视为您已阅读、理解本协议并同意接受本协议的约束，本协议即在您与创欧云之间产生法律效力。 尊敬的用户，欢迎使用创欧云服",
                "excerpt": "致力于保护您的隐私 本隐私政策最后更新于2025年8月31日。 本协议是创欧云数据（简称“创欧云”）与您之间就您使用创欧云服务的相关事项签订的合同。为使用创欧云服务，您应当充分阅读、理解本协议。“用户”是被创欧云提供服务的客户。 您通过勾选、点击确认或以其他方式表示接受本协议，或注册成功，或您以任何方式使用创欧云服务的，即视为您已阅读、理解本协议并同意接受本协议的约束，本协议即在您与创欧云之间产生法律效力。 尊敬的用户，欢迎使用创欧云服",
                "cover_image": null,
                "category": "官方政策",
                "is_pinned": 0,
                "is_recommended": 1,
                "publish_at": "2025-01-21 11:33:35",
                "updated_at": "2026-06-24 11:18:36"
            },
            {
                "id": 19,
                "title": "退款协议与增值服务说明",
                "summary": "本协议是创欧云数据（简称“创欧云”）与您之间，就您使用创欧云服务相关事项所订立的合同。在使用创欧云服务前，请您仔细阅读并充分理解本协议内容。本协议所称“用户”，即指接受创欧云服务的客户。您通过勾选、点击确认或以其他方式表示接受本协议，或完成注册，或实际使用创欧云服务的，均视为您已阅读、理解并同意接受本协议全部条款的约束，本协议即在您与创欧云之间产生法律效力。创欧云致力于为用户提供灵活、公平的退款政策，以保障用户满意。以下为退款政策具体内容：",
                "excerpt": "本协议是创欧云数据（简称“创欧云”）与您之间，就您使用创欧云服务相关事项所订立的合同。在使用创欧云服务前，请您仔细阅读并充分理解本协议内容。本协议所称“用户”，即指接受创欧云服务的客户。您通过勾选、点击确认或以其他方式表示接受本协议，或完成注册，或实际使用创欧云服务的，均视为您已阅读、理解并同意接受本协议全部条款的约束，本协议即在您与创欧云之间产生法律效力。创欧云致力于为用户提供灵活、公平的退款政策，以保障用户满意。以下为退款政策具体内容：",
                "cover_image": null,
                "category": "官方政策",
                "is_pinned": 0,
                "is_recommended": 1,
                "publish_at": "2025-01-21 10:33:35",
                "updated_at": "2026-06-26 07:00:24"
            }
        ],
        "help_articles": [
            {
                "id": 22,
                "title": "Linux 带宽测速脚本",
                "summary": null,
                "excerpt": "Linux带宽测速脚本 http://speedcs.cn 致力打造全网最好用的Linux带宽测速脚本 使用命令 curl -O http://speedcs.cn/speedtest &...",
                "cover_image": null,
                "category": "帮助",
                "is_pinned": 0,
                "is_recommended": 0,
                "publish_at": "2026-05-31 10:29:11",
                "updated_at": "2026-06-26 21:20:33"
            },
            {
                "id": 11,
                "title": "win登录到远程Linux服务器",
                "summary": "Windows 登录远程 Linux 服务器指南 在 Windows 系统中，可以通过 SSH 协议登录远程 Linux 服务器。以下是基本操作步骤。 步骤 1：打开命令行工具 按下 Win + R，输入 cmd 或 powershell，然后回车，打开命令提示符或 PowerShell。 步骤 2：确认 SSH 客户端可用 在命令行中输入以下命令检查是否支持 ssh： ssh -V 如果显示版本信息（如 OpenSSHx.x），说明已",
                "excerpt": "Windows 登录远程 Linux 服务器指南 在 Windows 系统中，可以通过 SSH 协议登录远程 Linux 服务器。以下是基本操作步骤。 步骤 1：打开命令行工具 按下 Win + R，输入 cmd 或 powershell，然后回车，打开命令提示符或 PowerShell。 步骤 2：确认 SSH 客户端可用 在命令行中输入以下命令检查是否支持 ssh： ssh -V 如果显示版本信息（如 OpenSSHx.x），说明已",
                "cover_image": null,
                "category": "帮助",
                "is_pinned": 0,
                "is_recommended": 0,
                "publish_at": "2025-08-23 17:02:07",
                "updated_at": "2026-07-05 16:28:12"
            },
            {
                "id": 12,
                "title": "各产品测试IP",
                "summary": "各产品测试IP",
                "excerpt": "各产品测试IP",
                "cover_image": null,
                "category": "介绍",
                "is_pinned": 0,
                "is_recommended": 0,
                "publish_at": "2025-08-16 12:09:56",
                "updated_at": "2026-06-09 09:29:59"
            },
            {
                "id": 13,
                "title": "各线路介绍",
                "summary": "中国互联网骨干网按运营商概览与比较 中国互联网骨干网按运营商概览与比较 中国电信 中国电信提供了多种骨干网服务，适用于不同层次的需求。 骨干网 (ChinaNet, AS4134) 适合人群：普通家庭用户特点：IP地址以202.97开头，价格便宜，但出国线路容易拥堵。适用场景：日常上网浏览、社交媒体使用。 CN2 GT (半程走 CN2) 适合人群：中端企业用户特点：省级骨干走163（出现202.97节点），出口才走CN2(AS4809",
                "excerpt": "中国互联网骨干网按运营商概览与比较 中国互联网骨干网按运营商概览与比较 中国电信 中国电信提供了多种骨干网服务，适用于不同层次的需求。 骨干网 (ChinaNet, AS4134) 适合人群：普通家庭用户特点：IP地址以202.97开头，价格便宜，但出国线路容易拥堵。适用场景：日常上网浏览、社交媒体使用。 CN2 GT (半程走 CN2) 适合人群：中端企业用户特点：省级骨干走163（出现202.97节点），出口才走CN2(AS4809",
                "cover_image": null,
                "category": "介绍",
                "is_pinned": 0,
                "is_recommended": 0,
                "publish_at": "2025-01-20 17:30:16",
                "updated_at": "2026-06-22 11:54:43"
            }
        ],
        "root_groups": [
            {
                "id": 12,
                "name": "裸金属",
                "slogan": "",
                "product_count": 4,
                "product_type": "type_iwjqnj",
                "product_type_id": 7,
                "product_type_label": "裸金属"
            },
            {
                "id": 14,
                "name": "Gold",
                "slogan": "",
                "product_count": 5,
                "product_type": "dedicated",
                "product_type_id": 2,
                "product_type_label": "游戏云"
            },
            {
                "id": 15,
                "name": "Platinum",
                "slogan": "",
                "product_count": 5,
                "product_type": "dedicated",
                "product_type_id": 2,
                "product_type_label": "游戏云"
            },
            {
                "id": 18,
                "name": "西安",
                "slogan": "",
                "product_count": 0,
                "product_type": "type_tgynng",
                "product_type_id": 9,
                "product_type_label": "物理机"
            },
            {
                "id": 19,
                "name": "2",
                "slogan": "",
                "product_count": 0,
                "product_type": "type_tgynng",
                "product_type_id": 9,
                "product_type_label": "物理机"
            },
            {
                "id": 13,
                "name": "襄阳",
                "slogan": "",
                "product_count": 8,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器"
            },
            {
                "id": 1,
                "name": "美国",
                "slogan": "",
                "product_count": 48,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器"
            },
            {
                "id": 2,
                "name": "香港",
                "slogan": "",
                "product_count": 10,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器"
            },
            {
                "id": 10,
                "name": "内蒙古电信",
                "slogan": "",
                "product_count": 6,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器"
            },
            {
                "id": 9,
                "name": "西安高防",
                "slogan": "",
                "product_count": 5,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器"
            },
            {
                "id": 8,
                "name": "轻量云",
                "slogan": "",
                "product_count": 10,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器"
            },
            {
                "id": 7,
                "name": "十堰高宽",
                "slogan": "",
                "product_count": 5,
                "product_type": "vps",
                "product_type_id": 1,
                "product_type_label": "云服务器"
            },
            {
                "id": 5,
                "name": "云电脑",
                "slogan": "",
                "product_count": 4,
                "product_type": "domain",
                "product_type_id": 4,
                "product_type_label": "云电脑"
            }
        ],
        "group_catalog_map": {
            "12": {
                "preview_products": [
                    {
                        "id": 78,
                        "effective_product_group_id": 11,
                        "name": "ercs",
                        "display_name": "ercs",
                        "instance_spec_text": "ercs",
                        "instance_spec_alias": "",
                        "primary_price": "299.00"
                    },
                    {
                        "id": 79,
                        "effective_product_group_id": 11,
                        "name": "ercs",
                        "display_name": "ercs",
                        "instance_spec_text": "ercs",
                        "instance_spec_alias": "",
                        "primary_price": "450.00"
                    },
                    {
                        "id": 80,
                        "effective_product_group_id": 11,
                        "name": "ercs",
                        "display_name": "ercs",
                        "instance_spec_text": "ercs",
                        "instance_spec_alias": "",
                        "primary_price": "599.00"
                    }
                ],
                "featured_product": {
                    "id": 78,
                    "effective_product_group_id": 11,
                    "name": "ercs",
                    "display_name": "ercs",
                    "instance_spec_text": "ercs",
                    "instance_spec_alias": "",
                    "primary_price": "299.00"
                }
            },
            "14": {
                "preview_products": [
                    {
                        "id": 42,
                        "effective_product_group_id": 14,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "40.00"
                    },
                    {
                        "id": 43,
                        "effective_product_group_id": 14,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "50.00"
                    },
                    {
                        "id": 44,
                        "effective_product_group_id": 14,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "65.00"
                    }
                ],
                "featured_product": {
                    "id": 42,
                    "effective_product_group_id": 14,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "40.00"
                }
            },
            "15": {
                "preview_products": [
                    {
                        "id": 82,
                        "effective_product_group_id": 15,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "48.00"
                    },
                    {
                        "id": 117,
                        "effective_product_group_id": 15,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "88.00"
                    },
                    {
                        "id": 83,
                        "effective_product_group_id": 15,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "90.00"
                    }
                ],
                "featured_product": {
                    "id": 82,
                    "effective_product_group_id": 15,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "48.00"
                }
            },
            "18": {
                "preview_products": [],
                "featured_product": null
            },
            "19": {
                "preview_products": [
                    {
                        "id": 94,
                        "effective_product_group_id": 19,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "45.00"
                    },
                    {
                        "id": 95,
                        "effective_product_group_id": 19,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "65.00"
                    },
                    {
                        "id": 96,
                        "effective_product_group_id": 19,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "85.00"
                    }
                ],
                "featured_product": {
                    "id": 94,
                    "effective_product_group_id": 19,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "45.00"
                }
            },
            "13": {
                "preview_products": [
                    {
                        "id": 27,
                        "effective_product_group_id": 13,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "45.00"
                    },
                    {
                        "id": 28,
                        "effective_product_group_id": 13,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "55.00"
                    },
                    {
                        "id": 29,
                        "effective_product_group_id": 13,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "70.00"
                    }
                ],
                "featured_product": {
                    "id": 27,
                    "effective_product_group_id": 13,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "45.00"
                }
            },
            "1": {
                "preview_products": [
                    {
                        "id": 6,
                        "effective_product_group_id": 1,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "20.00"
                    },
                    {
                        "id": 7,
                        "effective_product_group_id": 1,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "30.00"
                    },
                    {
                        "id": 8,
                        "effective_product_group_id": 1,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "40.00"
                    }
                ],
                "featured_product": {
                    "id": 6,
                    "effective_product_group_id": 1,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "20.00"
                }
            },
            "2": {
                "preview_products": [
                    {
                        "id": 22,
                        "effective_product_group_id": 2,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "23.00"
                    },
                    {
                        "id": 23,
                        "effective_product_group_id": 2,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "35.00"
                    },
                    {
                        "id": 24,
                        "effective_product_group_id": 2,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "45.00"
                    }
                ],
                "featured_product": {
                    "id": 22,
                    "effective_product_group_id": 2,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "23.00"
                }
            },
            "10": {
                "preview_products": [
                    {
                        "id": 52,
                        "effective_product_group_id": 10,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "25.00"
                    },
                    {
                        "id": 53,
                        "effective_product_group_id": 10,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "30.00"
                    },
                    {
                        "id": 56,
                        "effective_product_group_id": 10,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "75.00"
                    }
                ],
                "featured_product": {
                    "id": 52,
                    "effective_product_group_id": 10,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "25.00"
                }
            },
            "9": {
                "preview_products": [
                    {
                        "id": 42,
                        "effective_product_group_id": 14,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "40.00"
                    },
                    {
                        "id": 43,
                        "effective_product_group_id": 14,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "50.00"
                    },
                    {
                        "id": 44,
                        "effective_product_group_id": 14,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "65.00"
                    }
                ],
                "featured_product": {
                    "id": 42,
                    "effective_product_group_id": 14,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "40.00"
                }
            },
            "8": {
                "preview_products": [
                    {
                        "id": 74,
                        "effective_product_group_id": 8,
                        "name": "gscs-nat",
                        "display_name": "gscs-nat",
                        "instance_spec_text": "gscs-nat",
                        "instance_spec_alias": "",
                        "primary_price": "5.00"
                    },
                    {
                        "id": 75,
                        "effective_product_group_id": 8,
                        "name": "gscs-nat",
                        "display_name": "gscs-nat",
                        "instance_spec_text": "gscs-nat",
                        "instance_spec_alias": "",
                        "primary_price": "10.00"
                    },
                    {
                        "id": 76,
                        "effective_product_group_id": 8,
                        "name": "gscs-nat",
                        "display_name": "gscs-nat",
                        "instance_spec_text": "gscs-nat",
                        "instance_spec_alias": "",
                        "primary_price": "14.00"
                    }
                ],
                "featured_product": {
                    "id": 74,
                    "effective_product_group_id": 8,
                    "name": "gscs-nat",
                    "display_name": "gscs-nat",
                    "instance_spec_text": "gscs-nat",
                    "instance_spec_alias": "",
                    "primary_price": "5.00"
                }
            },
            "7": {
                "preview_products": [
                    {
                        "id": 72,
                        "effective_product_group_id": 7,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "9.90"
                    },
                    {
                        "id": 70,
                        "effective_product_group_id": 7,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "19.90"
                    },
                    {
                        "id": 71,
                        "effective_product_group_id": 7,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "29.90"
                    }
                ],
                "featured_product": {
                    "id": 72,
                    "effective_product_group_id": 7,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "9.90"
                }
            },
            "5": {
                "preview_products": [
                    {
                        "id": 47,
                        "effective_product_group_id": 5,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "22.00"
                    },
                    {
                        "id": 48,
                        "effective_product_group_id": 5,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "30.00"
                    },
                    {
                        "id": 49,
                        "effective_product_group_id": 5,
                        "name": "gscs",
                        "display_name": "gscs",
                        "instance_spec_text": "gscs",
                        "instance_spec_alias": "",
                        "primary_price": "40.00"
                    }
                ],
                "featured_product": {
                    "id": 47,
                    "effective_product_group_id": 5,
                    "name": "gscs",
                    "display_name": "gscs",
                    "instance_spec_text": "gscs",
                    "instance_spec_alias": "",
                    "primary_price": "22.00"
                }
            }
        }
    },
    "timestamp": 1783240541
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:41  
· 响应状态码：200  
· 调用方式：GET /api/site/home  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteHomeController@index`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
