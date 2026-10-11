---
status: current
updated: 2026-10-11
owner: backend-platform
---

# 魔方财务同系统对接 API

## 文档用途

- 完整提取自 `zjmf-manger-decoded-main`（魔方财务 3.5.8 全解密版，ThinkPHP 多应用，仅供学习研究），作为**上游侧权威行为参考**与 caiwu `zjmf_finance` 插件的接口对照底稿。
- 路由真源：`data/route/api.php`、`data/route/home.php`；实现真源：`app/api/`、`app/home/controller/`、`app/zjmf.php`、`app/common/logic/Host.php`。
- 项目实际调用的范围与本地落点见[本地对接说明](本地对接说明.md)；本文覆盖解密库全量对接面（含 caiwu 未使用的部分）。

## 通用约定

- 响应统一为 `{"status": <int>, "msg": "<string>", "data": {...}}`。
- `status` 语义：`200` 成功；`1001` 特殊成功（0 元开通 / 余额或信用额支付完成，`data.hostid[]` + `data.url`）；`400` 业务失败；`406` 参数或业务拒绝；`405` 登录失效（调用方应重新登录换 JWT）；`410` 需实名；`415` 需绑手机。
- 时间戳为秒级；金额两位小数；POST 默认 `application/x-www-form-urlencoded`（`commonCurl` 实现）。
- 请求方工具函数 `zjmfCurl($api_id, $path, $data, $timeout, $request)`（`app/zjmf.php`）默认走 JWT 客户通道，`GET` 时参数拼 query。

## 一、鉴权体系（四通道）

| 通道 | 凭证 | 校验方 | 覆盖接口 |
| --- | --- | --- | --- |
| 经销商 Basic | `Authorization: Basic base64(用户名:密码)`，`api` 表 `username` + `md5(password)` + IP 白名单（`api.ip` 逗号分隔） | `app/http/middleware/ApiCheck.php` | `POST /api/host`、`/api/host/free`、`/api/host_server` |
| 客户 JWT | `Authorization: Bearer <jwt>`（`JWT ` 前缀亦可，中间件只取第二段） | `app/http/middleware/Check.php` | home 路由 Check 组：`cart/settle`、`apply_credit`、`provision/default`、`host/*`、`upgrade/*`、`dcim/*`、`user_info` 等 |
| 推送签名 | `downstream_token`（32 位）参与 `signature` 计算 | 接收端自行校验 | `/api/host/sync`、`/api/ticket_reply/sync` |
| OAuth access_token | `access_token`（`md5(jwt)`，缓存 3600 秒） | Oauth 控制器自查 | `/api/oauth/getUserInfo` 等 |

客户 JWT 要点（`Check::checkToken`）：

- HS256，密钥 `config("jwtkey")`；载荷 `{userinfo:{id, username, is_api, contactid}, ip, nbf, exp}`，`/zjmf_api_login` 签发有效期 7200 秒。
- JWT 必须同时存在于缓存 `client_user_login_token_{jwt}`；客户改密后（`client_user_update_pass_{uid}` 晚于 `nbf`）立即失效；可选 `home_ip_check` 开启 IP 绑定。
- 失效统一返回 `status=405`，`zjmfCurl` 收到 405 自动重登一次再试。
- `is_api=1` 客户跳过 `provision/default` 的二次验证，并在下单/支付链路触发下游回写与推送行为。
- `UserCheck` 组（`cart/all`、`cart/get_product_config` 等）为可选鉴权：JWT 有效则按客户身份（特价/等级价），否则按游客放行。

## 二、上游侧：经销商商品接口（无中间件，直接可调）

> 解密版 `data/route/api.php` 中 `api/product/*` 未挂 `ApiCheck`，即不校验 `api` 表与 IP 白名单；实际部署若叠加了全局鉴权以部署为准。

| 方法 | 路径 | 参数 | 返回 | 实现位置 |
| --- | --- | --- | --- | --- |
| GET | `/api/product/proinfo` | `pids[]`（可选，商品 ID 数组） | `data.info[]{id, name, location_version}` + `data.currency`（默认货币代码） | `app/api/controller/ProductController.php:26` |
| GET | `/api/product/prodetail` | `pids[]`（**必传且分批**，超过上游 `concurrent` 上限直接 400"商品数量过多,请分批请求"） | `data.detail{pid: {商品全量：配置项、自定义字段、定价、stock_control, qty, upstream_version…}}` | 同上 `:106` |
| GET | `/api/product/list` | 无（仅 `dcim`/`dcimcloud` 类型） | `data.list[]`（含按 `upstream_price_type=percent` 折算的价格、试用价、特价 `sale_price/bates`） | 同上 `:161` |
| GET | `/api/product/{id}` | 路径参数 id | `data.product{…}` + `customfields[]` | 同上 `:393` |
| GET | `/api/product/{id}/resource` | 路径参数 id | 资源文件下载流 | `Provision::downloadResource` |
| GET | `/api/product/upgrade_product` | — | 路由存在，解密版控制器缺 `getUpgradeProduct` 方法（勘误） | `data/route/api.php:42` |

用途：`proinfo` 的 `location_version` 用于**版本号比对的增量商品同步**；`prodetail` 用于全量详情拉取（caiwu 批量对接 OOM 的分批约束即源于上游该并发上限）。

## 三、上游侧：主机/工单接口

| 方法 | 路径 | 鉴权 | 参数 | 行为 | 实现位置 |
| --- | --- | --- | --- | --- | --- |
| POST | `/api/host` | Basic | `id`（dcimid，必填）、`hostid`、`username/password/port/mainip/assignedips/os/dcim_os/ippassword`（可选字段按存在更新） | 更新下游 host 信息并 `pushHostInfo` 反推 | `app/api/controller/HostController.php:25` |
| POST | `/api/host/free` | Basic | `id[]`（hostid 数组） | 释放 DCIM 设备（清空 dcimid/IP/账密/端口） | 同上 `:121` |
| POST | `/api/host_server` | Basic | `id`（dcimid）、`hostid` | 查 DCIM 服务器 `enable_ip_custom`；开启返回 `data = ip_customid`，否则 `status=400, msg="off"` | 同上 `:85` |
| POST | `/api/host/sync` | 签名 | 见"五、推送与回调" | **部署在下游**，接收上游推送 | 同上 `:173` |
| POST | `/api/ticket_reply/sync` | 上游工单关联（`upstream_tid`） | `tid`、`content`、`attachment[]` | 下游接收上游工单回复 | 同上 `:308` |
| POST | `/api/ticket_reply` | 工单 token | `id`、`content`、`attachment[]`、`token` | 按 `ticket.token` 校验后回复工单 | 同上 `:388` |
| GET/POST | `/api/exec_module_func` | 模块 `_ApiAuth` | `module_name`、`module_func` | 远程执行服务器模块辅助方法；黑名单禁用 create/suspend/renew 等核心命令 | 同上 `:468` |

## 四、上游侧：OAuth 账号打通与客户 API 开通

OAuth（`app/api/controller/OauthController.php`，路由 `data/route/api.php:31-35`）：

| 方法 | 路径 | 参数 | 返回 |
| --- | --- | --- | --- |
| GET | `/api/oauth/logined` | — | 登录页配置 + 当前用户 + `authorize_json_web_token`（自动授权票据，缓存 3600 秒） |
| POST | `/api/oauth/accountGetAccessToken` | `redirect_url`、`action`（phone/phone_code/默认邮箱）+ 对应登录参数 | `data.redirect_url`（追加 `access_token`） |
| POST | `/api/oauth/automaticGetAccessToken` | `redirect_url`、`authorize_json_web_token` | 同上（免登录自动授权） |
| POST | `/api/oauth/accountGetAccessTokenDirect` | `account`（邮箱或手机号）、`password` | `data.access_token` |
| GET | `/api/oauth/getUserInfo` | `access_token` | `data{username, phonenumber, email, credit, user_certifi, real_name}` |

客户侧 API 开通三件套（`app/home/controller/ZjmfFinanceApiController.php`，受总开关 `allow_resource_api` 控制）：

| 方法 | 路径 | 参数 | 说明 |
| --- | --- | --- | --- |
| POST | `/zjmf_finance_api/open` | `api_open`（1/0） | 开通后写 `clients.api_open=1` 与 `api_create_time` |
| POST | `/zjmf_finance_api/reset` | — | 重置 `clients.api_password`（AES 存储，12 位随机） |
| GET | `/zjmf_finance_api/summary` | — | API 概览：密钥明文、代理商品数、API 产品数、昨日请求次数、环比、**豁免产品 `api_user_product{ontrial, qty}`** |

登录换 JWT：

| 方法 | 路径 | 参数 | 返回 | 实现位置 |
| --- | --- | --- | --- | --- |
| POST | `/zjmf_api_login` | `username`（4-20 位，邮箱或手机号）、`password` | `{jwt, status:200}`；失败 `{status:400}` | `app/home/controller/LoginController.php:421` |
| POST | `/resource_login_supplier` | 同上 | 同上 | 同上 `:488`（资源池供应商登录，仅资源池场景） |

`/zjmf_api_login` 校验链：总开关 `allow_resource_api` → `clients.api_open=1` → `aesPasswordEncode(明文)==clients.api_password` → 签发 `{id, username, is_api:1}` 的 JWT。`/resource_login`（无 `_supplier` 后缀）是**资源池平台**的登录端点，不在本解密库路由内。

## 五、推送与回调（上游 → 下游）

### 5.1 下游注册推送目标

`host.stream_info`（JSON）需含 `downstream_url` / `downstream_token`（32 位）/ `downstream_id`（下游 host id），两个写入入口：

- 下单/余额支付时随 `cart/settle`、`apply_credit`、`apply_credit_limit` 传 `downstream_url/downstream_token/downstream_id`（`PayController.php:412-418`）。
- 事后关联：`POST /host/setdownstream {id, pid, downstream_url, downstream_token, downstream_id}`（`app/home/controller/HostController.php:2896`，要求 `id` 属于当前 JWT 客户且 `pid` 与商品一致）。

### 5.2 主机信息推送 `POST {downstream_url}/api/host/sync`

上游触发点：`pushHostInfo($id, $other_field, $type)`（`app/zjmf.php`）——开通（`type=create`，成功/失败都记录 `zjmf_pushhost` 表 `{host_id, url, status, post_data, time, num}`）与信息变更时；开通结果受账单支付流程调用（`Invoices::processPaidInvoice` 链）。

请求字段（form-urlencoded）：

| 字段 | 说明 |
| --- | --- |
| `id` | 下游 host id（= `downstream_id`） |
| `host_id` | 上游 host id |
| `domain / username / password / port` | 主机名、账密（明文）、端口 |
| `dedicatedip / assignedips / os / os_url` | IP 与系统信息 |
| `nextduedate`（可选）/ `domainstatus / suspendreason` | 到期与状态（值域 `Pending/Active/Cancelled/Fraud/Deleted/Suspended`） |
| `type` | `create` 表示开通推送 |
| `signature / rand_str` | 签名 |

签名算法（`createSign/validateSign`，`app/zjmf.php:174-193`）：

```text
参与签名数据 = {id, token, rand_str}   // token = 下游 host.stream_info.token
ksort(data, SORT_STRING) → json_encode → md5 → strtoupper
signature == strtoupper(md5(json)) 即校验通过
```

下游接收端行为参考（`app/api/controller/HostController.php:173 syncInfo`）：校验签名 → 更新 host 字段 → `type=create` 且状态 Pending→Active 时触发欢迎短信/邮件与 RunMap 记录 → 如自身又是别的系统的上游则继续 `pushHostInfo` 链式推送。

### 5.3 工单回复推送 `POST {downstream_url}/api/ticket_reply/sync`

附件先经 `POST {downstream_url}/upload_image` 上传换取 `savename`，再推 `tid / content / attachment[] / signature / rand_str`（`pushTicketReply`，`app/zjmf.php:118`）。成功后上游标记 `ticket_reply.is_deliver=1`。

## 六、下游（客户身份）可调用的同系统接口

> 以下均在 home 路由内，除注明外走客户 JWT；`is_api=1` 由 JWT 载荷携带。参数名与响应键均取自解密源码。

### 6.1 商品与购物车

| 方法 | 路径 | 参数 | 返回/行为 | 实现位置（home/controller/CartController.php） |
| --- | --- | --- | --- | --- |
| GET | `/cart/all` | — | `data{products: 组[{id,name,products:[{id,type,name,description}]}], count, currency}`；隐含/下架/开发者应用商品被排除 | `:513` |
| GET | `/cart/get_product_config` | `pid` | `data{flag(特价), products, customfields[], product_pricings[], config_groups[{options[{sub[{pricings[]}]}]}], advanced[], config_links[]}` | `:538` |
| GET | `/cart/stock_control` | `pid` | `data.product{qty, stock_control, hidden}`；下游加购时**实时查上游库存**的端点 | `:347` |
| GET | `/cart/ontrialmax` | `pid` | `data.product{ontrial, qty}`（`api_user_product` 豁免/试用配额） | `:413` |
| GET | `/cart/hostinfo` | `hostid[]`、`all`（1=附配置） | `data.hosts[]`（含明文密码、配置数组） | `:381` |
| GET | `/cart/summary` | — | API 概览（上游管理下游视角） | `:450` |
| GET | `/cart/credit` | — | 客户余额（caiwu 查上游余额入口） | `:339` |
| POST | `/cart/add_to_shop` | `pid`、`billingcycle`、`qty`、`serverid?`、`configoption{}`、`customfield{}`、`currencyid`、`os{}`、`host?`、`password?`、`hostid?`、`checkout`（0/1）、`is_api=1` | 加入购物车（或 `checkout=1` 直接结算）；zjmf_api/resource 商品会实时调上游 `/cart/stock_control` 与 `/cart/ontrialmax` 校验库存与试用上限 | `:2800` |
| POST | `/cart/settle` | `payment?`、`checkout`、`use_credit`、`pos[]?`、`cart_data?`、`downstream_url?/downstream_token?/downstream_id?` | 生成订单+账单；`200 {invoiceid, hostid?}`（is_downstream 时附 hostid）；`1001 {hostid[], url}` 为 0 元开通；`406` 购物车错误；`410` 需实名；`415` 需绑手机 | `:1873` |
| POST | `/cart/clear` | — | 清空购物车 | `:3436` |

### 6.2 支付与余额

| 方法 | 路径 | 参数 | 返回/行为 | 实现位置 |
| --- | --- | --- | --- | --- |
| POST | `/apply_credit` | `invoiceid`、`use_credit`（1/0）、`enough`（1=余额必须够付）、`is_api=1`、`downstream_url/token/id?` | `200 {invoiceid, url}` 部分抵扣；`1001 {hostid[], url}` 全额支付完成（触发 `processPaidInvoice` 开通链，并回写 `host.stream_info` 供推送）；`400 "余额不足：账单需x，现余额y[,上游账单#id完成支付后即可开通]"` | `app/home/controller/PayController.php:341` |
| POST | `/apply_credit_limit` | `invoiceid`、`use_credit_limit`（1/0）、`enough?`、`downstream_*?` | 信用额支付，全额成功 `1001 {hostid[], url}`；与余额互斥 | 同上 `:439` |
| POST | `/check_order` | `id`（账单 id） | `status=1000` 已支付；`1001` 未支付（caiwu 续费轮询入口） | `app/home/controller/OrderController.php:137` |
| POST | `/pay` | `action=billing`、`invoiceid`、`use_credit?` | 走网关支付页/提交（caiwu 续费支付入口）；`action=recharge` 为充值 | `app/home/controller/ViewClientsController.php:1925` |

### 6.3 产品生命周期

| 方法 | 路径 | 参数 | 返回/行为 | 实现位置 |
| --- | --- | --- | --- | --- |
| POST | `/provision/default` | `func`、`id`（上游 host id；`on/off/reboot/hard_off/hard_reboot/status` 支持数组批量）、`os?/port?/password?/system?/reason?`、`is_api=1` | 统一模块动作入口：`on/off/reboot/hard_off/hard_reboot/vnc/status/reinstall/crack_pass/rescue_system/suspend/unsuspend`；批量时先回 `200 "请求发起成功"` 后台执行；`is_api` 跳过二次验证 | `app/home/controller/ProvisionController.php:24` |
| GET | `/host/header` | `host_id`（上游 host id）、`source=API`、`nat?` | `data{host_data{域名/IP/账密/状态/流量/周期/余额相关/allow_upgrade_*…}, server_data, custom_field_data, module_button, module_client_area, dcim{flowpacket}}`；caiwu 状态同步主接口 | `app/home/controller/HostController.php:1320` |
| POST | `/host/renew` | `hostid`、`billingcycles?` | `200 {invoiceid, payment}`（生成续费账单） | 同上 `:2323` |
| POST | `/host/autorenew` | `hostid`、`initiative_renew` | 设置自动续费开关 | 同上 `:2351` |
| POST | `/host/cancel` | `id`、`type`（`Immediate`/`Endofbilling`）、`reason` | `200` 创建取消申请；`is_api` 且产品不存在/已删除时幂等返回 200 | 同上 `:2758` |
| DELETE | `/host/cancel` | `id` | 删除取消申请 | 同上 `:2813` |
| POST | `/host/setdownstream` | `id`、`pid`、`downstream_url`、`downstream_token`（32 位）、`downstream_id` | 关联下游推送目标（见 5.1） | 同上 `:2896` |

下游侧对上游的调用对照（`app/common/logic/Host.php`）：暂停 `provision/default {id: dcimid, func: suspend, reason, is_api:1}`（`:328-333`）；取消 `/host/cancel`（`:533`）；主机信息 `/host/header`（`:633`）；续费 `/host/renew` + 余额不足时 `/apply_credit`（`:1796-1801`）；升降配 `/upgrade/*` + `/apply_credit`（`:1476-1506`）。

### 6.4 升降配

| 方法 | 路径 | 参数 | 行为 | 实现位置（home/controller/UpgradeController.php） |
| --- | --- | --- | --- | --- |
| POST | `/upgrade/upgrade_config_post` | `hid`、`configoption{配置项ID: 子项ID或数量}`、`currencyid?`、`pormo_code?` | 校验并缓存选择（`upgrade_down_config_{hid}`，86400 秒）；数据盘不可降级 | `:240` |
| POST | `/upgrade/checkout_config_upgrade` | `hid`、`resource_percent_value?` | 读取缓存选择生成升级账单（20 秒防重） | `:404` |
| POST | `/upgrade/upgrade_product_post` | `hid`、`pid`（新产品）、`billingcycle`、`currencyid?` | 缓存新产品选择（`upgrade_down_product_{hid}`） | `:554` |
| POST | `/upgrade/checkout_upgrade_product` | `hid` | 生成产品升级账单 | `:756` |

### 6.5 DCIM 增值与客户资料

| 方法 | 路径 | 参数 | 行为 | 实现位置 |
| --- | --- | --- | --- | --- |
| POST | `/dcim/buy_flow_packet` | `id`（产品 id）、`fid`（流量包 id） | 从上游 `/host/header` 的 `dcim.flowpacket` 取价（按 `upstream_price_type=percent` 加价）生成 `zjmf_flow_packet` 类型账单 → `200 {invoiceid}` | `app/home/controller/DcimController.php:74` |
| POST | `/dcim/buy_reinstall_times` | `id` | 先调上游 `/dcim/check_reinstall` 查价再生成账单 → `200 {invoiceid}` | 同上 `:161` |
| POST | `/dcim/check_reinstall` | `id` | 上游侧查询重装是否需购买及价格（下游被调） | 同库对应实现 |
| GET | `/user_info` | — | `data.user`：clients 全量资料（含 `credit`、`api_open`、`api_password` 明文仅自查）、实名状态、`allow_resource_api` 等 | `app/home/controller/UserController.php:97` |

## 七、下游侧：对接发起方能力方法

配置存储 `zjmf_finance_api` 表（`app/admin/controller/ZjmfFinanceApiController.php:30 createApi`）：`name`、`hostname`（上游地址）、`username`、`password`（AES）、`type`（`zjmf_api` 智简魔方 / `manual` 手动 / `whmcs`）、`is_resource`（资源池）、`contact_way`、`des`、`auto_update`（前台订购实时同步库存商品，默认 1）、`product_num`、`status`。管理端能力：`refreshStatus` 连接测试、`apiProducts/apiOrder/apiHost/apiRenew`（对接商品的订单/主机/续费对账视图）、`upstreamCredit`（上游余额）、`apiLog`（`api_resource_log` 请求日志）、`inputProduct`（导入上游商品）。

核心方法（`app/zjmf.php`）：

| 方法 | 说明 |
| --- | --- |
| `zjmfCurl($api_id, $path, $data, $timeout, $request)` | 统一请求入口：`is_resource=1` 走 `/resource_login`（`type=agent`），否则 `/zjmf_api_login`；JWT 缓存 `zjmf_finance_jwt_{id}` 5400 秒；收到 405 强制重登一次 |
| `zjmfCurlHasFile` | 带文件上传版本（multipart） |
| `commonCurl / curlUpload` | 底层 HTTP（form-urlencoded，不校验 SSL）与文件上传 |
| `getZjmfUpstreamProducts($id)` | `GET /cart/all`（资源池走 `resource/products`） |
| `getZjmfUpstreamProductsInfo / Detail($id, $pids)` | `GET /api/product/proinfo|prodetail`（走经销商商品通道，无 JWT） |
| `getZjmfUpstreamProductConfig` | `GET /cart/get_product_config` |
| `getResourceRate($id, $pid)` | `GET /resource/rate`（资源池分销折扣率） |
| `getZjmfUostreamHostInfo($id, $hid)` | `GET /host/header`（命名勘误：Uostream=Downstream） |
| `judgeOntrialNum`（common.php:4779） | `GET /cart/ontrialmax` 试用配额校验 |
| `upstreamBates` | 特价（type=1 比例 / type=2 立减）折算 |
| `ticketDeliver / ticketReplyDeliver`（common.php:6254/6321） | 工单投递：按 `ticket_department_upstream.upstream_dptid` 转上游，JWT 登录后调用上游工单接口 |
| `pushHostInfo / pushTicketReply / createSign / validateSign` | 推送与签名（上游与下游双侧共用，见五） |
| `whmcsCurlPost` | WHMCS 对接：`{hostname}/modules/addons/idcsmart_api/api.php?action=/v1/hosts/{上游hostid}/module/{action}` + `apiname/apikey` |
| `dcim_callback`（common.php:2811） | 重装次数/流量包账单支付后：`/dcim/buy_reinstall_times`、`/dcim/buy_flow_packet`、`/apply_credit` |

管理端另有「上游资源」体系（`app/admin/controller/UpperReachesController.php`，`upper_reaches_res` 表）：IPMI 控制走 `public.api.idcsmart.com` 公共接口，魔方 DCIM 客户端支持 status/on/off/reboot/vnc/reinstall/crackPass。资源池分销侧（`app/admin/controller/AgentController.php`）提供巡检、售后/退款转上游、工单转上游、返利与评估；`resourceCurl`、`resource/products` 等实现由资源池插件/平台提供，不在本解密库路由内。

## 八、与 caiwu 现状对照（差异与缺口）

- caiwu 插件通道（`/zjmf_api_login` JWT + `/cart/all`、`/cart/get_product_config`、`/host/header`、`/host/renew`、`/cart/credit`、`/check_order`、`/pay?action=billing`）在 3.5.8 路由中全部存在且行为一致；`GET /v1/user` **不在**本版本路由（`/v1/*` 属 idcsmart_api 插件或更新版本），对应用户资料应使用 `/user_info` 或 OAuth `getUserInfo`。
- 库存实时校验链：3.5.8 在 `cart/add_to_shop` 内实时调上游 `/cart/stock_control` + `/cart/ontrialmax`——caiwu 排障时"商品缺货/假库存"优先查上游 `products.qty` 与该链路。
- 2026-10-11 已对齐：caiwu 购物车链路（clear/settle/apply_credit）携带 `downstream_*` 注册推送目标，clear 幂等门收编（已付→收编 hostid、未付→补付既有账单）与 settle 1001 防重门已接入 `ZjmfProvisionService`，token 按服务持久化于 `provision_data.downstream_token`。
- caiwu 尚未实现的能力：`/api/host/sync`、`/api/ticket_reply/sync` 接收端（注册后上游推送落 404，不影响开通；接入时按第五节协议实现，需系统级路由）。
- 优惠与豁免：上游客户配额由 `api_user_product{ontrial, qty}` 控制，特价/加价由 `upstream_price_type=percent` 在两侧对称折算。

## 九、解密版勘误与已知缺口

- **上游 `/cart/clear` 幂等门未判空（缺陷）**：`whereLike("stream_info", "%".token."%")` 在不带 `downstream_token` 时退化为 `'%%'` 全表匹配（且无 uid 过滤），任何 is_api 客户的裸清车都可能误报「已开通」/未支付门并携带**他人主机**的 id。调用方约定：**只在携带注册参数时采信门结果**；未注册时对「已开通」400 仅容忍继续，绝不收编。
- `data/route/api.php:42` 的 `api/product/upgrade_product` 与 `:36` 的 `api/botMessage` 在解密控制器中无对应实现（`getUpgradeProduct`、`Bot` 控制器缺失）。
- 错别字：`Exctption`（应为 Exception）、`getZjmfUostreamHostInfo`（应为 Downstream）。
- `app/3.5.8admin` 为空目录；`/resource_login`（资源池平台端点）不在本库，仅 `/resource_login_supplier` 存在。
- 行为以实际部署的上游版本为准；本文与运行上游冲突时，按 AGENTS.md 以运行代码与实库为准并回改本文。
