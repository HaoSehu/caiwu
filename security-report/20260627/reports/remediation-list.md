# 修复建议清单

## P0

### 1. 停止通过 URL 传递登录凭证

- 移除官网对 `_token` 查询参数的登录态恢复。
- 管理员代登录改为后端中转页或一次性 POST 交换，不在 URL 中放 `code`。
- 清理相关前端日志、埋点、跳转链路，避免凭证进入历史记录和 Referrer。

### 2. 降低 VNC 页面主登录 token 暴露面

- 删除 VNC 页面从 `window.parent` / `window.opener` / `localStorage` 读取 `admin_token/client_token` 的逻辑。
- 为 VNC 独立签发最小权限、短时效、单用途访问凭证。
- 如果必须跨窗口通信，改为显式 `postMessage` 协商并严格校验来源。

### 3. 将代登录从普通用户管理权限拆分

- 新增独立权限，例如 `user.login_as`。
- 增加二次确认、审批或 step-up auth。
- 对每次 impersonation 记录更完整的审计信息，包括操作人、目标用户、来源 IP、UA、过期时间。

## P1

### 4. 统一前端退出为服务端撤销 token

- `frontend-user-v3-www` 的退出逻辑改为先调用 `/client/auth/logout`，再清本地。
- 如果后端注销失败，可本地兜底，但要显式记录异常。
- 回归时验证多标签页、跨端口、跨子域场景。

### 5. 清理控制台残留的持久化用户态

- `frontend-user-v4-console` 在 401、token 失效、主动退出时同步清理 `client_user`。
- 路由守卫强制以服务端 `getUserInfo()` 为准，不要仅凭本地 `userInfo.name` 放行。
- 需要离线体验时，也应把“仅本地缓存展示”与“真实登录态”区分开。

### 6. 最小化 `/client/auth/info` 返回面

- 将当前认证会话必需字段与实名、支付、余额、推荐收益等字段拆分。
- 仅在对应页面按需请求敏感字段。
- 对 `last_login_ip`、`verification_certify_id`、`alipay_account` 这类字段重新做字段级必要性评估。

## P2

### 7. 强化验证码与找回密码通道防护

- 确认生产环境开启 GeeTest。
- 确认短信/邮件频控配置启用，不仅依赖路由 throttle。
- 将未认证发码场景里对 bearer token 的直接反查，收敛到统一认证判定路径。

### 8. 扩展对象绑定回归覆盖

- 增加双客户端测试账号，覆盖 A 用户访问 B 用户 `invoice/payment/order/service/ledger`。
- 为 `serviceId + nestedId` 这类双层对象接口加自动化安全回归。

## 建议回归顺序

1. URL 凭证移除。
2. VNC token 读取链路收口。
3. 代登录权限与凭证链路改造。
4. 官网退出与控制台 401 清理。
5. `/client/auth/info` 字段最小化。
6. 双账号横向越权自动化回归。
