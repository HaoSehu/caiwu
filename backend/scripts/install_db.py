#!/usr/bin/env python3
"""初始化当前项目数据库。

说明：
- 读取 backend/.env 中的数据库配置
- 自动创建数据库（如不存在）
- 检查并生成 APP_KEY（如缺失）
- 空库时优先导入由真实库导出的 schema baseline
- 清理 Laravel 缓存
- 执行数据库迁移
- 自动创建默认管理员 cerbo / Temp@123456

schema baseline 更新方式：
    php backend/scripts/export_schema_baseline.php

旧库 SQL 数据迁移继续使用：
    python backend/scripts/migrate_legacy_dump.py
"""

from __future__ import annotations

import argparse
import os
import shlex
import shutil
import subprocess
import sys
from pathlib import Path


SCRIPT_PATH = Path(__file__).resolve()
SCRIPT_DIR = SCRIPT_PATH.parent
BACKEND_DIR = SCRIPT_DIR.parent
ENV_FILE = BACKEND_DIR / ".env"
ARTISAN_FILE = BACKEND_DIR / "artisan"
SCHEMA_FILE = BACKEND_DIR / "database" / "schema" / "mysql-schema.sql"
SCHEMA_EXPORT_SCRIPT = SCRIPT_DIR / "export_schema_baseline.php"

ADMIN_BOOTSTRAP_CODE = r"""use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$role = Role::query()->firstOrCreate(
    ['name' => 'super_admin'],
    [
        'label' => '超级管理员',
        'permissions' => ['*'],
    ]
);

$role->forceFill([
    'label' => trim((string) ($role->label ?? '')) !== '' ? $role->label : '超级管理员',
    'permissions' => ['*'],
])->save();

$admin = AdminUser::query()->firstOrNew(['username' => 'cerbo']);
$isNewAdmin = ! $admin->exists;

$admin->forceFill([
    'role_id' => (int) $role->id,
    'nickname' => trim((string) ($admin->nickname ?? '')) !== '' ? $admin->nickname : '默认管理员',
    'email' => trim((string) ($admin->email ?? '')) !== '' ? $admin->email : 'cerbo@example.com',
    'status' => $isNewAdmin ? 1 : (int) ($admin->status ?? 1),
]);

if ($isNewAdmin) {
    $admin->password = 'Temp@123456';
}

$admin->save();

if (Schema::hasTable('admin_user_roles')) {
    DB::table('admin_user_roles')->updateOrInsert(
        [
            'admin_user_id' => (int) $admin->id,
            'role_id' => (int) $role->id,
        ],
        []
    );
}"""


class InstallDbError(RuntimeError):
    """安装过程中的可预期错误。"""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        prog="python backend/scripts/install_db.py",
        description="初始化当前项目数据库。",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="只打印将要执行的步骤，不真正写入数据库。",
    )
    parser.add_argument(
        "--reset",
        action="store_true",
        help="先删除并重建目标数据库，再执行初始化。会清空目标库所有数据。",
    )
    return parser.parse_args()


def log(message: str) -> None:
    print(f"[install-db] {message}", flush=True)


def fail(message: str) -> None:
    raise InstallDbError(message)


def read_env_value(key: str) -> str:
    if not ENV_FILE.is_file():
        fail(f"未找到 {ENV_FILE}，请先准备后端 .env 文件")

    value = ""
    for raw_line in ENV_FILE.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw_line.lstrip()
        if not line.startswith(f"{key}=") and not line.startswith(f"{key} "):
            continue
        if "=" not in line:
            continue

        _, value = line.split("=", 1)
        value = value.rstrip("\r").lstrip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
            value = value[1:-1]

    return value


def mask_secret(value: str) -> str:
    return "******" if value else "(空)"


def ensure_file(path: Path, message: str) -> None:
    if not path.is_file():
        fail(message)


def ensure_command(command: str, message: str) -> None:
    if shutil.which(command) is None:
        fail(message)


def mysql_env(db_password: str) -> dict[str, str]:
    env = os.environ.copy()
    if db_password:
        env["MYSQL_PWD"] = db_password
    return env


def mysql_args(
    db_username: str,
    db_host: str,
    db_port: str,
    db_socket: str,
) -> list[str]:
    args = [
        "mysql",
        "--default-character-set=utf8mb4",
        "--connect-timeout=5",
        "-u",
        db_username,
    ]
    if db_socket:
        args.append(f"--socket={db_socket}")
    else:
        args.extend(["-h", db_host, "-P", db_port, "--protocol=TCP"])
    return args


def render_command(args: list[str]) -> str:
    return " ".join(shlex.quote(part) for part in args)


def run_command(
    args: list[str],
    *,
    cwd: Path | None = None,
    env: dict[str, str] | None = None,
    dry_run: bool = False,
    capture: bool = False,
) -> subprocess.CompletedProcess[str] | None:
    if dry_run:
        log(f"dry-run: {render_command(args)}")
        return None

    try:
        completed = subprocess.run(
            args,
            cwd=str(cwd) if cwd else None,
            env=env,
            text=True,
            capture_output=capture,
            check=False,
        )
    except FileNotFoundError as exc:
        fail(f"命令不存在：{args[0]} ({exc})")

    if completed.returncode != 0:
        detail = (completed.stderr or "").strip() or (completed.stdout or "").strip() or f"退出码 {completed.returncode}"
        fail(f"命令执行失败：{render_command(args)}\n{detail}")

    return completed


def run_mysql_sql(
    mysql_base_args: list[str],
    db_password: str,
    sql: str,
    *,
    dry_run: bool,
) -> None:
    if dry_run:
        log(f"dry-run: mysql -e {sql}")
        return

    run_command(
        mysql_base_args + ["-e", sql],
        env=mysql_env(db_password),
        capture=True,
    )


def query_mysql_value(
    mysql_base_args: list[str],
    db_password: str,
    sql: str,
) -> str:
    completed = run_command(
        mysql_base_args + ["-Nse", sql],
        env=mysql_env(db_password),
        capture=True,
    )
    assert completed is not None
    return (completed.stdout or "").strip()


def run_artisan_php(php_code: str, *, dry_run: bool) -> None:
    if dry_run:
        log("dry-run: php artisan tinker --execute '<初始化默认管理员代码>'")
        return

    run_command(
        ["php", "artisan", "tinker", f"--execute={php_code}"],
        cwd=BACKEND_DIR,
    )


def main() -> int:
    args = parse_args()

    try:
        ensure_file(ENV_FILE, f"未找到 {ENV_FILE}，请先准备后端 .env 文件")
        ensure_file(ARTISAN_FILE, f"未找到 {ARTISAN_FILE}")
        ensure_file(
            SCHEMA_EXPORT_SCRIPT,
            f"未找到 {SCHEMA_EXPORT_SCRIPT}，无法确认 schema baseline 更新入口",
        )
        ensure_file(
            BACKEND_DIR / "vendor" / "autoload.php",
            "未检测到 Composer 依赖，请先在 backend 目录执行 composer install",
        )
        ensure_command("php", "未检测到 php 命令")

        db_connection = read_env_value("DB_CONNECTION")
        db_host = read_env_value("DB_HOST") or "127.0.0.1"
        db_port = read_env_value("DB_PORT") or "3306"
        db_database = read_env_value("DB_DATABASE")
        db_username = read_env_value("DB_USERNAME")
        db_password = read_env_value("DB_PASSWORD")
        db_socket = read_env_value("DB_SOCKET")
        app_key_value = read_env_value("APP_KEY")

        if not db_connection:
            fail(".env 中缺少 DB_CONNECTION")
        if db_connection != "mysql":
            fail(f"当前脚本仅支持 mysql，实际为：{db_connection}")
        if not db_database:
            fail(".env 中缺少 DB_DATABASE")
        if not db_username:
            fail(".env 中缺少 DB_USERNAME")
        if not args.dry_run:
            ensure_command("mysql", "未检测到 mysql 客户端命令")

        mysql_base_args = mysql_args(db_username, db_host, db_port, db_socket)
        escaped_database_name = db_database.replace("`", "``")
        create_database_sql = (
            f"CREATE DATABASE IF NOT EXISTS `{escaped_database_name}` "
            "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        )

        log("开始初始化后端数据库")
        log(f"后端目录：{BACKEND_DIR}")
        log(f"数据库连接：{db_username}@{db_host}:{db_port}/{db_database}")
        log(f"数据库密码：{mask_secret(db_password)}")
        if db_socket:
            log("检测到 DB_SOCKET，将优先使用 Unix Socket 连接")

        if args.reset:
            drop_database_sql = f"DROP DATABASE IF EXISTS `{escaped_database_name}`;"
            log(f"重置目标数据库：{db_database}")
            run_mysql_sql(
                mysql_base_args,
                db_password,
                drop_database_sql,
                dry_run=args.dry_run,
            )

        run_mysql_sql(
            mysql_base_args,
            db_password,
            create_database_sql,
            dry_run=args.dry_run,
        )

        escaped_table_schema = db_database.replace("'", "''")
        table_count_sql = (
            "SELECT COUNT(*) FROM information_schema.tables "
            f"WHERE table_schema = '{escaped_table_schema}';"
        )
        existing_table_count = "0"
        if args.dry_run:
            log("dry-run: 检查目标数据库当前表数量")
        else:
            existing_table_count = query_mysql_value(mysql_base_args, db_password, table_count_sql) or "0"

        if not app_key_value:
            log("未检测到 APP_KEY，开始生成 Laravel 应用密钥")
            run_command(
                ["php", "artisan", "key:generate", "--ansi"],
                cwd=BACKEND_DIR,
                dry_run=args.dry_run,
            )
        else:
            log("已检测到 APP_KEY，跳过密钥生成")

        log("清理 Laravel 缓存")
        run_command(
            ["php", "artisan", "optimize:clear"],
            cwd=BACKEND_DIR,
            dry_run=args.dry_run,
        )

        if args.dry_run:
            if SCHEMA_FILE.is_file():
                log(f"dry-run: 若数据库为空，将优先导入 schema baseline {SCHEMA_FILE}")
            else:
                log("dry-run: 未检测到 schema baseline，将直接执行普通迁移")
        else:
            if existing_table_count == "0":
                if not SCHEMA_FILE.is_file():
                    fail(
                        f"目标数据库为空，但未找到 schema baseline：{SCHEMA_FILE}\n"
                        f"请先执行：php {SCHEMA_EXPORT_SCRIPT}"
                    )
                log("检测到空数据库，将通过 schema baseline 初始化当前表结构")
            else:
                log(f"检测到数据库已有 {existing_table_count} 张表，跳过 schema baseline 导入")

        log("执行数据库迁移")
        migrate_args = ["php", "artisan", "migrate", "--force"]
        if SCHEMA_FILE.is_file():
            if args.dry_run or existing_table_count == "0":
                migrate_args = [
                    "php",
                    "artisan",
                    "migrate",
                    "--schema-path=database/schema/mysql-schema.sql",
                    "--force",
                ]

        run_command(
            migrate_args,
            cwd=BACKEND_DIR,
            dry_run=args.dry_run,
        )

        log("初始化默认管理员 cerbo")
        run_artisan_php(ADMIN_BOOTSTRAP_CODE, dry_run=args.dry_run)

        log("数据库初始化完成")
        return 0
    except InstallDbError as exc:
        print(f"[install-db] 错误：{exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
