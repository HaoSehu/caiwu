# frontend-client

- 文档性质：现行方向 / 前端部署与 SEO 说明
- 对齐时间：2026-04-18
- 读者画像：负责官网、用户端构建、部署与 SEO 运维的前端 / 运维同学

## SEO 与预渲染

用户端官网的 SEO 默认值由管理端维护：

- 站务 → `SEO 设置`：维护站点级 `description`、`keywords`、`canonical_base`、`og_image`、`twitter_handle`、`robots_directive`
- 站务 → `基础设置`：维护站点名、浏览器标题、Logo、Favicon
- 公告 / 帮助编辑器：在管理端内容中心编辑单条 `meta_title`、`meta_description`
- 商品编辑器：在管理端商品详情页编辑 `meta_title`、`meta_description`、`meta_keywords`

官网静态资源位置：

- `public/robots.txt`：搜索引擎抓取规则
- `scripts/generate-sitemap.mjs`：构建 `sitemap.xml`
- `scripts/prerender.mjs`：构建后预渲染官网静态路由

## 构建命令

```bash
cd frontend-client

# 常规构建
npm run build

# 构建并生成 sitemap.xml
SITE_URL=https://www.example.com SITEMAP_API_BASE=https://api.example.com npm run build:with-sitemap

# 仅生成 sitemap.xml
SITE_URL=https://www.example.com SITEMAP_API_BASE=https://api.example.com npm run generate:sitemap

# 构建并预渲染官网静态路由
PRERENDER_BACKEND_TARGET=http://127.0.0.1:8000 npm run build:prerender
```

说明：

- `build:prerender` 不会替换默认 `npm run build`，仅在需要生成 SEO 快照时执行。
- 预渲染会抓取这些静态路由：`/`、`/products`、`/about`、`/terms`、`/privacy`、`/notices`、`/help`。
- 预渲染时本地静态服务会把 `/api`、`/uploads`、`/storage`、`/sanctum` 代理到 `PRERENDER_BACKEND_TARGET`，默认是 `http://127.0.0.1:8000`。

## 部署提示

Nginx 需要优先命中预渲染产物，再回退到 SPA：

```nginx
location / {
    try_files $uri $uri/ $uri.html /index.html;
}
```

补充说明：

- 预渲染后的静态页位于 `dist/<route>/index.html`，首页快照覆盖 `dist/index.html`。
- `dist/index.html` 仍然保留 SPA 兜底能力，适合配合 `try_files` 回退。
- 如果站点已确定主域名，建议在部署时同时给 `SITE_URL` 传入正式域名，让 `sitemap.xml` 生成绝对链接。

## 验证要求

涉及 SEO 元数据、`robots.txt`、`sitemap.xml` 或预渲染时，除了常规 `npm run build` 外，还应至少跑通一次：

```bash
cd frontend-client
npm run build:with-sitemap
```
