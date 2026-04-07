# Task 08: stabilize-admin-vnc-reconnect-flow

> Phase: 3 — 性能与结构收敛
> Status: Pending

---

## Objective

让管理员打开的 VNC 页面在 token 一次性消费后，仍能基于 admin token 与 admin user id 自愈重连。

## Traceability

- Finding(s): `Bug Fixer review-1 / H-1`
- Severity: `high`
- Dimension: `Bug Fixer Milestone Review`

## Related Findings — 关联发现

| Finding ID | Severity | File | Description |
|-----------|----------|------|-------------|
| BF-H-1 | HIGH | `frontend-client/public/vnc/vnc.html` | 管理员打开的 VNC 页面在 token 一次性消费后无法自愈重连 |

## Files

**Create:**

- `backend/tests/Feature/AdminVncReconnectFlowTest.php`

**Modify:**

- `backend/app/Http/Controllers/Admin/UserController.php`
- `frontend-admin/src/views/admin/UserDetail/index.vue`
- `frontend-client/public/vnc/vnc.html`

**Delete:**

- 无

**Test:**

- `backend/tests/Feature/AdminVncReconnectFlowTest.php`

## Dependencies

| Library | GitHub Repo | Usage in This Task |
|---------|-------------|-------------------|
| Laravel Framework | laravel/framework | 后台 VNC 续租接口返回与鉴权上下文 |
| Laravel Sanctum | laravel/sanctum | 基于 admin token 完成管理员新窗口续连 |
| PHPUnit | sebastianbergmann/phpunit | 锁定管理员 VNC 续连参数与接口契约 |

## Steps

### Step 1: 为管理员 VNC 打开流程附带续连上下文

调整管理员详情页打开 VNC 的 URL，让新窗口携带 `service_id`、`admin_user_id` 等续连所需参数。

```js
url.searchParams.set('admin_user_id', userId.value)
```

### Step 2: 让 vnc.html 识别 admin token

扩展 `vnc.html` 的 token 读取逻辑，支持读取 `admin_token`，并区分客户端续连与管理员续连。

```js
readStorageValue(window.localStorage, 'admin_token')
```

### Step 3: 增加管理员续租请求分支

当 URL 中存在 `admin_user_id` 时，优先调用管理员 VNC 接口重新获取新 token，而不是错误地走客户端续租接口。

```js
/api/admin/users/${adminUserId}/services/${serviceId}/vnc
```

### Step 4: 保持客户端续连逻辑不受影响

确保普通客户端 VNC 继续使用 `client_token` 和客户端接口，不引入跨角色串用。

```js
if (adminUserId) { ... } else { ... }
```

### Step 5: 增加续连回归测试

新增测试验证管理员 VNC 续连所需参数与后端接口契约完整可用。

```bash
php artisan test --filter=AdminVncReconnectFlowTest
```

## Verification

### Baseline Tests — 基线测试

- [ ] Run test suite before task: `php artisan test`
- [ ] Record baseline: `44` passing, `0` failing
- [ ] Run test suite after task
- [ ] Verify no new failures (Preservation Gate)

- [ ] 管理员新窗口在 token 一次性消费后仍可基于 admin token 重新拉取 VNC URL
- [ ] 客户端 VNC 续连逻辑保持不变

**Test command:**
```bash
php artisan test --filter=AdminVncReconnectFlowTest
```

**Expected output:**
```text
PASS  Tests\Feature\AdminVncReconnectFlowTest
```

## Rollback Plan

- 若管理员续连逻辑影响客户端 VNC，先回退 `vnc.html` 的 admin 分支
- 若管理员打开 URL 参数污染现有前端，先回退前端参数追加，不回退一次性 token 逻辑
- 保留测试，定位是参数透传、token 读取还是续连接口分支出错

## Commit

```text
fix: stabilize admin vnc reconnect flow after single use token (Phase 3, Task 08)
```
