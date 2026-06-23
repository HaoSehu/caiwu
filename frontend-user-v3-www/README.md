# frontend-user-v3-www

- 文档性质：现行方向 / 官网与购买入口部署说明
- 对齐时间：2026-06-08
- 读者画像：负责官网与购买入口构建、部署的前端 / 运维同学

## 站点基础配置

用户端官网的基础展示信息由管理端维护：

- 站务 → `基础信息`：维护站点名、浏览器标题、Logo、Favicon、客服联系方式与协议链接
- 站务 → `首页 Banner`：维护官网首页首屏内容

## 构建命令

```bash
cd frontend-user-v3-www

# 常规构建
npm run build
```

## 部署提示

Nginx 回退到 SPA：

```nginx
location / {
    try_files $uri $uri/ $uri.html /index.html;
}
```

## 验证要求

```bash
cd frontend-user-v3-www
npm run build
```
