# MC云短信 插件

该插件把 MC云短信 接入 Caiwu 的 `sms` 能力域，slug 为 `stay33`、plugin_key 为 `stay33`。插件对接 **MCloud v10 开放 API**（`https://api.freescdn.com/api/v1/sms/send`），契约依据官方开发文档 [freescdn.com/api-docs?id=99](https://freescdn.com/api-docs?id=99&product=25)。

## 目录结构

```text
backend/plugins/sms/stay33/
├── Stay33Plugin.php
├── lib/Stay33SmsService.php
├── lib/Stay33SmsClient.php
├── tests/Stay33SmsClientTest.php
├── tests/Stay33SmsServiceTest.php
└── config.php
```

## 配置项

标记为「加密」的字段保存后不会明文回显，留空表示保留旧值。

| 字段 | 类型 | 必填 | 加密 | 说明 |
| --- | --- | --- | --- | --- |
| `uid` | text | 否 | 否 | MCloud 账号 UID，控制台右上角显示的数字 ID 或用户 UUID。 |
| `api_key` | password | 否 | 是 | 控制台「访问控制 → API 密钥」创建。 |
| `sign_name` | text | 是 | 否 | 已通过审核的短信签名，不含外层【】。通过官方 `signature` 参数下发。 |
| `channel` | number | 否 | 否 | 发信通道 ID（`channel_id`）。留空表示由上游使用账号第一个可用通道。 |

（`username` 是旧版用户名的隐藏兼容字段，不出现在表单里。）

鉴权固定为 API Key：请求头 `X-Api-Key` + `X-Uid`，凭据不进 URL query。

## 协议要点

依据官方文档 `/sms/send`：

- 请求：`GET https://api.freescdn.com/api/v1/sms/send?phones=…&content=…&signature=…&channel_id=…`
- 鉴权：请求头 `X-Api-Key` + `X-Uid`，或 `X-Access-Key` + `X-Access-Secret`。
- 正文：**签名走 `signature` 参数**（可不带【】），插件不再往正文里拼【签名】。变量用 `${code}` 形式。
- 号码：多个用换行或英文逗号分隔。插件只剥离平台补上的大陆 `+86` 前缀，**国际号码原样保留**。
- `channel_id`：官方声明为 `int`，留空时不发送该参数，由上游选默认通道。
- 成功判定分两层：
  1. `code === 0` 表示接口调用成功（其余为业务失败，错误码见官方文档 1001/1002/1003/1009/3001 等）；
  2. `data.success` / `failed` / `blocked` 才是个体投递结果 —— 全部被拦截（`success:0`）或部分失败都会按失败上报，不会记成发送成功。
- 回执号取 `data.message_id`。

## 模板与签名

官方提供真实接口，插件已对接：

| action | 官方接口 | 说明 |
| --- | --- | --- |
| `sms.templates.pull` | `GET /sms/templates` | 拉取模板列表（含审核状态） |
| `sms.templates.push` | `POST /sms/templates` | 提交模板，进入审核；含「验证码」的内容自动归 `verify` 场景 |
| `sms.templates.update` | `POST /sms/templates`（带 `id`） | 修改并重新提审 |
| `sms.templates.status` | `GET /sms/templates` 按 id 过滤 | 回查审核状态；运营商侧不存在时返回 `deleted` |
| `sms.templates.delete` | 无官方接口 | 仅移除本地映射，不删运营商侧 |
| `sms.templates.defaults` | 无（本地种子） | 20 条默认模板，`@var()` 在导入时翻译为 `${var}` |
| `sms.fetch_signs` | `GET /sms/signatures` | 查询账号签名及审核状态 |
| `sms.verify_code_template` | 无（本地内置） | 按场景下发验证码文案 |

管理端「导入默认模板」落库为草稿，需提审后才进入发送解析链。

## 能力边界

插件只负责第三方协议适配与协议级错误翻译；订单、账单、服务状态、审计日志、限流与幂等仍由平台服务层负责。
