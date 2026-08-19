---
status: active
updated: 2026-08-19
owner: backend-platform
---

# Web 安装向导

## 状态

已完成首版后端服务、安装命令、安装路由、异常边界和离线静态页；待补充专门 Feature/Unit 测试与部署实机验证。

## 进度

- [x] 服务层、安装命令与锁机制
- [x] 路由、中间件、统一异常响应
- [x] 离线静态安装页面
- [x] 运维说明
- [ ] 专门自动化测试与 MySQL 实机验收

## 决策日志

- 使用 `storage/app/installer/installed.lock` 安装锁，不自删目录；重装需人工删除锁。
- 表单包含 API、官网、控制台、管理后台四个互不相同且同协议的根地址。
- 未安装时根路径 302 到 `/install/`，页面由 `public/install` 静态服务。

## 风险与后续

安装进程超时由 `INSTALLER_TIMEOUT` 控制，生产 FPM/Nginx 应配置不少于 600 秒；真实 MySQL 8 环境需执行 `app:install` 回归。
