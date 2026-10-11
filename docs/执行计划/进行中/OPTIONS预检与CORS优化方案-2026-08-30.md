---
status: 进行中
updated: 2026-08-30
owner: HaoSehu
---

# OPTIONS 预检与 CORS 优化方案

## 背景

API 收到大量浏览器发出的 OPTIONS 请求，引发"API 是否应该只有 GET/POST"的疑问。事实核查结论：

- OPTIONS 是浏览器跨域 CORS 预检（preflight）机制。请求只要带 `Authorization` 头或 `Content-Type: application/json` 之一即为非简单请求，浏览器必先发 OPTIONS——与后端定义了几个 HTTP 方法无关（MDN 权威定义）。三个前端均跨域调用独立 API 域名并携带 Bearer token + JSON，预检必然存在。
- 后端方法集现状：`routes/v2-admin.php` 含 PUT 22、PATCH 9、DELETE 18；`routes/v2-client.php` 含 PUT 11、DELETE 3。非简单方法大量存在，属管理后台圈惯例。
- GitHub 调研（2026-08-30）：管理后台圈（RuoYi、eladmin、laravue、vue-vben-admin）普遍使用完整 REST 方法集并在框架层处理预检；"只用 GET/POST"约定仅见于计费/主机面板圈（WHMCS 外部 API 纯 POST、Froxloor 纯 POST+JSON、FOSSBilling 仅 GET/POST），但该类系统调用方是服务端而非浏览器跨域 AJAX，从未面临预检。结论：方法集无需收敛，收敛对 OPTIONS 无任何收益。
- CORS 生效链路已具备：`config/cors.php` 白名单三个前端域名（`FRONTEND_URL`/`CLIENT_CONSOLE_URL`/`ADMIN_URL`），`HandleCors` 为 Laravel 12 框架默认全局中间件，预检在进入业务路由前被短路应答为 204。

可优化点：`cors.php` 的 `max_age = 0` 导致浏览器不缓存预检结果，每次请求都重复发 OPTIONS（日志噪音、网络往返、CDN 回源量）；生产链路（luvipcdn/lucdn CDN → 源站 Nginx → PHP-FPM）对 OPTIONS 的处理从未实测验证。

非目标：不收敛方法集为 GET/POST-only；不把 `allowed_origins` 改为 `*`；不在 Nginx/CDN 层硬编码 OPTIONS 固定响应；不做同源部署改造（仅记录为远期观察项）。

## 范围与验收

- [ ] 步骤 0 诊断：本地与生产对三个前端 Origin 各发 OPTIONS 探针，确认返回 204 且 `Access-Control-Allow-Origin/Methods/Headers` 齐全；确认生产 `.env` 三个域名与线上实际域名一致。
- [ ] CDN（luvipcdn/lucdn）确认：OPTIONS 请求回源透传、不做缓存、不被 WAF/CC 规则拦截。
- [ ] `backend/config/cors.php`：`max_age` 由 `0` 改为 `600`。
- [ ] 后端验证：`cd backend && composer analyse && composer format:check`；本地起服后 OPTIONS 探针首返 204。
- [ ] 前端回归：三端登录、列表、编辑（PUT）、删除（DELETE）走一遍；浏览器 Network 面板确认同窗口第二次请求不再出现 OPTIONS。
- [ ] 生产部署后观察 API 日志 OPTIONS 占比回落，无新增 CORS 报障。

## 实施步骤

0. 确认本方案后先执行诊断（步骤 0），诊断结果决定后续是否需补 `.env` 域名或调整 CDN 规则。
1. 修改 `backend/config/cors.php` 的 `max_age` 为 `600`。
2. 跑后端验证（composer analyse / format:check）与本地 OPTIONS 探针。
3. 部署生产，配合在 CDN 控制台确认 OPTIONS 回源与不缓存。
4. 按验收项回归三端并观察日志。

## 风险与回滚

- 预检缓存语义：`max_age` 变大后，若未来调整 `allowed_headers`/`allowed_methods`/`allowed_origins`，浏览器最长 10 分钟仍按旧规则预检，属标准 CORS 权衡，可接受。
- CDN 缓存风险：预检响应含 Origin 相关头，若被 CDN 缓存可能串域导致 CORS 失败——故 CDN 必须配置为不缓存 OPTIONS，属于本方案的强制前置条件。
- 域名白名单风险：若生产 `.env` 域名与线上实际 Origin 不一致，OPTIONS 将缺失允许头、前端直接 CORS 失败——诊断步骤 0 先行排除。
- 回滚：`max_age` 改回 `0` 重新部署即可；CDN 规则在控制台还原；诊断与配置改动均无数据风险。

## 进度

- [x] 建立执行计划并登记（README 表格 + catalog.json）。
- [x] `max_age` 预检缓存（目标已达成）：已由 `ddd916f1`《性能优化与稳定性治理》将 `config/cors.php` 的 `max_age` 调整为 `86400`（较本方案建议的 600 更激进，已随版本在跑）。
- [ ] 步骤 0 诊断（生产三 Origin 探针 + env 域名核对 + CDN 规则确认）——属生产运维动作，未执行。

## 决策日志

| 日期       | 决策                                   | 原因                                                                                                                          |
| ---------- | -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| 2026-08-30 | 不收敛 GET/POST-only                    | 预检由 Authorization/JSON 头触发，与方法集无关；33 个非简单方法路由与三端前端全量改造成本高、收益为零；管理后台圈惯例即完整方法集。 |
| 2026-08-30 | `max_age` 取 600（10 分钟）             | 同一会话窗口内的重复预检全部消除、收益覆盖绝大部分场景；配置变更后恢复窗口短，权衡优于 3600/86400。                          |
| 2026-08-30 | 不在 Nginx/CDN 硬编码 OPTIONS 固定响应  | 预检响应必须携带 Origin 相关的 CORS 头，硬编码易丢头导致跨域失败；由 HandleCors 短路应答是标准做法。                           |