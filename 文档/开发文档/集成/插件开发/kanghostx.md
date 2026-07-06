# 康乐虚拟主机插件

插件目录：`backend/plugins/servers/kanghostx`

该插件按根目录 `kanghostx/` 原模块的 Kangle WHM API 接入 Caiwu 上游插件系统，provider key 为 `kanghostx`。

## 接口映射

- 签名：`md5(a + accesshash + r)`
- 入口：`{api_url}/api/index.php`
- 固定参数：`c=whm`、`json=1`
- 开通：`a=add_vh`
- 查询：`a=getVh`
- 暂停/恢复：`a=update_vh`
- 删除：`a=del_vh`
- 改密：`a=change_password`

## 使用约束

- 后台供应商的 `api_url` 填康乐面板根地址。
- `api_key` 填原模块里的 `accesshash`。
- 空间容量、数据库容量、月流量、域名数、端口、模块等套餐参数在商品目录页绑定康乐接口提供商后，通过产品配置模板维护。
- 系统用 `cw{service_id}` 作为康乐虚拟主机账号，用整数 `service_id` 作为 `upstream_host_id`，以适配当前控制台的整数实例 ID 约束。
- 该插件提供一个本地康乐虚拟主机商品模板和产品配置模板，不从康乐面板同步真实商品目录；不提供 VNC、重装、NAT、安全组能力。

## 验证

```bash
cd backend
php artisan test tests/Feature/KangHostxPluginTest.php
```
