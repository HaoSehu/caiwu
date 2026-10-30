# frontend-user-v3-www

- 文档性质：现行方案 / 官网与购买入口部署说明
- 对齐时间：2026-06-28
- 读者对象：负责官网与购买入口构建、部署的前端 / 运维同学

## 站点基础配置

用户端官网的基础展示信息由管理端维护：
- 站务 -> `基础信息`：维护站点名、浏览器标题、Logo、Favicon、客服联系方式与协议链接
- 站务 -> `首页 Banner`：维护官网首页首屏内容

## 构建命令

```bash
cd frontend-user-v3-www
npm run build
```

`npm run build` 依次执行四步，缺一不可：

1. `vite build --mode production`：产出 `dist/`，并由 vite 插件的 `closeBundle` 对 assets 与根 `index.html` 做预压缩；
2. `scripts/generate-sitemap.mjs`：生成 `sitemap.xml` / `robots.txt`；
3. `scripts/prerender-www.mjs`：预渲染 7 条静态路由、5 条 SEO 落地页与 `404.html`，**并在写完所有 HTML 之后重新跑一遍预压缩**（vite 的 closeBundle 早于本步骤，那时这些文件还不存在或内容仍是旧的，只压一次会让线上拿到落后一版的 `.gz` / `.br`）；
4. `scripts/check-budget.mjs`：调用 `backend/scripts/frontend_budget.mjs` 校验首屏与单分片体积，与根目录 `npm run verify:frontends` 使用同一份预算表和同一套算法。

## 部署提示

Nginx SPA 回退与 HTML 层预压缩（`ngx_http_brotli_static_module` / `gzip_static` 只对**同目录同名**的 `.br` / `.gz` 生效，因此 HTML 也要开）：

```nginx
location / {
    try_files $uri $uri/ $uri.html /index.html;
    gzip_static on;
    brotli_static on;
}
```

静态资源预压缩：

```nginx
location /assets/ {
    gzip_static on;
    brotli_static on;
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

预渲染产出的子目录页（`/products/index.html`、`/cloud-server/index.html` 等）与根 `index.html`、`404.html` 都会带对应的 `.gz` / `.br`，前提是上面 HTML 层的 `gzip_static` / `brotli_static` 没有漏配。

## 验证要求

```bash
cd frontend-user-v3-www
npm run build
```

构建末尾若体积超预算会直接失败并打印超限明细，不再需要额外记得去跑根目录门禁。
