# 前端 Nginx 伪静态配置

三个前端站点均为 Vue 3 + vue-router history 模式，部署在宝塔面板独立站点。
在宝塔面板 → 对应站点 → 设置 → 伪静态中粘贴对应配置。

## 一、管理后台（frontend-admin-v3）

```nginx
location ~* /index\.html$ {
    add_header Cache-Control "no-cache, no-store, must-revalidate" always;
}

location / {
    try_files $uri $uri/ /index.html;
}

location ~ ^/(api|sanctum|uploads|media)/ {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host  $host;
}

location ^~ /ws/vnc {
    proxy_http_version 1.1;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade           $http_upgrade;
    proxy_set_header Connection        "upgrade";
    proxy_buffering     off;
    proxy_read_timeout  3600s;
    proxy_send_timeout  3600s;
    proxy_pass http://127.0.0.1:8100;
}
```

## 二、用户官网（frontend-user-v3-www）

```nginx
location ~* /index\.html$ {
    add_header Cache-Control "no-cache, no-store, must-revalidate" always;
}

location / {
    try_files $uri $uri/ /index.html;
}

location ~ ^/(api|sanctum|uploads|media)/ {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host  $host;
}

location ^~ /ws/vnc {
    proxy_http_version 1.1;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade           $http_upgrade;
    proxy_set_header Connection        "upgrade";
    proxy_buffering     off;
    proxy_read_timeout  3600s;
    proxy_send_timeout  3600s;
    proxy_pass http://127.0.0.1:8100;
}
```

## 三、用户控制台（frontend-user-v4-console）

```nginx
location ~* /index\.html$ {
    add_header Cache-Control "no-cache, no-store, must-revalidate" always;
}

location / {
    try_files $uri $uri/ /index.html;
}

location ~ ^/(api|sanctum|uploads|media|zjmf)/ {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host  $host;
}

location ^~ /ws/vnc {
    proxy_http_version 1.1;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade           $http_upgrade;
    proxy_set_header Connection        "upgrade";
    proxy_buffering     off;
    proxy_read_timeout  3600s;
    proxy_send_timeout  3600s;
    proxy_pass http://127.0.0.1:8100;
}
```

## 配置说明

| 规则 | 作用 |
|------|------|
| `index.html` 禁缓存 | 确保每次部署后用户获取最新入口文件 |
| `try_files ... /index.html` | SPA 路由回落，支持刷新和直接访问深层路由 |
| `/api、/sanctum、/uploads、/media` 反代 | 转发到 Laravel 后端（PHP-FPM 8000 端口） |
| `/zjmf`（仅控制台） | ZJMF 对接中间层路径，转发到后端 |
| `/ws/vnc` WebSocket 反代 | VNC 控制台长连接，转发到 8100 端口 |

## 四、后端 API 站点（backend/public）

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/tmp/php-cgi-82.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|eot)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
}

location ~ /\.(?!well-known) {
    deny all;
}
```

> `fastcgi_pass` 的 sock 路径以宝塔实际 PHP 版本为准，常见路径：
> - PHP 8.2：`/tmp/php-cgi-82.sock`
> - PHP 8.3：`/tmp/php-cgi-83.sock`
>
> 也可能是 TCP 形式：`127.0.0.1:9000`，以宝塔 PHP 设置页显示的为准。

## 注意事项

- 三个前端站点各自独立域名，配置互不干扰
- 后端站点根目录指向 `backend/public`，运行目录选 `/`
- 如果宝塔的伪静态输入框不支持 `proxy_*` 指令，需要在站点配置文件中直接编辑 Nginx 配置
