# 后端 API 清单导航

## 文档用途

- 给 `文档/后端/后端API清单.md` 这份自动生成清单提供一份人类可读的业务导航
- 对齐时间：`2026-04-22`
- 本文手工维护，不会被导出脚本覆盖
- 具体方法、控制器动作、中间件和鉴权仍以 `文档/后端/后端API清单.md` 为准

## 分组速览

| 分组 | 路径范围 | 默认鉴权/特征 |
| --- | --- | --- |
| 管理端 | `/api/admin/*` | 除 `/api/admin/login` 外，默认 `auth:sanctum + ensure.admin`，多数接口叠加 `permission:{code}` |
| 用户端 | `/api/client/*` | 公开认证、回调、VNC Token 和实名回调混在同一前缀下；其余主体接口默认 `auth:sanctum + ensure.client` |
| 公开站点 | `/api/site/*` | 官网、公开产品、公开内容、报价、站点配置 |
| 其他公开/受控接口 | `/api/health`、`/api/secure-assets/view` | 健康检查或按资源参数校验访问 |

## 业务域速览

### 管理端（admin）

| 业务域 | 关键路径前缀 | 说明 |
| --- | --- | --- |
| 认证 | `/api/admin/login`、`/api/admin/auth/*` | 管理员登录、信息、资料更新、退出 |
| 仪表盘 | `/api/admin/dashboard*` | 首页指标与最近账单 |
| 用户 | `/api/admin/users*` | 用户列表、详情、充值、代登录、嵌套账单/服务/日志/工单 |
| 账单 | `/api/admin/invoices*`、`/api/admin/users/{user}/invoices*` | 当前主财务实体是发票/账单，不再以订单路由为主 |
| 商品 | `/api/admin/products*`、`/api/admin/product-categories*`、`/api/admin/product-groups*`、`/api/admin/product-types*` | 商品、分类、分组、类型、批量同步与排序 |
| 服务实例 | `/api/admin/services*`、`/api/admin/users/{user}/services*` | 管理端实例概览与用户下实例操作 |
| 供应商 | `/api/admin/suppliers*` | 供应商余额、商品拉取、批量对接 |
| 优惠券 | `/api/admin/coupons*`、`/api/admin/coupon-campaigns*` | 优惠券与活动发券 |
| 推荐返佣 | `/api/admin/referral*`、`/api/admin/referral-withdrawals*` | 返佣概览、奖励、账变、提现审核 |
| 实名认证 | `/api/admin/verifications*` | 实名审核、详情、历史、解绑 |
| 工单 | `/api/admin/tickets*` | 工单列表、回复、关闭、指派、图片上传 |
| 内容与媒体 | `/api/admin/content*`、`/api/admin/media-files*` | 文章分类、文章内容、媒体库 |
| 日志 | `/api/admin/logs*` | API、短信、邮件、任务、系统、登录日志与清理 |
| 设置与站点运营 | `/api/admin/settings`、`/api/admin/site/home-hero` | 系统配置、站点首页 Hero |
| 调度 | `/api/admin/schedules*` | 调度总览与手动触发 |
| 会员等级 | `/api/admin/member-levels*` | 等级配置 |

### 用户端（client）

| 业务域 | 关键路径前缀 | 说明 |
| --- | --- | --- |
| 认证 | `/api/client/login`、`/api/client/register`、`/api/client/auth/*`、`/api/client/password` | 登录、注册、找回密码、资料、通知偏好、支付宝账号 |
| 实名认证 | `/api/client/verification*` | 状态、初始化、二维码、重试、回调、扫码 |
| 账单 | `/api/client/invoices*` | 当前用户侧下单与支付主实体是发票 |
| 充值 | `/api/client/recharge*` | 充值下单与状态轮询 |
| 服务实例 | `/api/client/services*`、`/api/client/vnc-tokens/*` | 实例详情、监控、续费、重装、VNC、NAT、安全组、流量包 |
| 余额 | `/api/client/balance-logs*` | 余额流水和汇总 |
| 优惠券 | `/api/client/coupons*` | 优惠券、汇总、领取；其中 `coupons/public*` 仍需客户端鉴权 |
| 推荐返佣 | `/api/client/referral*` | 概览、奖励、账变、提现申请 |
| 工单 | `/api/client/tickets*` | 列表、详情、回复、关闭、上传图片 |
| 内容 | `/api/client/content/overview`、`/api/client/notices*`、`/api/client/help-articles*` | 用户侧公告与帮助中心 |
| 管理工具 | `/api/client/blackhole/query` | 当前已落地的客户端工具接口 |
| 支付回调 | `/api/client/payment/alipay/notify` | 支付宝异步通知 |

### 公开站点与其他公开接口

| 业务域 | 关键路径前缀 | 说明 |
| --- | --- | --- |
| 站点配置 | `/api/site/config`、`/api/site/home`、`/api/site/home-hero` | 官网基础配置、首页聚合、首页 Hero |
| 公开商品 | `/api/site/product-types`、`/api/site/product-groups*`、`/api/site/product-categories*`、`/api/site/products*` | 官网商品浏览、库存、报价 |
| 公开内容 | `/api/site/content/overview`、`/api/site/notices*`、`/api/site/help-articles*` | 官网公告与帮助中心 |
| 健康检查 | `/api/health` | 服务可用性探针 |
| 受控资源查看 | `/api/secure-assets/view` | 资源查看，不属于普通公开静态资源 |

## 核心业务流程 -> 关键接口

### 官网选购 -> 创建账单 -> 支付 -> 开通

| 步骤 | 接口 |
| --- | --- |
| 浏览商品 | `GET /api/site/products` |
| 查看详情 | `GET /api/site/products/{productId}` |
| 获取报价 | `POST /api/site/products/{productId}/quote` |
| 创建账单 | `POST /api/client/invoices` |
| 余额支付 | `POST /api/client/invoices/{id}/pay/balance` |
| 支付宝支付 | `POST /api/client/invoices/{id}/pay/alipay` |
| 查询支付状态 | `GET /api/client/invoices/{id}/pay/alipay/status` |
| 查看账单详情 | `GET /api/client/invoices/{id}` |
| 查看服务实例 | `GET /api/client/services/{id}` |

### 服务续费

| 步骤 | 接口 |
| --- | --- |
| 查看续费预览 | `GET /api/client/services/{id}/renew` |
| 生成续费账单 | `POST /api/client/services/{id}/renew` |
| 后续支付 | 复用 `/api/client/invoices/{id}/pay/*` |

### 流量包加购

| 步骤 | 接口 |
| --- | --- |
| 查看流量包列表 | `GET /api/client/services/{id}/traffic-packages` |
| 获取加购报价 | `POST /api/client/services/{id}/traffic-packages/quote` |
| 创建加购单 | `POST /api/client/services/{id}/traffic-packages/order` |

### 实名认证

| 步骤 | 接口 |
| --- | --- |
| 查询状态 | `GET /api/client/verification/status` |
| 获取费用配置 | `GET /api/client/verification/fee-config` |
| 初始化实名 | `POST /api/client/verification/init` |
| 获取二维码 | `POST /api/client/verification/qrcode` |
| 重试流程 | `POST /api/client/verification/restart` |
| 异步回调 | `GET|POST /api/client/verification/callback` |
| 管理端查询 | `GET /api/admin/verifications` |
| 管理端历史 | `GET /api/admin/verifications/{user}/history` |

### 工单

| 步骤 | 接口 |
| --- | --- |
| 用户提交 | `POST /api/client/tickets` |
| 用户回复 | `POST /api/client/tickets/{id}/reply` |
| 用户关闭 | `POST /api/client/tickets/{id}/close` |
| 管理端列表 | `GET /api/admin/tickets` |
| 管理端回复 | `POST /api/admin/tickets/{ticket}/reply` |
| 管理端指派 | `POST /api/admin/tickets/{ticket}/assign` |

### 内容与媒体

| 步骤 | 接口 |
| --- | --- |
| 官网公告列表 | `GET /api/site/notices` |
| 官网帮助列表 | `GET /api/site/help-articles` |
| 用户侧内容总览 | `GET /api/client/content/overview` |
| 管理端文章列表 | `GET /api/admin/content/articles` |
| 管理端上传封面/正文图片 | `POST /api/admin/content/upload-image` |
| 管理端媒体库 | `GET /api/admin/media-files` |

### 站点运营配置

| 步骤 | 接口 |
| --- | --- |
| 拉取系统配置 | `GET /api/admin/settings` |
| 保存系统配置 | `POST /api/admin/settings` |
| 查看首页 Hero | `GET /api/admin/site/home-hero` |
| 更新首页 Hero | `POST /api/admin/site/home-hero` |

## 维护建议

- 新增或移除接口后，先执行 `php backend/scripts/export_api_inventory.php` 重刷 `后端API清单.md`
- 如果业务主实体变化，例如从 `orders` 切到 `invoices`，必须同步更新本文的“业务域速览”和“核心业务流程”
- 新增公开接口时，同时检查它属于：
  - 站点公开
  - 客户端前缀下的公开认证能力
  - 回调接口
  - 受控资源接口
