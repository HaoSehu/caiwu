---
status: current
updated: 2026-08-19
owner: backend-platform
---

# Web 安装向导

访问 `/install/` 按页面步骤完成环境检测、MySQL 配置、站点地址和管理员创建。安装成功后锁文件写入 `backend/storage/app/installer/installed.lock`，接口将返回 404；重装前需在停机维护窗口删除该锁并备份 `.env`。

安装向导与 `install_db.py` 共用数据库和管理员约定，CLI 仍可作为替代路径。管理员密码只通过安装进程环境变量传递，不出现在命令行、日志或响应中。生产环境密码至少 12 位，FPM/Nginx 超时应至少 600 秒。
