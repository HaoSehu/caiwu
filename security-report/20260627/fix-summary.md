# 2026-06-27 安全修复记录

## FIX-001 安全组写操作 `groupId` 越权防护

- 风险类型：鉴权缺失 / 参数信任 / 潜在越权访问
- 影响模块：`/api/client/services/{id}/security-groups/*`
- 问题描述：
  - 服务端在安全组应用、删除、创建规则、删除规则等写操作中，直接信任客户端提交的 `groupId`。
  - 原实现未在写操作前校验该安全组是否属于当前服务可见范围，存在通过篡改 `groupId` 操作隐藏安全组的风险。
- 修复方式：
  - 在 `backend/app/Services/ClientServiceConsole/ServiceSecurityGroupService.php` 增加 `assertSecurityGroupVisibleToCurrentHost()`。
  - 对以下写操作统一追加服务端可见性校验：
    - `applySecurityGroupForUser()`
    - `deleteSecurityGroupForUser()`
    - `createSecurityRuleForUser()`
    - `deleteSecurityRuleForUser()`
  - `callSecurityGroupAction()` 支持复用已校验的上下文，避免重复解析后产生不一致。
- 验证结果：
  - `php artisan test tests/Feature/ServiceSecurityGroupOwnershipFilterTest.php`
  - `php artisan test tests/Feature/ServiceSecurityGroupNameAvailabilityTest.php tests/Feature/ServiceSecurityGroupCacheIsolationTest.php`
  - `php -l app\Services\ClientServiceConsole\ServiceSecurityGroupService.php`
  - 以上均通过。

## 变更文件

- `backend/app/Services/ClientServiceConsole/ServiceSecurityGroupService.php`
- `backend/tests/Feature/ServiceSecurityGroupOwnershipFilterTest.php`

## 备注

- 本次为服务端收口修复，目的是阻断通过篡改 `groupId` 触发的隐藏资源操作。
- 尚未在真实浏览器联调环境复测该链路；如需完整回归，应使用测试账号在服务控制台页面对安全组增删改查路径做一次手工验证。
