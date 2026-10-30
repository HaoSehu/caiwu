---
status: current
updated: 2026-08-19
owner: ops
---

# 四端 Nginx 伪静态配置

四个站点独立部署、浏览器直连 API。三个前端站点不配置 API、上传资源或 WebSocket 反代。宝塔已经管理站点根目录、SSL 和 PHP-FPM 时，只编辑“设置 → 伪静态”，不要手工替换站点的完整 Nginx `server {}` 配置。

| 站点       | 域名示例              | 运行目录                        |
| ---------- | --------------------- | ------------------------------- |
| 官网       | `www.example.com`     | `frontend-user-v3-www/dist`     |
| 用户控制台 | `console.example.com` | `frontend-user-v4-console/dist` |
| 管理端     | `admin.example.com`   | `frontend-admin-v3/dist`        |
| API        | `api.example.com`     | `backend/public`                |

## 宝塔面板设置

先创建四个站点并按上表设置根目录。API 站点选择 PHP 8.2 或 8.3；三个前端站点保持静态站点。HTTPS 证书、80 到 443 跳转和域名由宝塔“SSL”页面配置，不能在伪静态框中填写 `listen`、`server_name`、`root`、`ssl_certificate` 或 PHP-FPM 配置。

纯 HTTP 环境关闭宝塔强制 HTTPS，并将 `SESSION_SECURE_COOKIE=false`；HTTPS 环境则让四个站点都使用 HTTPS。无论哪种环境，四个公开地址必须统一协议。

## 官网：www

```nginx
location / {
    try_files $uri $uri/ /index.html;
}

# 不存在的静态资源（带扩展名）直接返回 404，避免被回退到首页
location ~* \.(?:js|css|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|map)$ {
    try_files $uri =404;
}

# 构建产物 dist/404.html 供错误页引用；未知页面路径仍由 SPA 的 catch-all 路由渲染 404
error_page 404 /404.html;
location = /404.html {
    internal;
}
```

未知的页面路径（无扩展名）继续回退 `index.html` 交给前端路由处理；不存在的 JS/CSS/图片等静态资源返回真实 404 状态码，避免 Search Console 累积 soft-404。`dist/404.html` 由 `npm run build` 的预渲染脚本自动生成。

### 官网安全响应头（含 CSP）

在宝塔官网站点的“伪静态”或“配置文件”中添加以下安全头，阻断点击劫持与大部分脚本注入面：

```nginx
# 统一安全头（首页预热内联脚本为构建期注入，故 script-src 含 unsafe-inline）
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:; media-src 'self' https:; frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self';" always;
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

说明：

- `frame-ancestors 'none'` + `X-Frame-Options: DENY` 禁止第三方 iframe 嵌入官网，阻断点击劫持。
- `object-src 'none'`、`base-uri 'self'`、`form-action 'self'` 收窄插件/表单跳转面。
- 后续若把 `index.html` 的首页预热脚本抽为独立文件，可去掉 `script-src` 中的 `'unsafe-inline'` 进一步收紧。

#### CSP 收紧待办（阶段二，来源：官网 index.html 原注释）

现状：`script-src 'self' 'unsafe-inline'`，`frontend-user-v3-www/index.html` 内实际有 **2 段内联脚本**（不是 3 段）：

1. 首页 LCP 预热脚本（只读 `%VITE_API_BASE_URL%`，非根路径直接 return）；
2. `<script nomodule>` 老内核升级提示。

首屏 splash 是内联 `<style>` 不是 `<script>`，只受 `style-src` 约束。保留 `'unsafe-inline'` 等价于放弃对注入脚本的拦截，是当前 CSP 最实质的弱点（XSS 一旦落地即可直接执行）。

风险：

- 静态托管（nginx 直出 dist）无法按请求下发 nonce；改成 hash 需与构建产物同源，且每次改动内联脚本都会静默失效（浏览器只报违规、不阻断，容易上线后无人察觉）。
- 若改用响应头 CSP + nonce，需要 nginx 侧注入变量或 SSR，涉及部署链路改造，回滚成本高。

建议方案（按风险从低到高）：

1. 先把 2 段内联脚本外移到 `public/` 或由 Vite 产物引出的独立 `.js`（预热脚本可改为 `<link rel="modulepreload">` + 首帧后执行），内联清空后再删 `'unsafe-inline'`；
2. 外移后对剩余必须内联的部分用 sha256 hash 白名单（脚本内容固定、含 `%VITE_API_BASE_URL%` 替换，hash 必须在构建后生成，可挂到 vite `closeBundle` 阶段回写 index.html）；
3. 最终形态改为 nginx 响应头 CSP + 每请求 nonce，meta 版仅作兜底。

本阶段只登记待办，不修改 `index.html` 里 CSP 的取值：改动会立刻破坏首屏预热与老内核降级，必须在同一次发布内完成 1/2 才可动。
- 控制台/管理端站点如与官网同域，可套用同一组头；不同子域按各自资源需求调整 `connect-src`。

## 用户控制台：console

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

`/vnc/vnc.html` 是控制台构建产物的一部分，会被 `$uri` 直接命中。

## 管理端：admin

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

## API：api

在 API 站点的“伪静态”完整填入：

```nginx
location ^~ /ws/vnc {
    proxy_http_version 1.1;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade           $http_upgrade;
    proxy_set_header Connection        "upgrade";
    proxy_buffering off;
    proxy_read_timeout 3600s;
    proxy_send_timeout 3600s;
    proxy_pass http://127.0.0.1:8100;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

项目未启用 VNC 远程控制时，可以删除 `/ws/vnc` 的整个 `location` 块，保留 Laravel 回退规则。宝塔生成的 PHP-FPM 配置保持不变。`/api`、`/uploads`、`/media` 都由 API 站点的 Laravel/PHP-FPM 处理或直接提供静态文件。

## HTTP 与 HTTPS

- HTTP 环境在宝塔关闭强制 HTTPS，并将 `SESSION_SECURE_COOKIE=false`。
- HTTPS 环境在宝塔 SSL 页面开启证书和 80 到 443 跳转；前端 API 基址填写 `https://api.example.com/api`，VNC 自动使用 `wss`。
- 同一环境四个公开域名统一使用 HTTP 或 HTTPS，避免浏览器混合内容。

## 缓存建议

- `index.html` 和其他预渲染 HTML 使用 `Cache-Control: no-cache`。
- 带 hash 的 JS、CSS、字体和图片可长期缓存；压缩由宝塔 Nginx 的全局能力处理，不需要在伪静态中添加模块指令。
- 不要在三个前端站点重新添加 `/api`、`/uploads`、`/media`、`/ws/vnc` 的 `proxy_pass`。
