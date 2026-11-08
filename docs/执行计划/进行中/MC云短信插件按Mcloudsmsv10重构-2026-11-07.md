---
status: 进行中
updated: 2026-11-07
owner: backend-platform
---

# MC云短信插件按 Mcloudsmsv10 与官方 API 文档重构

## 背景

MC云短信（stay33）以 `backend/plugins/sms/stay33` 接入 Caiwu 的 `sms` 能力域。重构依据有两份材料：

1. 上游对接包 **Mcloudsmsv10 v2.0**（`C:\Users\cerbo\Downloads\mcloudsmsv10`）；
2. **官方开发文档** <https://freescdn.com/api-docs?id=99&product=25>（发送短信 `/sms/send`，及同产品下 20 个接口）。

官方文档纠正了仅凭对接包得出的若干判断：变量语法确为 `${code}`（对接包的 `@var()` 是旧写法）、签名应走 `signature` 参数、鉴权应走请求头、官方**确有**模板与签名接口。

## 范围与验收

- [x] 端点改为官方 `https://api.freescdn.com/api/v1/sms/send`，GET query string。
- [x] 鉴权改走请求头：`X-Api-Key` + `X-Uid`，或 `X-Access-Key` + `X-Access-Secret`（AccessKey 优先）。凭据不进 URL。
- [x] 签名改走官方 `signature` 参数，正文不再由本地拼【签名】。
- [x] `channel_id` 官方为 `int`：留空时不发送该参数，由上游选默认通道。
- [x] 成功判定分两层：`code===0` 为接口成功；再看 `data.success/failed/blocked` —— 全部被拦截或部分失败按失败上报。
- [x] 回执号取 `data.message_id`，缺失保持 `null`。
- [x] 号码归一只剥大陆 `+86`/`0086`/`86` 前缀；国际号码原样保留。
- [x] 模板 action 接入官方接口：`pull`=`GET /sms/templates`、`push`/`update`=`POST /sms/templates`（带 `id` 即修改）、`status` 按 id 过滤、`delete` 仅本地。
- [x] 新增 `sms.fetch_signs` = `GET /sms/signatures`。
- [x] 保留 20 条默认模板种子与 `sms.verify_code_template`（本地能力，官方无对应接口）。
- [x] 配置：`uid`/`api_key`/`access_key`/`access_secret`/`sign_name`/`channel`，全部除 `sign_name` 外非必填。
- [x] **配置迁移**：保留隐藏的 `username` 兼容字段，避免 `PluginConfigRepository` 保存时丢弃旧值。
- [x] **升级不阻断**：`uid` 去掉 `required`，已启用插件可直接升级。
- [x] 测试：客户端 30 例 + 服务层 14 例。

## 实施步骤

1. 按官方文档重写 `Stay33SmsClient`：header 鉴权、`signature` 参数、`channel_id` 省略、两层成功判定、`message_id` 回执、号码归一侧重大陆号。
2. 客户端新增 `queryTemplates()` / `submitTemplate()` / `queryTemplateStatus()` / `querySignatures()`。
3. `Stay33SmsService` 模板 action 改为调用真实接口；新增 `sms.fetch_signs`。
4. `config.php`：新增 AccessKey 两项、`uid`/`api_key` 转非必填、保留隐藏 `username`、`channel` 改 number。
5. 重写客户端与服务层测试。
6. 同步插件 README 与执行计划。

## 风险与回滚

- **必须先回填 source_hash**：`plugin_integrity_enforce` 默认 `true`（`backend/config/integrations.php:10`），插件源码变更后摘要不一致会被运行时阻断。改完在管理端「扫描 → 升级」，或运行 `php artisan plugins:backfill-source-hash --execute`。
- **配置迁移**：`username` 保留为隐藏兼容字段，`PluginConfigRepository::partitionConfigPayload()` 只保留当前 schema 的键，删除该字段会让旧值在下次保存时被丢弃。`uid`/`api_key` 改为非必填，已启用插件可直接升级。
- **成功判定变更**：从「只看 code」改为「code + data 计数」。若上游某天不返回 `data.success`（精简响应），插件不做投递计数判定，保持兼容。
- **模板 action 语义变更**：`push` 由「提交即过审」改为真实提交后进入 `pending`；`pull` 由空列表改为真实列表。管理端「提交即过审」的假设对 stay33 不再成立，与其他插件行为一致。
- **`delete` 无官方接口**：仅移除本地映射，不删运营商侧，管理端删除后运营商侧模板仍存在。
- **端点变更**：旧端点不再作为默认；如需旧端点由管理员覆盖 `api_endpoint`。
- **`certification/stay33`** 实名插件共用域名但凭据与端点独立，本计划不触碰。
- **回滚条件**：管理端测试发送连续失败、或模板同步返回非预期状态时，回滚插件源码并重新回填 `source_hash`。

## 进度

- [x] 阅读上下游代码、插件契约、参考对接包与官方 API 文档
- [x] 建立执行计划并登记 catalog 与索引
- [x] 客户端按官方文档重写（header 鉴权 / signature / channel_id / 两层判定 / message_id）
- [x] 客户端新增模板与签名接口
- [x] 服务层模板 action 接真实接口 + `sms.fetch_signs`
- [x] 配置迁移与升级阻断修复
- [x] 客户端 30 例 + 服务层 14 例测试通过
- [x] README 与执行计划同步
- [ ] 运行完整验证链并登记结果

## 决策日志

| 日期 | 决策 | 原因 |
| --- | ---- | ---- |
| 2026-11-07 | 以 Mcloudsmsv10 对接包为蓝本重构 | 用户要求「根据这个基于 v10 开发的插件，对现有 caiwu 的插件进行重构」 |
| 2026-11-07 | 以官方 API 文档为最高事实源，对接包的 `@var()` 不作为最终语法 | 官方文档明确「变量用 `${code}` 形式」；对接包 `@var()` 是旧系统占位语法 |
| 2026-11-07 | 鉴权改走请求头，API Key 与 AccessKey 都支持 | 官方文档两条鉴权路径；凭据放 URL 会进上游与代理日志 |
| 2026-11-07 | 签名改走 `signature` 参数 | 官方参数表明确支持，免去本地猜测签名位置与去重 |
| 2026-11-07 | `channel_id` 留空时不发送 | 官方声明为 `int`，不传走默认通道；空串会被当通道 0 或触发参数错误 |
| 2026-11-07 | 成功判定增加 `data.success/failed/blocked` | `code=0` 仅表示接口调用成功，全部被拦截时仍为 `code=0` |
| 2026-11-07 | 号码只剥大陆 `+86`，国际号码原样 | 官方只要求分隔符格式，未声明可去国际前缀；本地改写会改变号码语义 |
| 2026-11-07 | 模板 action 接官方 `/sms/templates` | 官方确有模板接口，旧「无模板 API」判断基于旧版 sendApi.php，前提已不成立 |
| 2026-11-07 | 保留 slug / plugin_key / driver_key = `stay33` | 绑定表与历史 `message_logs` 按 key 关联 |
| 2026-11-07 | 保留隐藏 `username` 兼容字段 | 配置保存只保留当前 schema 的键，删除会让旧值在下次保存时丢失 |
| 2026-11-07 | `uid` 改为非必填 | 必填会让已启用插件升级时 `assertConfigReady` 抛异常，阻断上线 |

## 追加范围（2026-11-07 第二轮）

- [x] 去掉验证码限流配置：MCloud 控制台自带防刷（黑名单、同号码日/时上限、最小间隔、账号总量），本地重复限流会误伤。同时把 `MessageRateLimitService` 的默认值改为「关闭」，并新增按插件当前 schema 过滤限流键，杜绝删字段后存量旧值继续生效。
- [x] 后台配置精简为「账号 UID / API 密钥 / 短信签名 / 发信通道」四项（`username` 保留为隐藏兼容字段）。
- [x] 移除 AccessKey 鉴权（配置项与客户端分支），统一走 API Key 的 `X-Api-Key` + `X-Uid` 请求头。
- [x] 修复「从插件拉取模板」对非阿里云插件失效：`SmsVerifyCodeTemplateSyncService::pull()` 不再硬编码 `aliyun`，改为回落到当前绑定驱动；同时不再把阿里云专属的运营商模板编号写进业务模板。
- [x] 默认模板种子补齐 `name` 业务键（`client_login_success`、`host_active` 等 20 个），与参考包 `config/smsTemplate.php` 完全对齐。
- [x] **插件设置页新增「提交短信模板」**：按官方 107 提交（`name`/`scene`/`content`/`remark`/`sample_image`），提交后用 106 列表接口刷新确认。
  - 后端新增任务类型 `sms_template_submit` / `sms_template_list`，走既有 `POST /integration-plugins/{plugin}/tasks` 通道。
  - 前端在插件配置抽屉的「发送测试短信」下方新增表单，提交成功后自动刷新模板列表。

### 线上验证（154.219.97.172）

- 真实提交：`POST /sms/templates` 返回 `#121 / pending`。
- 用 106 确认：`GET /sms/templates` 返回 `#121 | 您的验证码为 ${code}，5 分钟内有效。| pending`，闭环成立。
- 线上配置解析：`uid`/`api_key`/`sign_name` 均已填，`channel` 为空（走默认通道）。

### 提交模板改为勾选预置模板（2026-11-07 第三轮）

模板不再要求管理员手填，改为直接使用插件内置的 20 条预置模板（即 Mcloudsmsv10 `config/smsTemplate.php` 的原文）：

- 后端新增任务类型 `sms_template_presets`，读取插件 `sms.templates.defaults`（与「导入默认模板」同源）。
- `sms_template_submit` 支持批量：`payload.templates[]`，逐条提交，单条失败不阻断，返回 `submitted`/`failed`/`results`。
- 管理端插件抽屉里改为勾选列表：显示模板标题与正文，已提交项按正文比对自动置灰。
