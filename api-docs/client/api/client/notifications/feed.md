# 铃铛下拉：最新若干条合并消息 */

**请求方法**：GET  
**请求路径**：`/api/client/notifications/feed`  
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
| data.list.id | string | 真实调用返回字段 |
| data.list.raw_id | integer | 真实调用返回字段 |
| data.list.source | string | 真实调用返回字段 |
| data.list.type | string | 真实调用返回字段 |
| data.list.type_label | string | 真实调用返回字段 |
| data.list.title | string | 真实调用返回字段 |
| data.list.summary | string | 真实调用返回字段 |
| data.list.link | string | 真实调用返回字段 |
| data.list.read | boolean | 真实调用返回字段 |
| data.list.created_at | string | 真实调用返回字段 |
| data.unread_count | integer | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
        "list": [
            {
                "id": "msg-32",
                "raw_id": 32,
                "source": "message",
                "type": "order_paid",
                "type_label": "订购提醒",
                "title": "开通成功",
                "summary": "「gscs-2vcpu-2gib」已处理完成，账单号 zd202607042209238520。",
                "link": "/client/services/189",
                "read": false,
                "created_at": "2026-07-04 22:09:59"
            },
            {
                "id": "msg-31",
                "raw_id": 31,
                "source": "message",
                "type": "order_paid",
                "type_label": "订购提醒",
                "title": "开通成功",
                "summary": "「gscs-2vcpu-2gib」已处理完成，账单号 zd202607042134301239。",
                "link": "/client/services/188",
                "read": false,
                "created_at": "2026-07-04 21:37:09"
            },
            {
                "id": "msg-30",
                "raw_id": 30,
                "source": "message",
                "type": "order_paid",
                "type_label": "订购提醒",
                "title": "开通成功",
                "summary": "「gscs-2vcpu-2gib」已处理完成，账单号 zd202607042121220951。",
                "link": "/client/services/187",
                "read": false,
                "created_at": "2026-07-04 21:21:50"
            },
            {
                "id": "notice-21",
                "source": "notice",
                "type": "notice",
                "type_label": "系统公告",
                "title": "【关于全面推行实名认证的通知】",
                "summary": "",
                "link": "/client/notices/21",
                "read": false,
                "created_at": "2026-05-27 16:01:53"
            },
            {
                "id": "notice-15",
                "source": "notice",
                "type": "notice",
                "type_label": "系统公告",
                "title": "创欧云计算 · 国庆狂欢盛典",
                "summary": "创欧云计算-国庆节活动? 活动时间：2025.10.1 – 2025.10.3 ️⃣ 充值活动 用户充值 100元以上 返现 10% 用户预存 500元 即可升级为 铂金代理（海外、国内产品 8 折 购入/续费，不包括轻量云和活动机型） ️⃣ 新购折扣 优惠码：国庆节快乐 8折 购入，续费同 8 折 注：不包括轻量云和活动机型 （活动最终解释权归创欧云计算所有）",
                "link": "/client/notices/15",
                "read": false,
                "created_at": "2025-10-01 11:47:41"
            },
            {
                "id": "notice-14",
                "source": "notice",
                "type": "notice",
                "type_label": "系统公告",
                "title": "关于自动续费的说明",
                "summary": "自动续费功能可能存在系统异常，会导致自动续费失败的情况。为避免因自动续费未成功导致服务中断或数据丢失，强烈建议您手动进行续费，以确保续费流程稳定可靠。 感谢您的理解与配合！如有任何疑问，请及时联系客服协助处理。",
                "link": "/client/notices/14",
                "read": false,
                "created_at": "2025-09-19 09:41:44"
            },
            {
                "id": "notice-16",
                "source": "notice",
                "type": "notice",
                "type_label": "系统公告",
                "title": "宁波，成都，德阳业务调整",
                "summary": "月23日﹣6月26日，成都（沙渠）云机器将发往宁波机房。业务补偿5天，此机器／配置将不再售卖 月30日，宁波业务将搬移机柜并更换IP。业务补偿3天，业务将调整为双向计费。由于机器需要维修，中断时间可能大于3小时。 月30日前，德阳／成都（沙渠）托管业务／成都挂机宝业务将发往成都（西信）机房并更换IP。业务补偿3天，免费升级150G防御，支持屏蔽UDP。机器将当日下架当日寄出当日上架，中断不超过24小时",
                "link": "/client/notices/16",
                "read": false,
                "created_at": "2025-06-23 05:15:37"
            },
            {
                "id": "notice-18",
                "source": "notice",
                "type": "notice",
                "type_label": "系统公告",
                "title": "美国三网精品迁移通知",
                "summary": "美国三网精品迁移通知 由于上游租用服务商处理效率低，网络波动且找不到原因，决定将迁移AMD节点到netlab机房，所有已开通配置带宽免费升级20M，补偿3天。 涉及Ip段38.148.241 38.148.246",
                "link": "/client/notices/18",
                "read": false,
                "created_at": "2025-06-23 05:14:04"
            },
            {
                "id": "notice-17",
                "source": "notice",
                "type": "notice",
                "type_label": "系统公告",
                "title": "服务条款",
                "summary": "协议说明 本协议是创欧云数据（简称“创欧云”）与您之间就您使用创欧云服务的相关事项签订的合同。为使用创欧云服务，您应当充分阅读、理解本协议。“用户”是被创欧云提供服务的客户。 您通过勾选、点击确认或以其他方式表示接受本协议，或注册成功，或您以任何方式使用创欧云服务的，即视为您已阅读、理解本协议并同意接受本协议的约束，本协议即在您与创欧云之间产生法律效力。 禁止的内容 创欧云服务仅限合法用途使用，创欧云不对用户使用其产品/服务所产生的行为",
                "link": "/client/notices/17",
                "read": false,
                "created_at": "2025-01-21 12:20:17"
            },
            {
                "id": "notice-20",
                "source": "notice",
                "type": "notice",
                "type_label": "系统公告",
                "title": "隐私政策",
                "summary": "致力于保护您的隐私 本隐私政策最后更新于2025年8月31日。 本协议是创欧云数据（简称“创欧云”）与您之间就您使用创欧云服务的相关事项签订的合同。为使用创欧云服务，您应当充分阅读、理解本协议。“用户”是被创欧云提供服务的客户。 您通过勾选、点击确认或以其他方式表示接受本协议，或注册成功，或您以任何方式使用创欧云服务的，即视为您已阅读、理解本协议并同意接受本协议的约束，本协议即在您与创欧云之间产生法律效力。 尊敬的用户，欢迎使用创欧云服",
                "link": "/client/notices/20",
                "read": false,
                "created_at": "2025-01-21 11:33:35"
            }
        ],
        "unread_count": 11
    },
    "timestamp": 1783240527
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:27  
· 响应状态码：200  
· 调用方式：GET /api/client/notifications/feed  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\Client\NotificationController@feed`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder；控制器 success([...]) 数组字段`  
· 中间件：`api, auth:sanctum, ensure.client`
