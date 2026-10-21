# 数据库目录说明

## 目录结构

```
database/
├── schema/
│   ├── mysql-schema.sql       # 生产库完整结构快照（新环境初始化用）
│   └── baseline-meta.json     # 基线导出元数据（表数/迁移数/提交号）
├── migrations/
│   └── *.php                  # 增量迁移（仅新增变更）
├── migrations-orphans.json    # 孤儿迁移白名单（账本有记录但文件已删除）
├── migrations-lint-exempt.json # 迁移 lint 豁免清单
├── seeders/                   # 数据填充
├── factories/                 # 测试工厂
└── _archive/                  # 历史遗留（不参与 migrate）
    ├── migrations/            # 已固化进 schema 的历史迁移
    ├── migrations_devin/      # 临时工程迁移
    ├── schema.old.sql         # 旧版 schema 快照
    └── import_legacy_data.sql
```

## 初始化方式

### 新环境
```bash
python backend/scripts/install_db.py
# 自动导入 schema/mysql-schema.sql（含 migrations 记录）
```

### 增量更新（唯一受控入口）
```bash
php artisan db:upgrade-status   # 只读：查看待执行迁移与基线一致性
php artisan db:upgrade --force  # 执行：备份 + 校验 + 迁移 + 记账
```

直接执行 `php artisan migrate` 仅用于隔离开发环境调试，不作为产品升级入口。

### 更新 schema baseline
```bash
# 在生产库或同步后的本地库上导出
php backend/scripts/export_schema_baseline.php
```

## 注意事项

1. **禁止**把历史迁移从 `_archive/migrations/` 移回 `migrations/` 再执行
2. 新功能上线时，只新增 `migrations/*.php` 增量迁移
3. 重大结构变更后，重新导出 schema baseline 并归档旧迁移
4. `_archive/` 下文件仅供历史参考，不进入日常部署流程
