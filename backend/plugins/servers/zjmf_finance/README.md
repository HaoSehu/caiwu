# ZJMF 财务接口 插件

该插件把 ZJMF 财务接口 接入 Caiwu 的 `upstream` 能力域，slug 为 `zjmf_finance`、plugin_key 为 `zjmf_finance_api`。

## 目录结构

```text
backend/plugins/servers/zjmf_finance/
├── ZjmfFinancePlugin.php          插件入口（manifest、路由与调度注册）
├── config.php                     插件配置声明
├── logic/ZjmfFinance.php          插件动作入口（管理端/控制台动作分发）
├── lib/
│   ├── ZjmfFinanceDriver.php                     上游驱动门面（对外能力出口）
│   ├── ZjmfFinanceAdapter.php                    驱动门面到各域协作者的适配层
│   ├── ZjmfFinanceTransport.php                  HTTP 传输、JWT 缓存、登录端点策略、端点白名单
│   ├── ZjmfLoginEndpointPolicy.php               登录端点白名单策略
│   ├── ZjmfAuthManager.php                       登录换 JWT 与凭证解析
│   ├── ZjmfCredentialParser.php                  供应商备注里的 ZJMF_* Cookie 解析
│   ├── ZjmfLegacyPasswordVerifier.php            旧口令格式校验
│   ├── ZjmfUpstreamResponse.php                  上游响应契约（取载荷/账单号/主机号/成功判定/失败翻译）
│   ├── ZjmfCartPayloadBuilder.php                开通购物车载荷构建（配置项映射、货币、操作系统、密码）
│   ├── ZjmfProvisionService.php                  开通编排（清车幂等门 → 加购 → 结算 → 余额支付 → 校验主机 → 快照）
│   ├── ZjmfRenewService.php                      续费编排（续费页 → 续费下单 → 余额支付 → 待支付回落）
│   ├── ZjmfCatalogService.php                    目录同步（商品树、批量明细、价格与库存补齐）
│   ├── ZjmfStatusService.php                     服务状态同步（运行态批量查询与降级口径）
│   ├── ZjmfConsoleService.php                    控制台动作（详情、电源、模块内容、流量包）
│   ├── ZjmfNetworkService.php                    NAT/网络与流量包（旧版页面入口）
│   ├── ZjmfSecurityService.php                   安全组与 VNC 相关能力
│   ├── ZjmfBillingRestoreService.php             账单恢复（从上游 SQL 转储回填本地账单）
│   ├── ZjmfBillingRestoreProfile.php             账单恢复的目标表与列画像
│   ├── ZjmfProductTypeMapper.php                 上游产品类型映射
│   ├── ZjmfInventoryAndServiceSyncHook.php       库存与服务同步钩子
│   ├── ZjmfInventoryAndServiceSyncTask.php       库存与服务同步任务
│   ├── ZjmfScheduledAuthRefreshHook.php          定时 JWT 刷新钩子
│   └── ZjmfScheduledAuthRefreshTask.php          定时 JWT 刷新任务
└── tests/                          PHPUnit 用例（插件类需在测试里手动 require）
```

## 数据流

上游凭证不在插件配置里：`PluginBindingResolver` 把「供应商」记录（接口地址 + 登录账号/密钥，或备注里的 ZJMF_* Cookie）注入运行期模型，`ZjmfAuthManager` 用 `POST /zjmf_api_login` 换 JWT，`ZjmfFinanceTransport` 按供应商维度缓存 JWT、401 时失效重登，并用端点白名单拒绝非财务接口。四类业务域各自独立，不共享状态：

| 域 | 编排协作者 | 调用链 |
| --- | --- | --- |
| 目录同步 | `ZjmfCatalogService` | `GET /cart/all` 取商品树 → `POST /api/product/prodetail` 分批补价格与库存（单批上限 500）→ 选中商品详情缺失时回落 `GET /cart/get_product_config` 单商品补齐 |
| 开通 | `ZjmfProvisionService` + `ZjmfUpstreamResponse` + `ZjmfCartPayloadBuilder` | 登录 → `POST /cart/clear` 幂等门（带 downstream_* 时，命中已开通回 400+hostid 则收编既有实例，命中待支付回 200+invoiceid+hostid 则补付，都不再加购）→ `GET /cart/get_product_config` → 构建载荷 → `POST /cart/add_to_shop` → `POST /cart/settle` → `POST /apply_credit` 用供应商余额支付 → `GET /host/header` 校验主机 → 写开通快照；`finally` 兜底清车 |
| 续费 | `ZjmfRenewService` | `GET /host/renewpage` 取续费页 → `POST /host/renew` 生成续费账单 → `POST /pay` 余额支付 → `POST /check_order` 校验订单状态；余额不足或支付失败时回落成待支付账单，不改本地可见状态 |
| 账单恢复 | `ZjmfBillingRestoreService` + `ZjmfBillingRestoreProfile` | 读上游 SQL 转储 → 按目标表画像解析 → 事务内按主键覆盖/补齐本地账单（仅运维手工触发） |
| 服务状态 | `ZjmfStatusService` | `GET /host/header` 与 `GET /provision/default` 批量取运行态，按状态决定是否保留本地操作提示 |
| 控制台动作 | `ZjmfConsoleService` + `ZjmfNetworkService` + `ZjmfSecurityService` | 详情 `GET /host/header`、电源 `POST /dcim/refresh_power_status`、模块内容 `GET /zjmf_api/provision/custom/content`、流量包 `GET /servicedetail` + `POST /dcim/buy_flow_packet` |

平台侧的订单、账单、服务状态、审计日志、限流与幂等由服务层负责，插件只做协议适配与协议级错误翻译：

- 上游失败统一经 `ZjmfUpstreamResponse::assertSuccess()` / `throwFailure()` 翻译；命中售罄词表时抛 `UpstreamStockShortageException`，供开通编排层校准本地库存。
- 响应取字段口径集中在 `ZjmfUpstreamResponse`（兼容 `hostid`/`host_id`/`hostids`/`host_ids`、`invoiceid`/`invoice_id`），不要在业务类里再写第二份。

## 上游接口清单

| 方法 | 路径 | 用途 | 主要消费方 |
| --- | --- | --- | --- |
| POST | `/zjmf_api_login` | 登录换取 JWT | `ZjmfAuthManager` |
| GET | `/v1/user` | 校验当前登录身份 | `ZjmfFinanceTransport` |
| GET | `/cart/all` | 商品树（不含价格与库存） | `ZjmfCatalogService` |
| POST | `/api/product/prodetail` | 商品批量明细（价格、库存，单批 500） | `ZjmfCatalogService` |
| GET | `/cart/get_product_config` | 单商品配置项与价格兜底 | `ZjmfCatalogService`、`ZjmfProvisionService` |
| GET | `/cart/credit` | 供应商余额 | `ZjmfFinanceTransport` |
| POST | `/cart/add_to_shop` | 加入购物车 | `ZjmfProvisionService` |
| POST | `/cart/settle` | 结算生成账单 | `ZjmfProvisionService` |
| POST | `/apply_credit` | 用供应商余额支付账单 | `ZjmfProvisionService`、`ZjmfRenewService` |
| POST | `/cart/clear` | 清空购物车（兼作幂等门查询） | `ZjmfProvisionService` |
| GET | `/host/list` | 按主机名查找实例 | `ZjmfProvisionService` |
| GET | `/host/header` | 实例详情与运行态 | `ZjmfFinanceTransport`、`ZjmfConsoleService`、`ZjmfStatusService` |
| GET | `/host/renewpage` | 续费页数据 | `ZjmfFinanceTransport` |
| POST | `/host/renew` | 生成续费账单 | `ZjmfRenewService` |
| POST | `/pay` | 支付账单 | `ZjmfRenewService` |
| POST | `/check_order` | 校验订单支付状态 | `ZjmfRenewService` |
| GET | `/provision/default` | 产品开通模块默认内容 | `ZjmfConsoleService`、`ZjmfStatusService` |
| GET | `/zjmf_api/provision/custom/content` | 产品自定义模块内容 | `ZjmfConsoleService` |
| POST | `/dcim/refresh_power_status` | 刷新电源状态（仅 dcim 型产品） | `ZjmfConsoleService` |
| GET | `/servicedetail` | 旧版流量包购买页 | `ZjmfFinanceTransport`、`ZjmfNetworkService` |
| POST | `/dcim/buy_flow_packet` | 购买流量包 | `ZjmfFinanceTransport`、`ZjmfNetworkService` |

`ZjmfFinanceTransport` 的白名单与上表一致：除 `/zjmf_api_login` 外，只有 `/v1/`、`/cart/`、`/host/`、`/provision/`、`/api/`、`/apply_credit`、`/dcim/`、`/pay`、`/check_order`、`/servicedetail` 前缀的请求会被发出。

## 配置项

在后台插件管理中按下列字段填写；标记为「加密」的字段保存后不会明文回显。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `provider_key` | readonly | 否 | 否 | 供应商绑定 provider_key 必须保持该值，不要别名为 hosting_panel_api。 |

## 供应商侧配置

接口地址、登录账号与密钥不在插件配置里，由「供应商」记录维护（凭证经 `PluginBindingResolver::supplierWithRuntimeCredentials()` 注入运行期模型）。插件的 `provider_key = zjmf_finance_api` 必须保持该值，不要别名为 `hosting_panel_api`。

## 测试

`tests/` 下有 9 个 PHPUnit 文件（开通、续费、目录、状态、控制台、网络、传输、鉴权、账单恢复）。改动 `lib/` 后至少跑：

```bash
cd backend && php artisan test --filter=Zjmf
```

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
