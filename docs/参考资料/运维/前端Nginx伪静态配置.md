# 四端 Nginx 伪静态配置

四个站点独立部署、浏览器直连 API。三个前端站点不配置 API、上传资源或 WebSocket 反代。

| 站点 | 域名示例 | 运行目录 |
| --- | --- | --- |
| 官网 | `www.example.com` | `frontend-user-v3-www/dist` |
| 用户控制台 | `console.example.com` | `frontend-user-v4-console/dist` |
| 管理端 | `admin.example.com` | `frontend-admin-v3/dist` |
| API | `api.example.com` | `backend/public` |

## 完整站点配置的协议选择

以下四段是宝塔“配置文件”中每个站点共用的 `server` 外壳；后续各端的 `location` 规则仍按本页对应小节填写。不要把 HTTP 与 HTTPS 的 `listen` 混在同一个未配置证书的 `server` 块中。

### 纯 HTTP

HTTP 部署时四个站点都使用此形式，不配置证书、HSTS、80 到 443 跳转，也不将前端的 API 基址写成 HTTPS：

```nginx
server {
    listen 80;
    server_name www.example.com; # 按站点替换为 www / console / admin / api 域名
    root /www/wwwroot/project/frontend-user-v3-www/dist; # 按站点替换目录
    index index.html;

    # 填入本页该站点的 location 规则。
}
```

API 站点将 `root` 改为 `backend/public`，并将 `index` 改为 `index.php index.html`。

### HTTPS

HTTPS 部署时四个站点都使用 HTTPS 根地址；为每个域名配置证书，并额外用 80 端口只做跳转：

```nginx
server {
    listen 80;
    server_name www.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name www.example.com; # 按站点替换
    root /www/wwwroot/project/frontend-user-v3-www/dist; # 按站点替换目录
    index index.html;
    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    # 填入本页该站点的 location 规则。
}
```

API 站点同样将 `root` 和 `index` 换成 PHP-FPM 站点的值。反向代理会传递真实 `$scheme`，因此 Laravel 可以正确识别 HTTP 与 HTTPS。

## 官网：www

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

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

宝塔伪静态：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

在 API 站点完整 `server` 配置中额外加入 VNC Relay 转发：

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
```

宝塔生成的 PHP-FPM `location ~ \.php$` 配置保持不变。`/api`、`/uploads`、`/media` 都由 API 站点的 Laravel/PHP-FPM 处理或直接提供静态文件。

## HTTP 与 HTTPS

- HTTP 环境只监听 `80`，不配置 HSTS、SSL 跳转或 `Secure` Session Cookie。
- HTTPS 环境监听 `443 ssl`，可将 `80` 重定向到 `443`；前端 API 基址填写 `https://api.example.com/api`，VNC 自动使用 `wss`。
- 同一环境四个公开域名统一使用 HTTP 或 HTTPS，避免浏览器混合内容。

## 缓存建议

- `index.html` 和其他预渲染 HTML 使用 `Cache-Control: no-cache`。
- 带 hash 的 JS、CSS、字体和图片可长期缓存并启用 gzip/brotli 静态文件。
- 不要在三个前端站点重新添加 `/api`、`/uploads`、`/media`、`/ws/vnc` 的 `proxy_pass`。
