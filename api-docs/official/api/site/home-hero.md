# home-hero

**请求方法**：GET  
**请求路径**：`/api/site/home-hero`  
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
| data.slides | array | 真实调用返回字段 |
| data.slides.key | string | 真实调用返回字段 |
| data.slides.rail_title | string | 真实调用返回字段 |
| data.slides.title | string | 真实调用返回字段 |
| data.slides.desc | string | 真实调用返回字段 |
| data.slides.primary_text | string | 真实调用返回字段 |
| data.slides.primary_path | string | 真实调用返回字段 |
| data.slides.secondary_text | string | 真实调用返回字段 |
| data.slides.secondary_path | string | 真实调用返回字段 |
| data.slides.shape | string | 真实调用返回字段 |
| data.slides.video | string | 真实调用返回字段 |
| data.slides.ribbon | string | 真实调用返回字段 |
| data.slides.ribbon_type | string | 真实调用返回字段 |
| data.features | array | 真实调用返回字段 |
| data.features.key | string | 真实调用返回字段 |
| data.features.kicker | string | 真实调用返回字段 |
| data.features.title | string | 真实调用返回字段 |
| data.features.desc | string | 真实调用返回字段 |
| data.features.path | string | 真实调用返回字段 |
| timestamp | integer | Unix 秒级时间戳 |

### 返回示例（完整 JSON）
```json
{
    "code": 0,
    "message": "操作成功",
    "data": {
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
    "timestamp": 1783240541
}
```

### 调用记录
· 调试时间：2026-07-05 16:35:41  
· 响应状态码：200  
· 调用方式：GET /api/site/home-hero  
· 验证方式：真实调用；接口被判定为无破坏性或仅影响本轮临时 token  
· 脱敏说明：token、password、secret、key、authorization 等敏感字段已脱敏

### 源码依据
· 控制器动作：`App\Http\Controllers\SiteHomeController@hero`  
· 请求校验：`无 FormRequest`  
· 响应结构：`统一响应外层来自 App\Support\ApiResponseBuilder`  
· 中间件：`api`
