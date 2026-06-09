#!/usr/bin/env python3
"""把旧版 MySQL dump 的数据迁移到当前项目数据库。

约束：
- 不修改当前表结构。
- 不恢复旧字段。
- 不保留 products.name。
- 依赖 Python 标准库和本机 mysql 命令。
"""

from __future__ import annotations

import argparse
import os
import re
import shlex
import shutil
import subprocess
import sys
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any


SCRIPT_PATH = Path(__file__).resolve()
BACKEND_DIR = SCRIPT_PATH.parents[1]
DEFAULT_ENV_FILE = BACKEND_DIR / ".env"

SKIP_IMPORT_TABLES = {
    "jobs",
    "password_reset_tokens",
    "personal_access_tokens",
    "sessions",
}

PRESERVE_TABLES = {
    "migrations",
}

ALLOW_MISSING_SOURCE_TABLES = {
    "activity_logs",
    "gateway_logs",
    "schedule_run_logs",
}

FILTER_CODEX_SETTINGS_SQL = "`group_key` NOT REGEXP '^codex_(runtime|service)_'"

CORE_SCHEMA_COLUMNS = {
    "products": {"remark"},
    "orders": {"product_spec_snapshot"},
    "invoices": {"product_spec_snapshot"},
    "settings": {"group_key", "item_key", "item_value"},
}

RUNTIME_TABLES_TO_ASSERT_EMPTY = {
    "jobs",
    "password_reset_tokens",
    "personal_access_tokens",
    "sessions",
}


class MigrationError(RuntimeError):
    """迁移过程中的可预期错误。"""


@dataclass(frozen=True)
class DbConfig:
    host: str
    port: str
    database: str
    username: str
    password: str
    socket: str


@dataclass(frozen=True)
class ColumnInfo:
    name: str
    nullable: bool
    default: str | None
    extra: str

    @property
    def can_be_omitted(self) -> bool:
        return self.nullable or self.default is not None or "auto_increment" in self.extra.lower()


@dataclass(frozen=True)
class StagingContext:
    database: str
    prefix: str = ""

    @property
    def uses_prefixed_tables(self) -> bool:
        return self.prefix != ""

    def table_name(self, table: str) -> str:
        return f"{self.prefix}{table}"

    def qualified_table(self, table: str) -> str:
        return f"{quote_db(self.database)}.{quote_identifier(self.table_name(table))}"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="把旧数据库 SQL dump 的数据迁移到当前项目数据库，只迁移数据，不改结构。",
    )
    parser.add_argument(
        "--dump",
        required=True,
        help="旧数据库 SQL dump 文件路径。",
    )
    parser.add_argument(
        "--env",
        default=str(DEFAULT_ENV_FILE),
        help=f"后端 .env 文件路径，默认：{DEFAULT_ENV_FILE}",
    )
    parser.add_argument(
        "--target-db",
        default="",
        help="目标数据库名，默认读取 .env 的 DB_DATABASE。",
    )
    parser.add_argument(
        "--staging-db",
        default="",
        help="临时库名，默认自动生成。",
    )
    parser.add_argument(
        "--keep-staging",
        action="store_true",
        help="迁移成功后保留临时库，便于人工检查。",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="只检查连接、结构和映射，不创建临时库，不写入目标库。",
    )

    return parser.parse_args()


def log(message: str) -> None:
    print(f"[legacy-migrate] {message}", flush=True)


def fail(message: str) -> None:
    raise MigrationError(message)


def read_env_file(path: Path) -> dict[str, str]:
    if not path.is_file():
        fail(f"未找到 .env 文件：{path}")

    values: dict[str, str] = {}
    for raw_line in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip()
        if not key:
            continue

        if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
            value = value[1:-1]

        values[key] = value

    return values


def load_db_config(env_file: Path, target_db: str) -> DbConfig:
    env = read_env_file(env_file)
    connection = env.get("DB_CONNECTION", "")
    if connection != "mysql":
        fail(f"当前脚本仅支持 mysql，实际 DB_CONNECTION={connection!r}")

    database = target_db.strip() or env.get("DB_DATABASE", "").strip()
    username = env.get("DB_USERNAME", "").strip()
    if not database:
        fail(".env 缺少 DB_DATABASE，或未传入 --target-db")
    if not username:
        fail(".env 缺少 DB_USERNAME")

    return DbConfig(
        host=env.get("DB_HOST", "127.0.0.1").strip() or "127.0.0.1",
        port=env.get("DB_PORT", "3306").strip() or "3306",
        database=database,
        username=username,
        password=env.get("DB_PASSWORD", ""),
        socket=env.get("DB_SOCKET", "").strip(),
    )


def mysql_env(config: DbConfig) -> dict[str, str]:
    env = os.environ.copy()
    if config.password:
        env["MYSQL_PWD"] = config.password
    return env


def mysql_args(config: DbConfig, database: str | None = None) -> list[str]:
    args = [
        "mysql",
        "--default-character-set=utf8mb4",
        "--connect-timeout=5",
        "-u",
        config.username,
    ]
    if config.socket:
        args.append(f"--socket={config.socket}")
    else:
        args.extend(["-h", config.host, "-P", config.port, "--protocol=TCP"])

    if database:
        args.append(database)

    return args


def mysql_query_args(config: DbConfig, database: str | None = None) -> list[str]:
    return mysql_args(config, database) + [
        "--batch",
        "--raw",
        "--skip-column-names",
    ]


def run_command(
    args: list[str],
    *,
    env: dict[str, str] | None = None,
    stdin_path: Path | None = None,
    capture: bool = False,
) -> subprocess.CompletedProcess[str]:
    safe_args = [arg if not arg.startswith("--password") else "--password=******" for arg in args]
    try:
        if stdin_path is not None:
            with stdin_path.open("rb") as stdin_file:
                completed = subprocess.run(
                    args,
                    stdin=stdin_file,
                    text=True,
                    capture_output=capture,
                    env=env,
                    check=False,
                )
        else:
            completed = subprocess.run(
                args,
                text=True,
                capture_output=capture,
                env=env,
                check=False,
            )
    except FileNotFoundError as exc:
        fail(f"命令不存在：{args[0]} ({exc})")

    if completed.returncode != 0:
        command_text = " ".join(shlex.quote(part) for part in safe_args)
        stderr = (completed.stderr or "").strip()
        stdout = (completed.stdout or "").strip()
        detail = stderr or stdout or f"退出码 {completed.returncode}"
        fail(f"命令执行失败：{command_text}\n{detail}")

    return completed


def run_sql(config: DbConfig, sql: str, database: str | None = None) -> None:
    run_command(
        mysql_args(config, database) + ["-e", sql],
        env=mysql_env(config),
        capture=True,
    )


def query_rows(config: DbConfig, sql: str, database: str | None = None) -> list[list[str]]:
    completed = run_command(
        mysql_query_args(config, database) + ["-e", sql],
        env=mysql_env(config),
        capture=True,
    )
    output = completed.stdout or ""
    rows: list[list[str]] = []
    for line in output.splitlines():
        rows.append(line.split("\t"))
    return rows


def query_scalar(config: DbConfig, sql: str, database: str | None = None) -> str:
    rows = query_rows(config, sql, database)
    if not rows:
        return ""
    return rows[0][0] if rows[0] else ""


def quote_identifier(value: str) -> str:
    return "`" + value.replace("`", "``") + "`"


def quote_string(value: str) -> str:
    replacements = {
        "\\": "\\\\",
        "\0": "\\0",
        "\n": "\\n",
        "\r": "\\r",
        "\t": "\\t",
        "\b": "\\b",
        "\x1a": "\\Z",
        "'": "\\'",
    }
    escaped = "".join(replacements.get(char, char) for char in value)
    return f"'{escaped}'"


def quote_db(value: str) -> str:
    return quote_identifier(value)


def validate_dump_path(path: Path) -> None:
    if not path.is_file():
        fail(f"旧 SQL dump 不存在：{path}")
    if path.stat().st_size <= 0:
        fail(f"旧 SQL dump 是空文件：{path}")


def ensure_mysql_client() -> None:
    if shutil.which("mysql") is None:
        fail("未找到 mysql 命令，请先安装 MySQL 客户端")


def build_default_staging_db(target_db: str) -> str:
    suffix = time.strftime("%Y%m%d%H%M%S")
    base = re.sub(r"[^0-9A-Za-z_]", "_", target_db)
    candidate = f"{base}_legacy_stage_{suffix}"
    return candidate[:64]


def build_staging_prefix() -> str:
    return f"__legacy_{time.strftime('%Y%m%d%H%M%S')}_"


def parse_dump_schema(path: Path) -> dict[str, list[str]]:
    text = path.read_text(encoding="utf-8", errors="replace")
    tables: dict[str, list[str]] = {}
    pattern = re.compile(r"CREATE TABLE `([^`]+)` \((.*?)\n\) ENGINE=", re.S)
    for match in pattern.finditer(text):
        table = match.group(1)
        columns: list[str] = []
        for raw_line in match.group(2).splitlines():
            line = raw_line.strip().rstrip(",")
            column_match = re.match(r"`([^`]+)`\s+", line)
            if column_match:
                columns.append(column_match.group(1))
        tables[table] = columns

    if not tables:
        fail(f"未能从旧 SQL dump 解析出 CREATE TABLE：{path}")

    return tables


def fetch_table_columns(
    config: DbConfig,
    database: str,
    table_prefix: str = "",
) -> dict[str, dict[str, ColumnInfo]]:
    filters: list[str] = []
    if table_prefix:
        filters.append(f"LEFT(TABLE_NAME, {len(table_prefix)}) = {quote_string(table_prefix)}")
    else:
        filters.append("TABLE_NAME NOT LIKE '__legacy\\_%'")

    where_suffix = ""
    if filters:
        where_suffix = " AND " + " AND ".join(filters)

    sql = """
        SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        {where_suffix}
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    """.format(where_suffix=where_suffix)
    result: dict[str, dict[str, ColumnInfo]] = {}
    for table, column, nullable, default, extra in query_rows(config, sql, database):
        table_key = table[len(table_prefix) :] if table_prefix else table
        if not table_key:
            continue

        result.setdefault(table_key, {})[column] = ColumnInfo(
            name=column,
            nullable=nullable.upper() == "YES",
            default=None if default == "NULL" else default,
            extra=extra,
        )
    return result


def fetch_auto_increment_tables(config: DbConfig, database: str) -> set[str]:
    sql = """
        SELECT DISTINCT TABLE_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND EXTRA LIKE '%auto_increment%'
    """
    return {row[0] for row in query_rows(config, sql, database)}


def assert_core_schema(target_columns: dict[str, dict[str, ColumnInfo]]) -> None:
    missing: list[str] = []
    for table, columns in CORE_SCHEMA_COLUMNS.items():
        if table not in target_columns:
            missing.append(table)
            continue

        for column in columns:
            if column not in target_columns[table]:
                missing.append(f"{table}.{column}")

    product_columns = target_columns.get("products", {})
    if "name" in product_columns:
        missing.append("products 表仍存在旧字段 name，请先使用当前项目结构初始化目标库")

    if missing:
        fail("目标库结构不符合当前项目要求：" + ", ".join(missing))


def staging_columns_from_dump(dump_schema: dict[str, list[str]]) -> dict[str, dict[str, ColumnInfo]]:
    return {
        table: {
            column: ColumnInfo(name=column, nullable=True, default=None, extra="")
            for column in columns
        }
        for table, columns in dump_schema.items()
    }


def build_select_expression(table: str, column: str, staging_columns: set[str]) -> str | None:
    if table in {"orders", "invoices"} and column == "product_spec_snapshot":
        candidates = []
        if "product_spec_snapshot" in staging_columns:
            candidates.append("NULLIF(`product_spec_snapshot`, '')")
        if "product_name_snapshot" in staging_columns:
            candidates.append("NULLIF(`product_name_snapshot`, '')")
        if candidates:
            return "COALESCE(" + ", ".join(candidates) + ")"
        return None

    if column in staging_columns:
        return quote_identifier(column)

    return None


def build_table_plan(
    table: str,
    target_columns: dict[str, ColumnInfo],
    staging_columns: dict[str, ColumnInfo],
) -> tuple[list[str], list[str], list[str]]:
    target_column_names = list(target_columns.keys())
    staging_column_names = set(staging_columns.keys())
    insert_columns: list[str] = []
    select_expressions: list[str] = []
    omitted_required: list[str] = []

    for column in target_column_names:
        expression = build_select_expression(table, column, staging_column_names)
        if expression is not None:
            insert_columns.append(column)
            select_expressions.append(expression)
            continue

        if not target_columns[column].can_be_omitted:
            omitted_required.append(column)

    return insert_columns, select_expressions, omitted_required


def print_plan(
    target_columns: dict[str, dict[str, ColumnInfo]],
    staging_columns: dict[str, dict[str, ColumnInfo]],
) -> list[str]:
    tables = sorted(target_columns.keys())
    errors: list[str] = []

    log("迁移计划：")
    for table in tables:
        if table in PRESERVE_TABLES:
            log(f"  - {table}: 保留目标库现有数据")
            continue
        if table in SKIP_IMPORT_TABLES:
            log(f"  - {table}: 清空并重置自增，不导入旧数据")
            continue
        if table not in staging_columns:
            if table in ALLOW_MISSING_SOURCE_TABLES:
                log(f"  - {table}: 旧库缺少同名表，保留空表")
                continue
            errors.append(f"{table}: 旧库缺少同名表")
            log(f"  - {table}: 旧库缺少同名表，无法迁移")
            continue

        insert_columns, _, omitted_required = build_table_plan(
            table,
            target_columns[table],
            staging_columns[table],
        )
        if omitted_required:
            errors.append(f"{table}: 目标必填字段无法映射：{', '.join(omitted_required)}")

        old_only = sorted(set(staging_columns[table]) - set(target_columns[table]))
        extra_note = ""
        if table == "settings":
            extra_note = "，过滤 codex 临时配置"
        elif table == "products":
            skipped_legacy = [column for column in ["name", "supplier_product_name"] if column in old_only]
            if skipped_legacy:
                extra_note = "，跳过旧字段 " + ", ".join(skipped_legacy)

        log(
            f"  - {table}: 映射 {len(insert_columns)} 个字段"
            f"{extra_note}"
        )

    if errors:
        fail("迁移计划存在无法处理的问题：\n" + "\n".join(errors))

    return tables


def create_staging_database(config: DbConfig, staging_db: str) -> None:
    run_sql(
        config,
        f"DROP DATABASE IF EXISTS {quote_db(staging_db)}; "
        f"CREATE DATABASE {quote_db(staging_db)} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
    )


def import_dump_to_staging(config: DbConfig, staging_db: str, dump_path: Path) -> None:
    log(f"导入旧 SQL 到临时库：{staging_db}")
    run_command(
        mysql_args(config, staging_db),
        env=mysql_env(config),
        stdin_path=dump_path,
        capture=True,
    )


def rewrite_dump_line_for_prefix(line: str, table_names: set[str], prefix: str) -> str:
    def replace_table(match: re.Match[str]) -> str:
        name = match.group(2)
        if name not in table_names:
            return match.group(0)

        return f"{match.group(1)}`{prefix}{name}`"

    anchored_patterns = [
        r"^(DROP TABLE IF EXISTS )`([^`]+)`",
        r"^(CREATE TABLE )`([^`]+)`",
        r"^(LOCK TABLES )`([^`]+)`",
        r"^(INSERT INTO )`([^`]+)`",
    ]
    rewritten = line
    for pattern in anchored_patterns:
        rewritten = re.sub(pattern, replace_table, rewritten, count=1)

    stripped = line.lstrip()
    if stripped.startswith("/*!40000 ALTER TABLE") or stripped.startswith("ALTER TABLE"):
        rewritten = re.sub(r"(ALTER TABLE )`([^`]+)`", replace_table, rewritten, count=1)

    return rewritten


def strip_foreign_key_constraints(create_table_lines: list[str]) -> list[str]:
    stripped_lines = [
        line
        for line in create_table_lines
        if not (
            line.lstrip().startswith("CONSTRAINT ")
            and " FOREIGN KEY " in line.upper()
        )
    ]

    for index in range(len(stripped_lines) - 1, -1, -1):
        if stripped_lines[index].lstrip().startswith(") ENGINE="):
            continue

        if stripped_lines[index].rstrip().endswith(","):
            stripped_lines[index] = stripped_lines[index].rstrip().rstrip(",") + "\n"
        break

    return stripped_lines


def rewrite_dump_lines_for_prefix(
    dump_file: Any,
    table_names: set[str],
    prefix: str,
) -> Any:
    create_table_buffer: list[str] = []

    for line in dump_file:
        if create_table_buffer:
            create_table_buffer.append(line)
            if line.lstrip().startswith(") ENGINE="):
                for buffered_line in strip_foreign_key_constraints(create_table_buffer):
                    yield rewrite_dump_line_for_prefix(buffered_line, table_names, prefix)
                create_table_buffer = []
            continue

        if line.startswith("CREATE TABLE "):
            create_table_buffer.append(line)
            continue

        yield rewrite_dump_line_for_prefix(line, table_names, prefix)

    if create_table_buffer:
        for buffered_line in strip_foreign_key_constraints(create_table_buffer):
            yield rewrite_dump_line_for_prefix(buffered_line, table_names, prefix)


def import_dump_to_prefixed_staging(
    config: DbConfig,
    target_db: str,
    dump_path: Path,
    dump_schema: dict[str, list[str]],
    prefix: str,
) -> None:
    table_names = set(dump_schema.keys())
    log(f"导入旧 SQL 到同库临时表：{prefix}*")

    process = subprocess.Popen(
        mysql_args(config, target_db),
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        encoding="utf-8",
        errors="replace",
        env=mysql_env(config),
    )
    assert process.stdin is not None

    try:
        with dump_path.open("r", encoding="utf-8", errors="replace") as dump_file:
            for line in rewrite_dump_lines_for_prefix(dump_file, table_names, prefix):
                process.stdin.write(line)
        process.stdin.close()
        stdout = process.stdout.read() if process.stdout is not None else ""
        stderr = process.stderr.read() if process.stderr is not None else ""
        returncode = process.wait()
    except (BrokenPipeError, OSError):
        stdout = process.stdout.read() if process.stdout is not None else ""
        stderr = process.stderr.read() if process.stderr is not None else ""
        returncode = process.wait()

    if returncode != 0:
        detail = (stderr or stdout or f"退出码 {returncode}").strip()
        fail(f"导入旧 SQL 到同库临时表失败：\n{detail}")


def clear_target_tables(
    config: DbConfig,
    target_db: str,
    tables: list[str],
    auto_increment_tables: set[str],
) -> None:
    log("清空目标库数据表")
    for table in tables:
        if table in PRESERVE_TABLES:
            continue

        clear_target_table(config, target_db, table, auto_increment_tables)


def clear_target_table(
    config: DbConfig,
    target_db: str,
    table: str,
    auto_increment_tables: set[str],
) -> None:
    statements = [
        "SET FOREIGN_KEY_CHECKS=0",
        f"DELETE FROM {quote_identifier(table)}",
    ]
    if table in auto_increment_tables:
        statements.append(f"ALTER TABLE {quote_identifier(table)} AUTO_INCREMENT = 1")
    statements.append("SET FOREIGN_KEY_CHECKS=1")

    run_sql(config, "; ".join(statements) + ";", target_db)


def count_rows(config: DbConfig, database: str, table: str, where_sql: str = "") -> int:
    sql = f"SELECT COUNT(*) FROM {quote_identifier(table)}"
    if where_sql:
        sql += f" WHERE {where_sql}"
    return int(query_scalar(config, sql, database) or "0")


def count_staging_rows(config: DbConfig, staging: StagingContext, table: str, where_sql: str = "") -> int:
    sql = f"SELECT COUNT(*) FROM {staging.qualified_table(table)}"
    if where_sql:
        sql += f" WHERE {where_sql}"
    return int(query_scalar(config, sql) or "0")


def migrate_table(
    config: DbConfig,
    target_db: str,
    staging: StagingContext,
    table: str,
    target_columns: dict[str, ColumnInfo],
    staging_columns: dict[str, ColumnInfo],
    auto_increment_tables: set[str],
) -> tuple[int, int]:
    insert_columns, select_expressions, omitted_required = build_table_plan(
        table,
        target_columns,
        staging_columns,
    )
    if omitted_required:
        fail(f"{table} 存在无法映射的目标必填字段：{', '.join(omitted_required)}")

    where_sql = FILTER_CODEX_SETTINGS_SQL if table == "settings" else ""
    source_count = count_staging_rows(config, staging, table, where_sql)
    clear_target_table(config, target_db, table, auto_increment_tables)
    if not insert_columns:
        return source_count, 0

    insert_list = ", ".join(quote_identifier(column) for column in insert_columns)
    select_list = ", ".join(select_expressions)
    sql = (
        f"INSERT INTO {quote_db(target_db)}.{quote_identifier(table)} ({insert_list}) "
        f"SELECT {select_list} FROM {staging.qualified_table(table)}"
    )
    if where_sql:
        sql += f" WHERE {where_sql}"
    sql += ";"

    run_sql(config, f"SET FOREIGN_KEY_CHECKS=0; {sql} SET FOREIGN_KEY_CHECKS=1;")
    target_count = count_rows(config, target_db, table)

    return source_count, target_count


def migrate_all_tables(
    config: DbConfig,
    target_db: str,
    staging: StagingContext,
    tables: list[str],
    target_columns: dict[str, dict[str, ColumnInfo]],
    staging_columns: dict[str, dict[str, ColumnInfo]],
    auto_increment_tables: set[str],
) -> None:
    log("开始复制旧数据")
    for table in tables:
        if table in PRESERVE_TABLES:
            log(f"  - {table}: 保留目标库现有数据")
            continue
        if table in SKIP_IMPORT_TABLES:
            clear_target_table(config, target_db, table, auto_increment_tables)
            log(f"  - {table}: 不导入旧数据，保持空表")
            continue
        if table not in staging_columns:
            if table in ALLOW_MISSING_SOURCE_TABLES:
                clear_target_table(config, target_db, table, auto_increment_tables)
                log(f"  - {table}: 旧库缺少同名表，保持空表")
                continue
            fail(f"旧库缺少目标表：{table}")

        source_count, target_count = migrate_table(
            config,
            target_db,
            staging,
            table,
            target_columns[table],
            staging_columns[table],
            auto_increment_tables,
        )
        log(f"  - {table}: 旧库 {source_count} 行，目标库 {target_count} 行")


def assert_empty_runtime_tables(config: DbConfig, target_db: str) -> None:
    not_empty: list[str] = []
    for table in sorted(RUNTIME_TABLES_TO_ASSERT_EMPTY):
        if count_rows(config, target_db, table) != 0:
            not_empty.append(table)

    if not_empty:
        fail("运行态表应为空，但发现数据：" + ", ".join(not_empty))


def run_integrity_checks(config: DbConfig, target_db: str, target_columns: dict[str, dict[str, ColumnInfo]]) -> None:
    checks = [
        (
            "invoices.user_id",
            "SELECT COUNT(*) FROM invoices i LEFT JOIN users u ON u.id = i.user_id WHERE u.id IS NULL",
        ),
        (
            "invoices.product_id",
            "SELECT COUNT(*) FROM invoices i LEFT JOIN products p ON p.id = i.product_id WHERE i.product_id IS NOT NULL AND p.id IS NULL",
        ),
        (
            "invoice_items.invoice_id",
            "SELECT COUNT(*) FROM invoice_items ii LEFT JOIN invoices i ON i.id = ii.invoice_id WHERE i.id IS NULL",
        ),
        (
            "payments.user_id",
            "SELECT COUNT(*) FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE u.id IS NULL",
        ),
        (
            "payments.invoice_id",
            "SELECT COUNT(*) FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id WHERE p.invoice_id IS NOT NULL AND i.id IS NULL",
        ),
        (
            "payment_callbacks.payment_id",
            "SELECT COUNT(*) FROM payment_callbacks pc LEFT JOIN payments p ON p.id = pc.payment_id WHERE p.id IS NULL",
        ),
        (
            "services.user_id",
            "SELECT COUNT(*) FROM services s LEFT JOIN users u ON u.id = s.user_id WHERE u.id IS NULL",
        ),
        (
            "services.product_id",
            "SELECT COUNT(*) FROM services s LEFT JOIN products p ON p.id = s.product_id WHERE p.id IS NULL",
        ),
        (
            "services.invoice_id",
            "SELECT COUNT(*) FROM services s LEFT JOIN invoices i ON i.id = s.invoice_id WHERE s.invoice_id IS NOT NULL AND i.id IS NULL",
        ),
        (
            "user_accounts.user_id",
            "SELECT COUNT(*) FROM user_accounts ua LEFT JOIN users u ON u.id = ua.user_id WHERE u.id IS NULL",
        ),
        (
            "ticket_replies.ticket_id",
            "SELECT COUNT(*) FROM ticket_replies tr LEFT JOIN tickets t ON t.id = tr.ticket_id WHERE t.id IS NULL",
        ),
    ]

    failed: list[str] = []
    for label, sql in checks:
        count = int(query_scalar(config, sql, target_db) or "0")
        if count > 0:
            failed.append(f"{label}: {count}")

    if "name" in target_columns.get("products", {}):
        failed.append("products.name: 目标表仍存在旧字段")

    assert_empty_runtime_tables(config, target_db)

    if failed:
        fail("迁移后引用检查失败：\n" + "\n".join(failed))

    log("关键引用检查通过")


def drop_staging_database(config: DbConfig, staging_db: str) -> None:
    run_sql(config, f"DROP DATABASE IF EXISTS {quote_db(staging_db)};")


def drop_prefixed_staging_tables(
    config: DbConfig,
    target_db: str,
    dump_schema: dict[str, list[str]],
    prefix: str,
) -> None:
    table_names = sorted(dump_schema.keys(), reverse=True)
    if not table_names:
        return

    statements = [
        "SET FOREIGN_KEY_CHECKS=0",
        *[
            f"DROP TABLE IF EXISTS {quote_identifier(prefix + table)}"
            for table in table_names
        ],
        "SET FOREIGN_KEY_CHECKS=1",
    ]
    run_sql(config, "; ".join(statements) + ";", target_db)


def prepare_staging(
    config: DbConfig,
    dump_path: Path,
    dump_schema: dict[str, list[str]],
    staging_db: str,
) -> StagingContext:
    log(f"目标库：{config.database}")
    log(f"临时库：{staging_db}")

    try:
        create_staging_database(config, staging_db)
    except MigrationError as exc:
        if "Access denied" not in str(exc):
            raise

        prefix = build_staging_prefix()
        log("当前数据库用户无建库权限，切换为同库临时前缀表模式")
        import_dump_to_prefixed_staging(config, config.database, dump_path, dump_schema, prefix)
        return StagingContext(database=config.database, prefix=prefix)

    import_dump_to_staging(config, staging_db, dump_path)
    return StagingContext(database=staging_db)


def dry_run(config: DbConfig, dump_path: Path) -> None:
    log("dry-run: 开始检查，不会写入数据库")
    target_columns = fetch_table_columns(config, config.database)
    if not target_columns:
        fail(f"目标库 {config.database} 没有表，请先初始化当前项目数据库")

    assert_core_schema(target_columns)
    staging_columns = staging_columns_from_dump(parse_dump_schema(dump_path))
    print_plan(target_columns, staging_columns)
    log("dry-run: 检查完成")


def run_migration(config: DbConfig, dump_path: Path, staging_db: str, keep_staging: bool) -> None:
    if staging_db == config.database:
        fail("临时库名不能和目标库名相同")

    target_columns = fetch_table_columns(config, config.database)
    if not target_columns:
        fail(f"目标库 {config.database} 没有表，请先初始化当前项目数据库")

    assert_core_schema(target_columns)
    dump_schema = parse_dump_schema(dump_path)

    staging = prepare_staging(config, dump_path, dump_schema, staging_db)
    staging_columns = fetch_table_columns(config, staging.database, staging.prefix)
    tables = print_plan(target_columns, staging_columns)

    auto_increment_tables = fetch_auto_increment_tables(config, config.database)
    clear_target_tables(config, config.database, tables, auto_increment_tables)
    migrate_all_tables(
        config,
        config.database,
        staging,
        tables,
        target_columns,
        staging_columns,
        auto_increment_tables,
    )
    run_integrity_checks(config, config.database, target_columns)

    if keep_staging:
        if staging.uses_prefixed_tables:
            log(f"按要求保留同库临时表：{staging.prefix}*")
        else:
            log(f"按要求保留临时库：{staging.database}")
    else:
        if staging.uses_prefixed_tables:
            drop_prefixed_staging_tables(config, config.database, dump_schema, staging.prefix)
            log("同库临时表已删除")
        else:
            drop_staging_database(config, staging.database)
            log("临时库已删除")

    log("迁移完成")


def main() -> int:
    args = parse_args()
    dump_path = Path(args.dump).expanduser().resolve()
    env_file = Path(args.env).expanduser().resolve()

    try:
        ensure_mysql_client()
        validate_dump_path(dump_path)
        config = load_db_config(env_file, args.target_db)
        staging_db = args.staging_db.strip() or build_default_staging_db(config.database)

        if args.dry_run:
            dry_run(config, dump_path)
        else:
            run_migration(config, dump_path, staging_db, args.keep_staging)
    except MigrationError as exc:
        print(f"[legacy-migrate] 错误：{exc}", file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
