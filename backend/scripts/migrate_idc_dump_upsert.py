#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""旧版本 MySQL dump -> 新版本数据库：数据迁移（只做 DML，绝不执行 DDL）。

范围与保证
----------
1. 只读 dump、只写数据行：全程只执行 SELECT 与 INSERT ... ON DUPLICATE KEY UPDATE，
   不执行 CREATE / ALTER / DROP / TRUNCATE / RENAME / REPLACE，
   因此不会新增、删除或修改任何表、字段、索引、约束与外键。
2. 幂等：以目标表主键为冲突依据 upsert（operation_logs 并入 activity_logs 时改用
   确定性 event_id 命中唯一键），重复执行不会产生重复行。
3. 分批：按行数与字节数双阈值分批，一批一个事务并提交，避免大事务长时间持锁。
4. 顺序：先按外键依赖拓扑排序载入（父表先于子表），避免外键报错。
5. 凭据：运行时从 backend/.env 读取，命令行不接受密码，日志与报告均不回显口令。
6. 时间：会话固定 time_zone='+00:00'，dump 时间字面量原样写入，不做任何加减。
7. 失败隔离：批失败回滚后逐行重试定位出错行，记录到报告并继续后续批次。

用法
----
    python backend/scripts/migrate_idc_dump_upsert.py --describe-structure
    python backend/scripts/migrate_idc_dump_upsert.py --dry-run       # 解析+体检，不写库
    python backend/scripts/migrate_idc_dump_upsert.py                 # 正式迁移
    python backend/scripts/migrate_idc_dump_upsert.py --verify-only   # 只校验，不写数据
    python backend/scripts/migrate_idc_dump_upsert.py --tables users,orders,payments
    python backend/scripts/migrate_idc_dump_upsert.py --merge-operation-logs
    python backend/scripts/migrate_idc_dump_upsert.py --conflict keep-newer

退出码：0 成功；1 校验未通过或存在失败行；2 参数或前置条件错误。
"""

from __future__ import annotations

import argparse
import hashlib
import json
import random
import re
import shutil
import sys
import tempfile
import time
from dataclasses import dataclass, field
from datetime import date, datetime, timedelta
from decimal import Decimal, InvalidOperation, localcontext
from pathlib import Path
from typing import Any, Callable, Dict, Iterator, List, Optional, Sequence, Set, Tuple

try:
    import pymysql
except ModuleNotFoundError:  # pragma: no cover
    sys.exit('缺少 pymysql：请先执行 pip install pymysql')

BT = chr(96)  # 反引号
REPO_ROOT = Path(__file__).resolve().parents[2]
BACKEND_ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DUMP = REPO_ROOT / 'idc_2026-10-26_02-28-18_mysql_data_wWKMz.sql'
DEFAULT_ENV = BACKEND_ROOT / '.env'

# 默认不迁移：凭据类与框架自身簿记类，混入旧库记录会带来安全问题或框架状态错乱。
DEFAULT_EXCLUDED = {
    'migrations': '新库自身的结构版本簿记，写入旧库记录会让 Laravel 迁移状态错乱',
    'personal_access_tokens': '会话令牌属于凭据，迁移等于把旧库令牌带进新库',
    'password_reset_tokens': '密码重置令牌属于凭据，且新旧库有效窗口不同',
    'failed_jobs': '队列失败调试数据，无业务含义',
    'sessions': '会话数据属于临时凭据载体',
    'cache': '缓存非业务数据',
    'cache_locks': '缓存锁非业务数据',
}

# 只放"出现在行首就一定是表级约束"的词。KEY/INDEX/CHECK/PERIOD 同时也是合法列名，
# 放进来会把同名业务列整列丢掉（且 dump 里已删反引号，无法靠引号区分）。
NOT_COLUMN_TOKENS = {
    'PRIMARY', 'UNIQUE', 'CONSTRAINT', 'FOREIGN', 'FULLTEXT', 'SPATIAL',
}

NUMERIC_HINTS = ('int', 'decimal', 'numeric', 'float', 'double', 'real', 'bit')
TEMPORAL_HINTS = ('date', 'datetime', 'timestamp', 'time', 'year')


class MigrationError(Exception):
    """迁移过程中的致命错误。"""


FK_ERROR_CODES = (1452, 1216, 1217)


def is_fk_error(exc: BaseException) -> bool:
    """外键类错误：父行还没写入（1452）或父行被删（1216/1217）。

    这类失败有可能只是顺序问题，交回补写轮次重试，而不是直接判死。
    """
    args = getattr(exc, 'args', ())
    if args and isinstance(args[0], int) and args[0] in FK_ERROR_CODES:
        return True
    text = str(exc).lower()
    return 'a foreign key constraint fails' in text or 'foreign key constraint' in text


DEADLOCK_CODES = (1213, 1205)


def is_deadlock(exc: BaseException) -> bool:
    """死锁（1213）/锁等待超时（1205）：可整批重试，不必逐行定位。"""
    args = getattr(exc, 'args', ())
    if args and isinstance(args[0], int) and args[0] in DEADLOCK_CODES:
        return True
    text = str(exc).lower()
    return 'deadlock found' in text or 'lock wait timeout' in text


def qi(name: str) -> str:
    return BT + name + BT


def qt(names: Sequence[str]) -> str:
    return ','.join(qi(n) for n in names)


# ======================================================================================
# 配置与连接
# ======================================================================================

@dataclass
class DbConfig:
    host: str
    port: int
    user: str
    password: str
    database: str
    charset: str = 'utf8mb4'

    def describe(self) -> str:
        return '{0}@{1}:{2}/{3}'.format(self.user, self.host, self.port, self.database)


def read_env_file(path: Path) -> Dict[str, str]:
    """解析 Laravel .env，返回 KEY->VALUE；任何内容都不打印。"""
    values: Dict[str, str] = {}
    if not path.is_file():
        raise MigrationError('找不到环境文件：{0}'.format(path))
    for raw in path.read_text(encoding='utf-8', errors='replace').splitlines():
        line = raw.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, _, value = line.partition('=')
        key, value = key.strip(), value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in ('"', "'"):
            value = value[1:-1]
        values[key] = value
    return values


def load_db_config(env_path: Path, database_override: Optional[str]) -> DbConfig:
    env = read_env_file(env_path)
    connection = env.get('DB_CONNECTION', 'mysql').strip().lower()
    if connection not in ('mysql', 'mariadb'):
        raise MigrationError('本脚本只支持 mysql/mariadb 连接，当前 DB_CONNECTION={0}'.format(connection))
    database = (database_override or env.get('DB_DATABASE', '')).strip()
    if not database:
        raise MigrationError('无法确定目标数据库：.env 缺少 DB_DATABASE，且未传 --database')
    return DbConfig(
        host=env.get('DB_HOST', '127.0.0.1').strip() or '127.0.0.1',
        port=int(env.get('DB_PORT', '3306').strip() or 3306),
        user=env.get('DB_USERNAME', '').strip(),
        password=env.get('DB_PASSWORD', ''),
        database=database,
        charset=(env.get('DB_CHARSET', 'utf8mb4').strip() or 'utf8mb4'),
    )


def open_connection(config: DbConfig, *, autocommit: bool = False):
    conn = pymysql.connect(
        host=config.host,
        port=config.port,
        user=config.user,
        password=config.password,
        database=config.database,
        charset=config.charset,
        autocommit=autocommit,
        connect_timeout=10,
        read_timeout=600,
        write_timeout=600,
    )
    with conn.cursor() as cur:
        # dump 由 mysqldump 在 +00:00 会话下产生，写入端保持同一时区即可原样落库。
        cur.execute("SET SESSION time_zone = '+00:00'")
        # NO_AUTO_VALUE_ON_ZERO：显式写入 0 时不要被当成"分配自增值"，否则主键 0 会被改写。
        cur.execute("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,"
                    "NO_AUTO_VALUE_ON_ZERO'")
        cur.execute('SET SESSION group_concat_max_len = 1048576')
    return conn


# ======================================================================================
# 目标库结构元数据
# ======================================================================================

@dataclass
class TableMeta:
    name: str
    columns: List[str] = field(default_factory=list)
    types: Dict[str, str] = field(default_factory=dict)
    nullable: Dict[str, bool] = field(default_factory=dict)
    has_default: Dict[str, bool] = field(default_factory=dict)
    generated: Set[str] = field(default_factory=set)
    auto_increment: Set[str] = field(default_factory=set)
    pk: List[str] = field(default_factory=list)
    unique_keys: List[List[str]] = field(default_factory=list)


def load_target_meta(conn) -> Dict[str, TableMeta]:
    meta: Dict[str, TableMeta] = {}
    with conn.cursor() as cur:
        cur.execute(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA '
            'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() '
            'ORDER BY TABLE_NAME, ORDINAL_POSITION'
        )
        for table, column, ctype, nullable, default, extra in cur.fetchall():
            item = meta.setdefault(table, TableMeta(name=table))
            item.columns.append(column)
            item.types[column] = (ctype or '').lower()
            item.nullable[column] = (nullable == 'YES')
            item.has_default[column] = default is not None
            extra_l = (extra or '').lower()
            if 'generated' in extra_l and 'default_generated' not in extra_l:
                item.generated.add(column)
            if 'auto_increment' in extra_l:
                item.auto_increment.add(column)
        cur.execute(
            'SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS '
            'WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'
        )
        grouped: Dict[Tuple[str, str], List[str]] = {}
        non_unique: Dict[Tuple[str, str], int] = {}
        for table, index, column, nu in cur.fetchall():
            grouped.setdefault((table, index), []).append(column)
            non_unique[(table, index)] = int(nu)
    for (table, index), cols in grouped.items():
        if table not in meta:
            continue
        if index == 'PRIMARY':
            meta[table].pk = cols
        elif non_unique[(table, index)] == 0:
            meta[table].unique_keys.append(cols)
    return meta


@dataclass
class ForeignKey:
    name: str
    table: str
    columns: List[str]
    ref_table: str
    ref_columns: List[str]


def load_foreign_keys(conn) -> List[ForeignKey]:
    rows: Dict[str, Dict[str, Any]] = {}
    with conn.cursor() as cur:
        cur.execute(
            'SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, '
            'REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
            'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL '
            'ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION'
        )
        result = cur.fetchall()
    for name, table, column, ref_table, ref_column in result:
        item = rows.setdefault(name, {
            'table': table, 'columns': [], 'ref_table': ref_table, 'ref_columns': []})
        item['columns'].append(column)
        item['ref_columns'].append(ref_column)
    return [ForeignKey(name=n, **v) for n, v in rows.items()]


def topological_order(tables: Sequence[str], fks: Sequence[ForeignKey]) -> List[str]:
    """按外键依赖排序，父表先载入；成环时按名称打破。"""
    wanted = set(tables)
    parents: Dict[str, Set[str]] = {t: set() for t in tables}
    for fk in fks:
        if fk.table in wanted and fk.ref_table in wanted and fk.table != fk.ref_table:
            parents[fk.table].add(fk.ref_table)
    ordered: List[str] = []
    done: Set[str] = set()
    remaining = list(tables)
    while remaining:
        progressed = False
        for table in list(remaining):
            if parents[table] - done:
                continue
            ordered.append(table)
            done.add(table)
            remaining.remove(table)
            progressed = True
        if not progressed:
            table = sorted(remaining)[0]
            ordered.append(table)
            done.add(table)
            remaining.remove(table)
    return ordered


# ======================================================================================
# dump 解析
# ======================================================================================

# 表名放宽到 [\w$.-]：旧 dump 里表名可能含 - 或 .；CREATE 兼容 IF NOT EXISTS。
_TABLE_NAME = r'[`"]?([\w$.-]+)[`"]?'
CREATE_HEAD_RE = re.compile(r'^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?' + _TABLE_NAME + r'\s*\(')
INSERT_HEAD_RE = re.compile(r'^INSERT\s+(?:IGNORE\s+)?INTO\s+' + _TABLE_NAME + r'\s+VALUES\s')

_ESCAPES = {
    '0': '\0', 'n': '\n', 'r': '\r', 't': '\t', 'b': '\b', 'Z': '\x1a',
    '\\': '\\', "'": "'", '"': '"', '%': '\\%', '_': '\\_',
}


class DumpParseError(MigrationError):
    pass


def split_tuples(body: str) -> List[List[Optional[str]]]:
    """把 VALUES 之后的文本切成行：NULL -> None，其余为解码后的字符串。"""
    rows: List[List[Optional[str]]] = []
    i, n = 0, len(body)
    while True:
        while i < n and body[i] in ' \t\r\n,':
            i += 1
        if i >= n or body[i] == ';':
            break
        if body[i] != '(':
            raise DumpParseError('VALUES 期望左括号，实际 {0!r} 偏移 {1}'.format(body[i], i))
        i += 1
        fields: List[Optional[str]] = []
        while True:
            while i < n and body[i] in ' \t\r\n':
                i += 1
            if i < n and body[i] == "'":
                i += 1
                buf: List[str] = []
                closed = False
                while i < n:
                    ch = body[i]
                    if ch == '\\':
                        i += 1
                        if i < n:
                            buf.append(_ESCAPES.get(body[i], body[i]))
                            i += 1
                        continue
                    if ch == "'":
                        if i + 1 < n and body[i + 1] == "'":
                            buf.append("'")
                            i += 2
                            continue
                        i += 1
                        closed = True
                        break
                    buf.append(ch)
                    i += 1
                if not closed:
                    raise DumpParseError('字符串字面量未闭合，偏移 {0}'.format(i))
                fields.append(''.join(buf))
            else:
                start = i
                while i < n and body[i] not in ',)':
                    i += 1
                raw = body[start:i].strip()
                if not raw:
                    raise DumpParseError('第 {0} 行存在空字段（偏移 {1}）'.format(
                        len(rows) + 1, start))
                fields.append(None if raw.upper() == 'NULL' else raw)
            # 引号串结束位置可能停在空白上，必须跳过，否则会被当成一个新的空字段。
            while i < n and body[i] in ' \t\r\n':
                i += 1
            if i < n and body[i] == ',':
                i += 1
                continue
            if i < n and body[i] == ')':
                i += 1
                break
            if i >= n:
                raise DumpParseError('行未闭合，偏移 {0}'.format(i))
        rows.append(fields)
    return rows


# 行首是这些词、但第二个词是类型/约束时，仍可能是普通列名（如 key varchar(64)）。
_RESERVED_AS_COLUMN_HINT = re.compile(
    r'^(?:KEY|INDEX|CHECK|PERIOD)\s+(?:`|varchar|char|text|tinytext|mediumtext|longtext|'
    r'int|bigint|smallint|tinyint|mediumint|decimal|numeric|float|double|date|datetime|'
    r'timestamp|time|year|json|blob|binary|varbinary|enum|set|bit|bool|boolean)\b',
    re.IGNORECASE)


def parse_create_columns(lines: Sequence[str]) -> List[str]:
    columns: List[str] = []
    for line in lines[1:]:
        text = line.strip()
        if not text or text.startswith(')') or text.startswith('('):
            continue
        token = re.split(r'[\s(]', text, 1)[0]
        if not token:
            continue
        upper = token.upper()
        if upper in NOT_COLUMN_TOKENS:
            continue
        if upper in ('KEY', 'INDEX', 'CHECK', 'PERIOD'):
            # 这两个词既是约束起始词又是合法列名，只能按"后面跟类型"来判定列定义。
            if not _RESERVED_AS_COLUMN_HINT.match(text):
                continue
        if re.match(r'^\w+$', token):
            columns.append(token)
    return columns


@dataclass
class SpoolTable:
    name: str
    columns: List[str]
    path: Path
    rows: int = 0


def _statement_payload(text: str, table_columns: Dict[str, List[str]]):
    # 直接在原始语句上匹配，保证 match.end() 与 text 的偏移一致（反引号由正则容忍）。
    match = INSERT_HEAD_RE.match(text)
    if not match:
        return None
    name = match.group(1)
    columns = table_columns.get(name)
    if not columns:
        raise DumpParseError('未找到表 {0} 的 CREATE TABLE 列定义'.format(name))
    body = text[match.end():].rstrip()
    if body.endswith(';'):
        body = body[:-1]
    rows = split_tuples(body)
    for row in rows:
        if len(row) != len(columns):
            raise DumpParseError(
                '表 {0} 行字段数 {1} 与列数 {2} 不一致（INSERT 可能是带列名的完整形态，'
                '本脚本只支持 mysqldump 默认的按位置 VALUES 形态）'.format(
                    name, len(row), len(columns)))
    return name, columns, rows


def parse_dump(dump_path: Path, spool_dir: Path, *, spool: bool,
               progress_every: int = 0) -> Dict[str, SpoolTable]:
    """单次顺序读 dump：登记每表列顺序，并把 INSERT 行落到按表分文件的 spool。

    分表 spool 的意义：dump 按表名字母序导出，子表常早于父表出现；
    先落地再按外键拓扑序回放，才能保证父表先入库。
    """
    if not dump_path.is_file():
        raise MigrationError('找不到 dump 文件：{0}'.format(dump_path))
    tables: Dict[str, SpoolTable] = {}
    handles: Dict[str, Any] = {}
    table_columns: Dict[str, List[str]] = {}
    create_head: Optional[str] = None
    create_buf: List[str] = []
    stmt_buf: Optional[List[str]] = None
    statements = 0
    started = time.time()

    def absorb(text: str) -> None:
        nonlocal statements
        statements += 1
        payload = _statement_payload(text, table_columns)
        if payload is None:
            # 语句头不匹配时绝不能静默 return：那会让整表数据在无任何告警的情况下消失。
            raise DumpParseError(
                '第 {0} 条 INSERT 的语句头无法识别（表名/语法形态超出支持范围）：{1!r}'.format(
                    statements, text[:120]))
        name, columns, rows = payload
        item = tables.get(name)
        if item is None:
            item = SpoolTable(name=name, columns=columns, path=spool_dir / (name + '.jsonl'))
            tables[name] = item
        item.rows += len(rows)
        if not spool:
            return
        handle = handles.get(name)
        if handle is None:
            handle = item.path.open('a', encoding='utf-8', newline='\n')
            handles[name] = handle
        for row in rows:
            handle.write(json.dumps(row, ensure_ascii=False))
            handle.write('\n')
        if progress_every and statements % progress_every == 0:
            print('  解析中：{0} 条 INSERT，{1} 张表，{2:.1f}s'.format(
                statements, len(tables), time.time() - started), flush=True)

    try:
        with dump_path.open('r', encoding='utf-8', errors='strict', newline='') as source:
            for raw_line in source:
                line = raw_line.rstrip('\r\n')
                plain = line.replace(BT, '')
                if create_head is not None:
                    create_buf.append(plain)
                    # DDL 结束行的形态是 ") ENGINE=... COMMENT='...';"，不能只看 ");"
                    if plain.rstrip().endswith(';'):
                        table_columns[create_head] = parse_create_columns(create_buf)
                        create_head, create_buf = None, []
                    continue
                if stmt_buf is not None:
                    stmt_buf.append(line)
                    if line.rstrip().endswith(';'):
                        text = '\n'.join(stmt_buf)
                        stmt_buf = None
                        absorb(text)
                    continue
                if plain.startswith('CREATE TABLE'):
                    match = CREATE_HEAD_RE.match(plain)
                    if not match:
                        raise DumpParseError(
                            'CREATE TABLE 语句头无法识别：{0!r}'.format(plain[:120]))
                    create_head = match.group(1)
                    create_buf = [plain]
                    if plain.rstrip().endswith(';'):
                        table_columns[create_head] = parse_create_columns(create_buf)
                        create_head, create_buf = None, []
                    continue
                if plain.startswith('INSERT') or plain.startswith('REPLACE'):
                    stmt_buf = [line]
                    if line.rstrip().endswith(';'):
                        text = '\n'.join(stmt_buf)
                        stmt_buf = None
                        absorb(text)
                    continue
            # 走到这里说明文件已读完，残留的半截语句意味着 dump 被截断。
            if stmt_buf is not None:
                raise DumpParseError(
                    'dump 在最后一条 INSERT 处被截断（该语句没有以分号结束，已解析 {0} 条语句）'.format(
                        statements))
            if create_head is not None:
                raise DumpParseError(
                    'dump 在 CREATE TABLE {0} 处被截断（缺少收尾的分号）'.format(create_head))
    except UnicodeDecodeError as exc:
        # 非 UTF-8 字节不是 MigrationError，不转换会以裸 traceback + 退出码 1 收场，
        # 与"校验未通过"无法区分。
        raise DumpParseError('dump 不是合法的 UTF-8 文本：{0}'.format(exc)) from exc
    finally:
        for handle in handles.values():
            handle.close()
    return tables


def replay_spool(table: SpoolTable) -> Iterator[List[Optional[str]]]:
    with table.path.open('r', encoding='utf-8', newline='\n') as handle:
        for number, line in enumerate(handle, 1):
            if not line.strip():
                continue
            try:
                yield json.loads(line)
            except json.JSONDecodeError as exc:
                # 中间文件损坏时必须报错：半截数据静默参与迁移同样校验不出来。
                raise DumpParseError('{0} 第 {1} 行无法解析：{2}'.format(
                    table.path.name, number, exc)) from exc

# ======================================================================================
# 值规范化
# ======================================================================================

INT_TYPES = {'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'}
REAL_TYPES = {'decimal', 'numeric', 'float', 'double', 'real'}
TEXT_TYPES = {'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'}

BASE_TYPE_RE = re.compile(r'^([a-z]+)')


def base_type(ctype: str) -> str:
    match = BASE_TYPE_RE.match(ctype or '')
    return match.group(1) if match else ''


def _decimal_text(value: Any) -> str:
    """把数值折叠成不带指数、不带多余尾零的十进制文本，便于两侧直接比较。"""
    try:
        with localcontext() as ctx:
            # Decimal 默认 prec=28，29 位的整数串在 quantize 时会抛 InvalidOperation。
            # 这里放宽到 60 位，足以覆盖 bigint + 小数位与 28 位流水号。
            ctx.prec = 60
            number = Decimal(str(value)) if not isinstance(value, Decimal) else value
            if number == number.to_integral_value():
                if number == 0:
                    # '-0' 与 '0' 数值相等，统一成 '0' 避免假不一致。
                    return '0'
                return str(number.quantize(Decimal(1)))
            text = format(number.normalize(), 'f')
    except (InvalidOperation, ValueError, ArithmeticError):
        return str(value)
    if '.' in text:
        text = text.rstrip('0').rstrip('.')
    return text


def canon(value: Any) -> Any:
    """把两侧的值折叠成可直接比较的形式。"""
    if value is None:
        return None
    if isinstance(value, bool):
        return '1' if value else '0'
    if isinstance(value, (int, Decimal)):
        return _decimal_text(value)
    if isinstance(value, float):
        return _decimal_text(Decimal(repr(value)))
    if isinstance(value, datetime):
        if value.microsecond:
            return value.strftime('%Y-%m-%d %H:%M:%S.%f')
        return value.strftime('%Y-%m-%d %H:%M:%S')
    if isinstance(value, date):
        return value.strftime('%Y-%m-%d')
    if isinstance(value, timedelta):
        total = int(value.total_seconds())
        sign = '-' if total < 0 else ''
        total = abs(total)
        return '{0}{1:02d}:{2:02d}:{3:02d}'.format(sign, total // 3600, (total % 3600) // 60, total % 60)
    if isinstance(value, (bytes, bytearray)):
        return bytes(value).decode('utf-8', 'replace')
    if isinstance(value, str):
        stripped = value.strip()
        if re.match(r'^-?\d+(\.\d+)?$', stripped):
            return _decimal_text(stripped)
        # 注释承诺"折叠成可直接比较的形式"，返回未 strip 的原串会让 CHAR 列假报不一致。
        return stripped
    return str(value)


ZERO_DATE_RE = re.compile(r'^0000-00-00')
HEX_LITERAL_RE = re.compile(r'^0x[0-9a-fA-F]*$')


def normalize_value(raw: Optional[str], ctype: str, *, column: str = '') -> Any:
    """把 dump 的文本字面量转换成驱动可直接绑定的 Python 值。"""
    if raw is None:
        return None
    kind = base_type(ctype)
    if raw == '':
        # 空串只在字符类与 JSON 类列上保留原义；数值/时间列的空串按 NULL 处理。
        if kind in TEXT_TYPES or kind in ('json', 'enum', 'set'):
            return ''
        return None
    if kind in INT_TYPES:
        try:
            number = Decimal(raw)
        except (InvalidOperation, ValueError):
            raise MigrationError('列 {0} 期望整数，实际 {1!r}'.format(column, raw[:40]))
        # int(Decimal('1.9')) 会静默截断成 1，产生错误数据且重跑不会修正，必须显式报错。
        if number != number.to_integral_value():
            raise MigrationError('列 {0} 期望整数，实际 {1!r}（含小数部分）'.format(column, raw[:40]))
        return int(number)
    if kind in REAL_TYPES:
        try:
            return Decimal(raw)
        except InvalidOperation:
            raise MigrationError('列 {0} 期望数值，实际 {1!r}'.format(column, raw[:40]))
    if kind == 'bit':
        text = raw
        if text.lower().startswith("b'") and text.endswith("'"):
            return int(text[2:-1] or '0', 2)
        try:
            return int(text)
        except ValueError:
            return 1 if text.lower() in ('true', 'on') else 0
    if kind in ('blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary') \
            and HEX_LITERAL_RE.match(raw):
        # mysqldump --hex-blob 产出的 0x4142 形态；按文本原样写入会把 "0x4142" 当成内容。
        return bytes.fromhex(raw[2:])
    if kind in ('date', 'datetime', 'timestamp') and ZERO_DATE_RE.match(raw):
        # 严格模式下零日期会被拒绝，按 NULL 处理并在报告中计数。
        return None
    return raw


# ======================================================================================
# 迁移方案（表级映射）
# ======================================================================================

@dataclass
class MapRule:
    column: str
    source: Optional[str] = None
    source_index: Optional[int] = None
    const: Any = None
    is_const: bool = False


@dataclass
class TargetSpec:
    target: str
    sources: List[str]
    rules: List[MapRule]
    note: str = ''
    row_filter: Optional[Callable[[Dict[str, Any]], bool]] = None
    row_builder: Optional[Callable[[Dict[str, Any]], Dict[str, Any]]] = None
    skipped: List[str] = field(default_factory=list)

    def insert_columns(self) -> List[str]:
        return [rule.column for rule in self.rules]


@dataclass
class PlanReport:
    migrated: List[str] = field(default_factory=list)
    skipped: Dict[str, str] = field(default_factory=dict)
    dropped_columns: Dict[str, List[str]] = field(default_factory=dict)
    filled_columns: Dict[str, List[str]] = field(default_factory=dict)
    blockers: List[str] = field(default_factory=list)
    notes: List[str] = field(default_factory=list)
    # --tables 里写了但没匹配到任何表的表名：不报出来会变成"空跑一遍并显示通过"。
    unmatched_only: List[str] = field(default_factory=list)


def identity_spec(meta: Dict[str, TableMeta], target: str, source: str,
                  source_columns: Sequence[str]) -> TargetSpec:
    """同名同义列直通：只写源与目标都存在的非生成列。"""
    item = meta[target]
    rules: List[MapRule] = []
    for index, column in enumerate(source_columns):
        if column not in item.types:
            continue
        if column in item.generated:
            continue
        rules.append(MapRule(column=column, source=column, source_index=index))
    return TargetSpec(target=target, sources=[source], rules=rules)


def check_target_required(meta: Dict[str, TableMeta], target: str,
                          source_cols: Sequence[str], blockers: List[str]) -> List[str]:
    """目标表里源没有、又必须给值的列：提前判定，避免运行到一半才炸。"""
    item = meta[target]
    source_set = set(source_cols)
    problems: List[str] = []
    for column in item.columns:
        if column in source_set or column in item.generated:
            continue
        if column in item.auto_increment:
            continue
        if item.nullable[column] or item.has_default[column]:
            continue
        if column in item.pk and column in item.auto_increment:
            continue
        problems.append(
            '{0}.{1}（{2}，NOT NULL 且无默认值）在源中不存在，必须显式补值'.format(
                target, column, item.types[column]))
    blockers.extend(problems)
    return problems


def describe_schema_gap(meta: Dict[str, TableMeta], target: str,
                        source_cols: Sequence[str]) -> Tuple[List[str], List[str]]:
    """返回 (被丢弃的源列, 被补齐的目标列)。"""
    item = meta[target]
    target_set = set(item.columns)
    dropped = [c for c in source_cols if c not in target_set]
    filled: List[str] = []
    for column in item.columns:
        if column in source_cols:
            continue
        if column in item.generated:
            filled.append(column + '（STORED 生成列，由库自动计算）')
            continue
        if column in item.auto_increment:
            filled.append(column + '（自增列，由库分配）')
            continue
        if item.nullable[column]:
            filled.append(column + '（可空，保持 NULL）')
        elif item.has_default[column]:
            filled.append(column + '（NOT NULL，使用表默认值）')
        else:
            # 原先 elif 链走到这里会整条消失：NOT NULL 且无默认值、源里也没有的列
            # 既不算丢弃也不算补齐，报告上看不出它会阻断迁移。
            filled.append(column + '（NOT NULL 且无默认值，源中无来源，会被阻断）')
    return dropped, filled


def build_plan(meta: Dict[str, TableMeta], dump_tables: Dict[str, SpoolTable], *,
               merge_operation_logs: bool,
               exclude: Sequence[str],
               only: Optional[Sequence[str]] = None) -> Tuple[List[TargetSpec], PlanReport]:
    report = PlanReport()
    specs: List[TargetSpec] = []
    excluded = set(exclude)
    only_set = set(only) if only else None

    matched_only: set = set()

    def consider(name: str) -> bool:
        if only_set is None:
            return True
        if name in only_set:
            matched_only.add(name)
            return True
        return False

    # 1) 旧库独有：product_groups 拆进三张分组表
    product_cols = dump_tables['product_groups'].columns if 'product_groups' in dump_tables else []
    if 'product_groups' in dump_tables and consider('product_groups'):
        covered = _covered_group_levels(dump_tables, report)
        specs.extend(product_group_specs(meta, product_cols, report, covered_levels=covered))

    # 2) 旧库独有：operation_logs 可选并入 activity_logs
    if 'operation_logs' in dump_tables and consider('operation_logs'):
        if merge_operation_logs:
            specs.append(operation_log_spec(dump_tables['operation_logs'].columns, report))
        else:
            report.skipped['operation_logs'] = (
                '旧库独有的操作日志表，新库无同名表；如需并入 activity_logs 请显式加 --merge-operation-logs')

    if 'agent_applications' in dump_tables and consider('agent_applications'):
        if dump_tables['agent_applications'].rows == 0:
            report.skipped['agent_applications'] = '旧库独有的表，且 dump 中 0 行，无可迁移数据'
        else:
            report.skipped['agent_applications'] = '旧库独有的表，新库无对应结构'

    # 3) 同名直通表
    for source in sorted(dump_tables):
        if source in ('product_groups', 'operation_logs', 'agent_applications'):
            continue
        if not consider(source):
            continue
        if source in excluded:
            report.skipped[source] = DEFAULT_EXCLUDED.get(source, '按 --exclude 排除')
            continue
        if source not in meta:
            # 整张表静默消失是三条"报告写通过"的丢数据路径之一：必须进 blockers。
            report.skipped[source] = '新库无同名表，且未定义映射规则（该表数据不会被迁移）'
            report.blockers.append(
                '{0}：源表在新库没有同名表，也未定义映射规则，{1} 行将不会被迁移'.format(
                    source, dump_tables[source].rows))
            continue
        cols = dump_tables[source].columns
        spec = identity_spec(meta, source, source, cols)
        if not spec.rules:
            report.skipped[source] = '源列与新库列无交集（该表数据不会被迁移）'
            report.blockers.append(
                '{0}：源列与新库列无交集，{1} 行将不会被迁移'.format(source, dump_tables[source].rows))
            continue
        check_target_required(meta, source, cols, report.blockers)
        dropped, filled = describe_schema_gap(meta, source, cols)
        if dropped:
            report.dropped_columns[source] = dropped
        if filled:
            report.filled_columns[source] = filled
        specs.append(spec)
        report.migrated.append(source)

    for name in meta:
        if name in dump_tables:
            continue
        report.notes.append('{0}：新库独有表，dump 无数据，保持现状'.format(name))

    if only_set is not None:
        # 表名写错时原先会产出 0 个 spec，跑完后报告全零却打印"校验通过"。
        known = set(dump_tables) | {'product_groups', 'operation_logs', 'agent_applications'}
        report.unmatched_only = sorted(only_set - matched_only)
        for name in report.unmatched_only:
            hint = '（可能想写 {0}）'.format(
                '、'.join(sorted(k for k in known if name and (name in k or k in name)))) if any(
                name and (name in k or k in name) for k in known) else ''
            report.blockers.append(
                '--tables 指定的 {0} 没有匹配到任何源表{1}'.format(name, hint))

    return specs, report


GROUP_LEVEL_TARGETS = {1: 'first_product_groups', 2: 'second_product_groups',
                       3: 'third_product_groups'}


def _covered_group_levels(dump_tables: Dict[str, SpoolTable], report: PlanReport) -> set:
    """dump 里已有同名分组表且 id 完整覆盖 product_groups 该层级时，标记该层级已被覆盖。

    dump 同时导出了 first/second/third_product_groups，其 id 与 product_groups 分层后
    的 id 一一对应（实测 first 6/6、second 15/15、third 22/22 完全一致，third_product_groups
    还多出 3 行）。此时代码若再按 level 拆一遍，同一行会被写两次；改为只走同名直通表。
    """
    table = dump_tables.get('product_groups')
    if table is None or 'level' not in table.columns or 'id' not in table.columns:
        return set()
    level_at = table.columns.index('level')
    id_at = table.columns.index('id')
    by_level: Dict[str, set] = {}
    for row in replay_spool(table):
        by_level.setdefault(str(row[level_at]), set()).add(row[id_at])
    covered = set()
    for level, target in GROUP_LEVEL_TARGETS.items():
        peer = dump_tables.get(target)
        if peer is None or peer.rows == 0 or 'id' not in peer.columns:
            continue
        source_ids = by_level.get(str(level), set())
        if not source_ids:
            continue
        if source_ids <= _id_set(peer, 'id'):
            covered.add(level)
            report.notes.append(
                '{0}：dump 已导出 {1} 且 id 完整覆盖 product_groups.level={2}，'
                '该层级改走同名表直通，不再重复拆分'.format(target, target, level))
    return covered


def _id_set(table: SpoolTable, column: str = 'id') -> set:
    """读取一张 spool 表某列的全部取值（仅用于小表，如 product_groups）。"""
    if column not in table.columns:
        return set()
    index = table.columns.index(column)
    values = set()
    for row in replay_spool(table):
        values.add(row[index])
    return values


def product_group_specs(meta: Dict[str, TableMeta], cols: Sequence[str],
                        report: PlanReport,
                        covered_levels: Optional[set] = None) -> List[TargetSpec]:
    """product_groups.level/parent_id 拆分到 first/second/third_product_groups。

    covered_levels 中的层级表示 dump 里已有同名的分组表并且 id 集合完整覆盖
    product_groups 该层级的全部 id，此时不再重复拆分，避免同一行被写两次。
    """
    covered = covered_levels or set()
    index = {c: i for i, c in enumerate(cols)}
    specs: List[TargetSpec] = []

    def spec_for(target: str, level: int, parent_col: Optional[str]) -> Optional[TargetSpec]:
        if level in covered:
            return None
        item = meta[target]
        rules: List[MapRule] = []

        def add(column: str, source: Optional[str] = None) -> None:
            if column not in item.types or column in item.generated:
                return
            if source is None:
                rules.append(MapRule(column=column, const=None, is_const=True))
                return
            rules.append(MapRule(column=column, source=source, source_index=index[source]))

        add('id', 'id')
        if 'code' in item.types:
            add('code', 'code')
        if 'product_type' in item.types:
            add('product_type', 'product_type')
        add('name', 'name')
        add('slug', 'slug')
        add('description', 'description')
        if 'icon' in item.types:
            add('icon', 'icon')
        if 'banner_image' in item.types:
            add('banner_image', 'banner_image')
        if parent_col:
            add(parent_col, 'parent_id')
        add('sort_order', 'sort_order')
        add('is_visible', 'is_visible')
        if 'is_system' in item.types:
            add('is_system', 'is_system')
        add('created_at', 'created_at')
        add('updated_at', 'updated_at')
        return TargetSpec(
            target=target,
            sources=['product_groups'],
            rules=rules,
            note='源自 product_groups.level={0}'.format(level),
            row_filter=lambda row, lv=level: str(row.get('level')) == str(lv),
        )

    first = spec_for('first_product_groups', 1, None)
    second = spec_for('second_product_groups', 2, 'first_product_group_id')
    third = spec_for('third_product_groups', 3, 'second_product_group_id')
    produced = [spec for spec in (first, second, third) if spec]
    for spec in produced:
        specs.append(spec)
    if produced:
        report.migrated.append('product_groups -> {0}'.format(
            '/'.join(spec.target for spec in produced)))
        report.dropped_columns['product_groups'] = [
            'level（用于分流目标表，不再落库）',
            'parent_id（first 表无父级，second/third 分别映射为 first_product_group_id / second_product_group_id）',
            'legacy_product_type', 'legacy_product_group_id',
        ]
        report.filled_columns['product_groups'] = [
            'second_product_groups.code/product_type/icon：新表无这些列',
            'third_product_groups.code/product_type/icon/banner_image：新表无这些列',
        ]
    return specs


OPERATION_LOG_STREAM = 'operation'
OPERATION_LOG_EVENT_SALT = 'operation_logs:'


def operation_log_spec(cols: Sequence[str], report: PlanReport) -> TargetSpec:
    """operation_logs -> activity_logs（可选，默认关闭）。

    该映射需要为缺失列补占位值，属于语义假设，故必须显式开启，并在说明中列明。
    """
    index = {c: i for i, c in enumerate(cols)}
    missing = [c for c in ('id', 'action', 'module', 'created_at') if c not in index]
    if missing:
        raise MigrationError('operation_logs 缺少必要列：{0}'.format(','.join(missing)))
    rules = [
        MapRule(column='event_id', const=None, is_const=True),
        MapRule(column='stream', const=OPERATION_LOG_STREAM, is_const=True),
        MapRule(column='actor_type', const=None, is_const=True),
        MapRule(column='actor_id', source='user_id', source_index=index.get('user_id')),
        MapRule(column='actor_name', const='', is_const=True),
        MapRule(column='module', source='module', source_index=index['module']),
        MapRule(column='action', source='action', source_index=index['action']),
        MapRule(column='description', const='', is_const=True),
        MapRule(column='subject_id', source='subject_id', source_index=index.get('subject_id')),
        MapRule(column='context', source='context', source_index=index.get('context')),
        MapRule(column='ip_address', source='ip_address', source_index=index.get('ip_address')),
        MapRule(column='created_at', source='created_at', source_index=index['created_at']),
        MapRule(column='updated_at', source='created_at', source_index=index['created_at']),
    ]

    def builder(row: Dict[str, Any]) -> Dict[str, Any]:
        raw_id = row.get('id')
        values: Dict[str, Any] = {}
        for rule in rules:
            if rule.column == 'event_id':
                values[rule.column] = hashlib.sha1(
                    (OPERATION_LOG_EVENT_SALT + str(raw_id)).encode('utf-8')).hexdigest()
            elif rule.column == 'actor_type':
                user_type = (row.get('user_type') or '').strip().lower()
                values[rule.column] = 'admin' if user_type in ('admin', 'administrator') else 'user'
            elif rule.is_const:
                values[rule.column] = rule.const
            else:
                values[rule.column] = row.get(rule.source)
        return values

    report.notes.append(
        'operation_logs -> activity_logs（已开启）：event_id 由 sha1("operation_logs:<id>") 生成以保证幂等；'
        'stream 固定 "operation"；description 旧库无来源，填空串；updated_at 取 created_at；'
        'actor_type 由 user_type 映射（admin/administrator -> admin，其余 -> user）。以上为占位约定，非业务语义还原。')
    return TargetSpec(target='activity_logs', sources=['operation_logs'], rules=rules,
                      note='operation_logs 并入 activity_logs', row_builder=builder,
                      key_column='event_id')


# ======================================================================================
# 迁移执行
# ======================================================================================

@dataclass
class TableStats:
    target: str
    source: str
    planned: int = 0
    sent: int = 0
    failed: int = 0
    batches: int = 0  # 批次尝试数：成功与失败都计一次
    before_count: Optional[int] = None
    after_count: Optional[int] = None
    expected_after: Optional[int] = None
    missing_keys: int = 0
    zero_date_to_null: int = 0
    default_filled: int = 0
    overflow_handled: int = 0
    fk_deferred: int = 0
    fk_resolved: int = 0
    fk_unresolved: int = 0
    fk_unresolved_keys: List[Dict[str, Any]] = field(default_factory=list)
    unique_conflict: int = 0
    unique_conflict_keys: List[Dict[str, Any]] = field(default_factory=list)
    source_total: int = 0
    row_filtered: bool = False
    source_row_mismatch: str = ''
    errors: List[Dict[str, Any]] = field(default_factory=list)
    samples: int = 0
    sample_mismatch: List[str] = field(default_factory=list)
    note: str = ''

    def to_dict(self) -> Dict[str, Any]:
        return {
            'target': self.target,
            'source': self.source,
            'source_rows': self.planned,
            'sent_rows': self.sent,
            'failed_rows': self.failed,
            'batches': self.batches,
            'before_count': self.before_count,
            'after_count': self.after_count,
            'expected_after': self.expected_after,
            'missing_keys': self.missing_keys,
            'zero_date_to_null': self.zero_date_to_null,
            'default_filled': self.default_filled,
            'overflow_handled': self.overflow_handled,
            'fk_deferred': self.fk_deferred,
            'fk_resolved': self.fk_resolved,
            'fk_unresolved': self.fk_unresolved,
            'fk_unresolved_keys': self.fk_unresolved_keys[:200],
            'unique_conflict': self.unique_conflict,
            'unique_conflict_keys': self.unique_conflict_keys[:200],
            'unique_conflict_truncated': max(0, self.unique_conflict - 200),
            'source_total': self.source_total,
            'row_filtered': self.row_filtered,
            'source_row_mismatch': self.source_row_mismatch,
            'samples': self.samples,
            'sample_mismatch': self.sample_mismatch,
            'errors': self.errors[:50],
            'errors_truncated': max(0, self.failed - 50),
            'fk_unresolved_truncated': max(0, self.fk_unresolved - 200),
            'note': self.note,
        }


class _DefaultMark:
    """占位标记：该列在 dump 中为空但目标列 NOT NULL，改用列默认值。"""

    __slots__ = ()

    def __repr__(self) -> str:  # pragma: no cover
        return 'DEFAULT'


DEFAULT_MARK = _DefaultMark()


class Migrator:
    def __init__(self, conn, meta: Dict[str, TableMeta], *,
                 fks: Optional[Sequence[ForeignKey]] = None,
                 batch_rows: int = 500, batch_bytes: int = 2 * 1024 * 1024,
                 conflict: str = 'overwrite', sample_size: int = 5,
                 on_overflow: str = 'fail', fk_rounds: int = 20,
                 unique_guard_max_rows: int = 50000,
                 dry_run: bool = False, verify_only: bool = False,
                 verbose: bool = True) -> None:
        self.conn = conn
        self.meta = meta
        self.batch_rows = max(1, batch_rows)
        self.batch_bytes = max(65536, batch_bytes)
        self.conflict = conflict
        self.sample_size = max(0, sample_size)
        self.on_overflow = on_overflow
        self.dry_run = dry_run
        self.verify_only = verify_only
        self.verbose = verbose
        self.fk_rounds = max(1, fk_rounds)
        # 唯一键撞键检测需要把目标表唯一键全量读进内存，行数很大时退化为不检测。
        self.unique_guard_max_rows = max(0, unique_guard_max_rows)
        self.stats: List[TableStats] = []
        # 全部按 _spec_key 索引：--merge-operation-logs 会为同一个 target 产出两个 spec
        # （operation_logs 与 activity_logs 都写 activity_logs），按 target 索引会互相覆盖。
        self.stats_by_key: Dict[Tuple[str, str], TableStats] = {}
        self.specs: List[TargetSpec] = []
        self.tables: Dict[str, SpoolTable] = {}
        self.pending: Dict[Tuple[str, str], List[List[Any]]] = {}
        self.before_keys: Dict[Tuple[str, str], Set[Tuple]] = {}
        self.dump_keys: Dict[Tuple[str, str], Set[Tuple]] = {}
        self.insert_positions: Dict[Tuple[str, str], List[str]] = {}
        self.unique_guards: Dict[Tuple[str, str], List[Dict[str, Any]]] = {}
        self.fk_updates: List[Tuple[Tuple[str, str], TableMeta, List[Any], Dict[str, Any]]] = []
        self.parent_cache: Dict[Tuple[str, str], Set[Any]] = {}
        self.fks_by_table: Dict[str, List[ForeignKey]] = {}
        for fk in fks or []:
            self.fks_by_table.setdefault(fk.table, []).append(fk)

    # -- 小工具 -------------------------------------------------------------------
    @staticmethod
    def _spec_key(spec: TargetSpec) -> Tuple[str, str]:
        """状态索引键：目标表 + 首个源表。

        --merge-operation-logs 下 operation_logs 与 activity_logs 两个 spec 的 target 相同，
        只按 target 索引会让后者覆盖前者的 pending/dump_keys/列顺序，导致补写列错位、
        缺失主键检测失效，甚至按错误的主键位置回填外键。
        """
        return (spec.target, spec.sources[0] if spec.sources else '')

    def log(self, message: str) -> None:
        if self.verbose:
            print(message, flush=True)

    def _row_locator(self, spec: TargetSpec, row: Sequence[Any]) -> Dict[str, Any]:
        """按主键列给出一行的定位信息，只保留 id / *_no / email 之类的标识列。"""
        columns = self.insert_positions.get(self._spec_key(spec), [])
        locator: Dict[str, Any] = {}
        for index, column in enumerate(columns):
            if index >= len(row):
                break
            if column == 'id' or column.endswith('_no') or column in ('email', 'code', 'uuid', 'username'):
                value = row[index]
                locator[column] = None if value is DEFAULT_MARK else value
        return locator

    def _drop_fk_updates(self, key: Tuple[str, str], count: int) -> None:
        """丢弃该 spec 最近登记的 count 条回填任务。

        提交失败后这批行并没有落库，若保留它们的外键回填任务，_restore_fks 会去 UPDATE
        一个不存在的行，命不中却按成功计数。
        """
        if count <= 0:
            return
        dropped = 0
        kept: List[Tuple[Tuple[str, str], TableMeta, List[Any], Dict[str, Any]]] = []
        for entry in reversed(self.fk_updates):
            if dropped < count and entry[0] == key:
                dropped += 1
                continue
            kept.append(entry)
        kept.reverse()
        self.fk_updates = kept

    def _count(self, table: str) -> int:
        with self.conn.cursor() as cur:
            cur.execute('SELECT COUNT(*) FROM {0}'.format(qi(table)))
            return int(cur.fetchone()[0])

    def _pk_set(self, table: str, pk: Sequence[str]) -> Set[Tuple]:
        if not pk:
            return set()
        rows: Set[Tuple] = set()
        with self.conn.cursor() as cur:
            cur.execute('SELECT {0} FROM {1}'.format(qt(pk), qi(table)))
            for row in cur.fetchall():
                rows.add(tuple(canon(v) for v in row))
        return rows

    def _sql_parts(self, spec: TargetSpec, item: TableMeta) -> Tuple[str, str]:
        """返回 (VALUES 之前的语句头, ON DUPLICATE 之后的语句尾)。"""
        cols = spec.insert_columns()
        head = 'INSERT INTO {0} ({1}) VALUES '.format(qi(spec.target), qt(cols))
        pk = set(item.pk)
        # keep-newer 需要两侧都有 updated_at 才有比较基准，否则退化为 overwrite。
        newer = self.conflict == 'keep-newer' and 'updated_at' in item.types and 'updated_at' not in pk
        updates: List[str] = []
        for column in cols:
            if column in pk:
                continue
            if newer:
                updates.append(
                    '{0}=IF(VALUES(updated_at) >= updated_at, VALUES({0}), {0})'.format(qi(column)))
            else:
                updates.append('{0}=VALUES({0})'.format(qi(column)))
        if not updates:
            # 表里只有主键列：命中冲突时不改任何列，保持幂等。
            updates.append('{0}={0}'.format(qi(cols[0])))
        tail = ' ON DUPLICATE KEY UPDATE ' + ', '.join(updates)
        return head, tail

    @staticmethod
    def _render_rows(rows: Sequence[Sequence[Any]]) -> Tuple[str, List[Any]]:
        """把一批行渲染成 VALUES 片段；DEFAULT_MARK 直接写 DEFAULT 关键字。"""
        groups: List[str] = []
        params: List[Any] = []
        for row in rows:
            marks: List[str] = []
            for value in row:
                if value is DEFAULT_MARK:
                    marks.append('DEFAULT')
                else:
                    marks.append('%s')
                    params.append(value)
            groups.append('(' + ','.join(marks) + ')')
        return ','.join(groups), params

    # -- 主流程 -------------------------------------------------------------------
    def run(self, specs: Sequence[TargetSpec],
            dump_tables: Dict[str, SpoolTable]) -> List[TableStats]:
        for spec in specs:
            if spec.target not in self.meta:
                raise MigrationError('新库缺少目标表 {0}'.format(spec.target))
        self.specs = list(specs)
        self.tables = dump_tables
        self.stats = []
        self.stats_by_key = {}
        for spec in self.specs:
            stat = self.migrate_spec(spec, dump_tables)
            self.stats.append(stat)
            self.stats_by_key[self._spec_key(spec)] = stat
        if not (self.dry_run or self.verify_only):
            # 外键指向的父行可能属于后写入的表（表间双向依赖、表内自引用），
            # 这些行先挂起，等所有表都写过一轮再统一补写，避免为了顺序去关闭外键检查。
            self._backfill_pending()
        for spec, stat in zip(self.specs, self.stats):
            self.finish_spec(spec, stat)
        return self.stats

    def migrate_spec(self, spec: TargetSpec, dump_tables: Dict[str, SpoolTable]) -> TableStats:
        item = self.meta[spec.target]
        stat = TableStats(target=spec.target, source=','.join(spec.sources), note=spec.note)
        if len(spec.sources) != 1:
            raise MigrationError('暂不支持多源合并：{0}'.format(spec.sources))
        table = dump_tables.get(spec.sources[0])
        if table is None:
            stat.note = (stat.note + ' 源表在 dump 中缺失，跳过').strip()
            return stat
        source_cols = table.columns
        # 源行数取自 spool 的原始 tuple 计数，不经过值转换与 dump_keys，
        # 用来发现"解析/过滤阶段就丢了行"这种校验自证不了的情况。
        stat.source_total = table.rows
        stat.row_filtered = spec.row_filter is not None
        typed = [item.types.get(rule.column, '') for rule in spec.rules]

        if self.dry_run:
            # 只做解析与值转换体检，不写库，但仍报告将被丢弃的行。
            for raw_row in replay_spool(table):
                if spec.row_filter is not None and not spec.row_filter(dict(zip(source_cols, raw_row))):
                    continue
                stat.planned += 1
                try:
                    self._build_values(spec, raw_row, typed, source_cols, item, stat)
                except MigrationError as exc:
                    stat.failed += 1
                    if len(stat.errors) < 20:
                        stat.errors.append({'row': raw_row[:3], 'error': str(exc)})
            stat.note = (stat.note + ' [dry-run：未写库]').strip()
            self.log('  {0:<34} 源 {1:>7} 待写入 {2:>7} 转换失败 {3:>4}'.format(
                spec.target, stat.planned, stat.planned - stat.failed, stat.failed))
            return stat

        stat.before_count = self._count(spec.target)
        before_keys = self._pk_set(spec.target, item.pk)
        head, tail = self._sql_parts(spec, item)
        self.insert_positions[self._spec_key(spec)] = spec.insert_columns()
        self.unique_guards[self._spec_key(spec)] = self._unique_guards(
            spec, item, stat.before_count or 0)

        def after_flush(written: List[List[Any]]) -> None:
            self._check_unique(spec, item, stat, written)

        buffer: List[List[Any]] = []
        buffer_bytes = 0
        dump_keys: Set[Tuple] = set()
        pending: List[List[Any]] = []
        cursor = self.conn.cursor()
        try:
            for raw_row in replay_spool(table):
                if spec.row_filter is not None and not spec.row_filter(dict(zip(source_cols, raw_row))):
                    continue
                stat.planned += 1
                try:
                    values = self._build_values(spec, raw_row, typed, source_cols, item, stat)
                except MigrationError as exc:
                    stat.failed += 1
                    if len(stat.errors) < 50:
                        # 只记定位列：整行会带上 users/user_accounts 的密码哈希等凭据。
                        locator = {c: raw_row[i] for i, c in enumerate(source_cols)
                                   if i < len(raw_row) and (
                                       c == 'id' or c.endswith('_no')
                                       or c in ('email', 'code', 'uuid', 'username'))}
                        stat.errors.append({'row': locator, 'error': str(exc)})
                    continue
                self._record_dump_key(spec, item, values, dump_keys)
                if self.verify_only:
                    continue
                buffer.append(values)
                # 按 UTF-8 字节计：按字符计会让中文/JSON 大列的实际批次放大 3~4 倍。
                buffer_bytes += sum(len(str(v).encode('utf-8'))
                                    for v in values if v is not DEFAULT_MARK and v is not None) + 8
                if len(buffer) >= self.batch_rows or buffer_bytes >= self.batch_bytes:
                    self._flush(cursor, spec, stat, head, tail, buffer, pending, after_flush)
                    buffer, buffer_bytes = [], 0
            if buffer and not self.verify_only:
                self._flush(cursor, spec, stat, head, tail, buffer, pending, after_flush)
        finally:
            cursor.close()

        if not stat.row_filtered and stat.source_total and stat.planned != stat.source_total:
            stat.source_row_mismatch = '源表 {0} 共 {1} 行，进入迁移 {2} 行，差 {3} 行'.format(
                spec.sources[0], stat.source_total, stat.planned,
                stat.source_total - stat.planned)
        key = self._spec_key(spec)
        self.before_keys[key] = before_keys
        self.dump_keys[key] = dump_keys
        if pending:
            self.pending[key] = pending
        self.log('  {0:<34} 源 {1:>7} 写入 {2:>7} 失败 {3:>4} 待补外键 {4:>4}'.format(
            spec.target, stat.planned, stat.sent, stat.failed, len(pending)))
        return stat

    def finish_spec(self, spec: TargetSpec, stat: TableStats) -> None:
        """补写阶段结束后的最终统计与抽样核对。"""
        item = self.meta[spec.target]
        key = self._spec_key(spec)
        dump_keys = self.dump_keys.get(key)
        if dump_keys is None:
            return
        stat.after_count = self._count(spec.target)
        after_keys = self._pk_set(spec.target, item.pk)
        stat.missing_keys = len(dump_keys - after_keys) if item.pk else 0
        before_keys = self.before_keys.get(key) or set()
        stat.expected_after = len(before_keys | dump_keys) if item.pk else stat.after_count
        # 源表名不等于目标表名：product_groups 会拆进 first/second/third_product_groups，
        # 用 target 当键取 self.tables 会 KeyError，且崩在数据已提交之后。
        source_table = self.tables.get(spec.sources[0]) if spec.sources else None
        if source_table is not None:
            # verify-only 也必须做值比对：只比主键存在性发现不了"值写错了"。
            self._sample_check(spec, item, source_table, stat)
        if self.verify_only:
            stat.note = (stat.note + ' [只校验：未写库，外键回填未执行]').strip()
        self.log('  {0:<34} 源 {1:>7} 写入 {2:>7} 失败 {3:>4} | 表内 {4} -> {5} (期望 {6}, 缺失主键 {7})'.format(
            spec.target, stat.planned, stat.sent, stat.failed,
            stat.before_count, stat.after_count, stat.expected_after, stat.missing_keys))

    def _build_values(self, spec: TargetSpec, raw_row: Sequence[Optional[str]],
                      typed: Sequence[str], source_cols: Sequence[str],
                      item: TableMeta, stat: TableStats) -> List[Any]:
        values: List[Any] = []
        built = spec.row_builder(dict(zip(source_cols, raw_row))) if spec.row_builder is not None else None
        for rule, ctype in zip(spec.rules, typed):
            if rule.is_const:
                raw: Any = rule.const
            elif built is not None:
                raw = built.get(rule.column)
            else:
                index = rule.source_index
                raw = raw_row[index] if index is not None and index < len(raw_row) else None
            values.append(self._coerce(raw, ctype, rule.column, item, stat))
        return values

    def _coerce(self, raw: Any, ctype: str, column: str, item: TableMeta,
                stat: TableStats) -> Any:
        """把 dump 里的字面量转换成可绑定的参数值，并处理空值/长度边界。"""
        value = self._normalize(raw, ctype, column, stat)
        if value is None:
            if item.nullable.get(column, True):
                return None
            if item.has_default.get(column) or column in item.auto_increment:
                # NOT NULL 又没有默认值时不能靠库兜底，必须让迁移失败而不是静默写错。
                stat.default_filled += 1
                return DEFAULT_MARK
            raise MigrationError(
                '{0}.{1} 为 NOT NULL 且无默认值，dump 值为空'.format(item.name, column))
        limit = self._char_limit(item, column)
        if limit is not None and isinstance(value, str) and len(value) > limit:
            if self.on_overflow == 'truncate':
                # 只有真正截断并写入的行才算"已处理"；fail 模式下该行会进 failed，
                # 在这里一起计数会让两个数字互相矛盾。
                stat.overflow_handled += 1
                return value[:limit]
            raise MigrationError('{0}.{1} 长度超限：{2} > {3}'.format(
                item.name, column, len(value), limit))
        return value

    @staticmethod
    def _char_limit(item: TableMeta, column: str) -> Optional[int]:
        ctype = item.types.get(column, '')
        if not ctype.startswith(('char(', 'varchar(')):
            return None
        try:
            return int(ctype[ctype.index('(') + 1:ctype.index(')')])
        except (ValueError, IndexError):
            return None

    @staticmethod
    def _normalize(raw: Any, ctype: str, column: str, stat: TableStats) -> Any:
        if raw is None or raw is DEFAULT_MARK:
            return None
        if not isinstance(raw, str):
            return raw
        if base_type(ctype) in ('date', 'datetime', 'timestamp') and ZERO_DATE_RE.match(raw):
            stat.zero_date_to_null += 1
            return None
        return normalize_value(raw, ctype, column=column)

    def _record_dump_key(self, spec: TargetSpec, item: TableMeta,
                         values: Sequence[Any], sink: Set[Tuple]) -> None:
        if not item.pk:
            return
        position = {c: i for i, c in enumerate(spec.insert_columns())}
        key: List[Any] = []
        for column in item.pk:
            if column not in position:
                return
            value = values[position[column]]
            if value is DEFAULT_MARK:
                return
            key.append(canon(value))
        sink.add(tuple(key))

    def _unique_guards(self, spec: TargetSpec, item: TableMeta,
                       table_rows: int) -> List[Dict[str, Any]]:
        """为目标表的每个"非主键唯一键"记录当前占用者，用于发现撞键改写。

        ON DUPLICATE KEY UPDATE 对非主键唯一键同样会命中：源行主键 p 与目标既有行 q
        的唯一键相同时，MySQL 更新的是 q 而不是插入 p —— 不报错、源行消失、q 被改写。
        这里提前把"唯一键值 -> 持有该值的主键集合"读出来，写批完成后逐行比对。
        """
        columns = spec.insert_columns()
        position = {c: i for i, c in enumerate(columns)}
        pk_set = set(item.pk)
        if table_rows > self.unique_guard_max_rows:
            self.log('    i {0} 行数 {1} 超过唯一键守卫上限，跳过撞键检测'.format(
                spec.target, table_rows))
            return []
        guards: List[Dict[str, Any]] = []
        with self.conn.cursor() as cur:
            for key in item.unique_keys:
                if not key or set(key) <= pk_set:
                    continue
                if not all(c in position for c in key):
                    continue
                cur.execute('SELECT {0},{1} FROM {2}'.format(qt(key), qt(item.pk), qi(spec.target)))
                owners: Dict[Tuple, Set[Tuple]] = {}
                for row in cur.fetchall():
                    value = tuple(canon(v) for v in row[:len(key)])
                    owners.setdefault(value, set()).add(
                        tuple(canon(v) for v in row[len(key):]))
                guards.append({
                    'columns': list(key),
                    'position': [position[c] for c in key],
                    'owners': owners,
                    'reported': set(),
                })
        return guards

    def _check_unique(self, spec: TargetSpec, item: TableMeta, stat: TableStats,
                      rows: Sequence[Sequence[Any]]) -> None:
        guards = self.unique_guards.get(self._spec_key(spec)) or []
        if not guards:
            return
        position = {c: i for i, c in enumerate(self.insert_positions.get(self._spec_key(spec), []))}
        if any(c not in position for c in item.pk):
            return
        pk_positions = [position[c] for c in item.pk]
        for guard in guards:
            for row in rows:
                try:
                    value = tuple(canon(row[i]) for i in guard['position'])
                    owner = tuple(canon(row[i]) for i in pk_positions)
                except Exception:
                    continue
                # MySQL/InnoDB 的唯一索引把 NULL 视为互不相等（复合键只要含 NULL 就允许重复），
                # 含 NULL 的行不可能撞键，参与比对只会产生假告警。
                if any(row[i] is None or row[i] is DEFAULT_MARK for i in guard['position']):
                    continue
                holders = guard['owners'].get(value)
                if holders and owner not in holders:
                    if value not in guard['reported']:
                        guard['reported'].add(value)
                        stat.unique_conflict += 1
                        if len(stat.unique_conflict_keys) < 200:
                            stat.unique_conflict_keys.append({
                                'unique_key': guard['columns'],
                                'value': [None if v is None else str(v) for v in value],
                                'source_pk': dict(zip(item.pk, [row[i] for i in pk_positions])),
                                'existing_pk': [list(h) for h in sorted(holders)][:5],
                            })
                guard['owners'].setdefault(value, set()).add(owner)

    def _write_chunk(self, cursor, stat: TableStats, head: str, tail: str,
                     rows: Sequence[Sequence[Any]]) -> bool:
        """把一批行当作一个事务写入；失败返回 False 交回调用方逐行定位。"""
        values_sql, params = self._render_rows(rows)
        for attempt in range(1, 4):
            try:
                cursor.execute('START TRANSACTION')
                cursor.execute(head + values_sql + tail, params)
                self.conn.commit()
                stat.batches += 1
                return True
            except Exception as exc:  # 整批失败：回滚后逐行定位，避免一批脏数据连带失败
                try:
                    self.conn.rollback()
                except Exception:
                    pass
                if is_deadlock(exc) and attempt < 3:
                    # 死锁/锁等待超时是可重试的整批失败，重试前不必逐行定位。
                    self.log('    批次死锁，第 {0} 次重试（{1} 行）'.format(attempt, len(rows)))
                    time.sleep(0.5 * attempt)
                    continue
                stat.batches += 1
                self.log('    批次回滚（{0} 行）：{1}'.format(len(rows), str(exc)[:160]))
                return False
        return False

    def _flush(self, cursor, spec: TargetSpec, stat: TableStats, head: str, tail: str,
               rows: List[List[Any]], pending: Optional[List[List[Any]]] = None,
               after_flush: Optional[Callable[[List[List[Any]]], None]] = None) -> None:
        if not rows:
            return
        if self._write_chunk(cursor, stat, head, tail, rows):
            stat.sent += len(rows)
            if after_flush is not None:
                after_flush(rows)
            return
        self._retry_rows(cursor, spec, stat, head, tail, rows, pending, after_flush)

    def _retry_rows(self, cursor, spec: TargetSpec, stat: TableStats, head: str, tail: str,
                    rows: Sequence[Sequence[Any]],
                    sink: Optional[List[List[Any]]] = None,
                    after_flush: Optional[Callable[[List[List[Any]]], None]] = None) -> None:
        """整批失败后逐行定位。

        用 SAVEPOINT 在同一个事务里逐行试写，比“每行一个事务”少一次提交开销；
        外键父行尚未写入的行不当作失败，转投 sink 等后续补写轮次。
        """
        if not rows:
            return
        position = {c: i for i, c in enumerate(spec.insert_columns())}
        try:
            cursor.execute('START TRANSACTION')
        except Exception:
            pass
        written: List[List[Any]] = []
        for row in rows:
            values_sql, params = self._render_rows([row])
            try:
                cursor.execute('SAVEPOINT idc_retry_row')
            except Exception:
                pass
            try:
                cursor.execute(head + values_sql + tail, params)
                stat.sent += 1
                written.append(list(row))
            except Exception as exc:
                try:
                    cursor.execute('ROLLBACK TO SAVEPOINT idc_retry_row')
                except Exception:
                    pass
                if sink is not None and is_fk_error(exc):
                    # 只是父行还没到，记进 sink 等补写轮次，不算失败。
                    sink.append(list(row))
                    continue
                stat.failed += 1
                if len(stat.errors) < 50:
                    locator = {c: row[i] for c, i in position.items()
                               if c == 'id' or c.endswith('_no') or c in ('email', 'code', 'uuid', 'username')}
                    stat.errors.append({'row': locator, 'error': str(exc)[:300]})
        try:
            self.conn.commit()
        except Exception:
            try:
                self.conn.rollback()
            except Exception:
                pass
            raise MigrationError('{0} 逐行重试提交失败'.format(spec.target))
        if after_flush is not None and written:
            after_flush(written)

    # -- 外键延迟写 ---------------------------------------------------------------
    def _parent_values(self, fk: ForeignKey) -> Set[Any]:
        """父表被引用列当前已有的取值；按轮次失效重取，避免拿旧快照判活。"""
        key = (fk.ref_table, fk.ref_columns[0])
        cached = self.parent_cache.get(key)
        if cached is None:
            column = fk.ref_columns[0]
            values: Set[Any] = set()
            with self.conn.cursor() as cur:
                cur.execute('SELECT {0} FROM {1}'.format(qi(column), qi(fk.ref_table)))
                for row in cur.fetchall():
                    values.add(canon(row[0]))
            cached = values
            self.parent_cache[key] = cached
        return cached

    def _relax_fks(self, spec: TargetSpec, item: TableMeta,
                   values: Sequence[Any]) -> Tuple[List[Any], Dict[str, Any]]:
        """把“父行还不存在”的可空外键列临时置空，等全部表写完后统一回填。

        只在父行确实缺失时降级，父行已在库里的外键原样写入，不做无谓改写。
        """
        fks = self.fks_by_table.get(spec.target)
        if not fks:
            return list(values), {}
        position = {c: i for i, c in enumerate(spec.insert_columns())}
        relaxed = list(values)
        pending: Dict[str, Any] = {}
        for fk in fks:
            if len(fk.columns) != 1:
                continue
            column = fk.columns[0]
            index = position.get(column)
            if index is None or not item.nullable.get(column):
                continue
            value = relaxed[index]
            if value is None or value is DEFAULT_MARK:
                continue
            if canon(value) in self._parent_values(fk):
                continue
            relaxed[index] = None
            pending[column] = value
        return relaxed, pending

    def _insert_relaxed(self, cursor, spec: TargetSpec, item: TableMeta, stat: TableStats,
                        row: Sequence[Any]) -> bool:
        """按“可空外键暂置空”的方式写入一行，并把外键原值登记成回填任务。"""
        relaxed, pending = self._relax_fks(spec, item, row)
        head, tail = self._sql_parts(spec, item)
        values_sql, params = self._render_rows([relaxed])
        try:
            cursor.execute('SAVEPOINT idc_relax_row')
        except Exception:
            pass
        try:
            cursor.execute(head + values_sql + tail, params)
        except Exception as exc:
            try:
                cursor.execute('ROLLBACK TO SAVEPOINT idc_relax_row')
            except Exception:
                pass
            # 这里不计 failed：调用方会把这行放回 pending 等下一轮，最终写不进去时
            # 在 _backfill_pending 收尾统一计账，中途计数会让重试成功的行留下假失败。
            if len(stat.errors) < 50:
                stat.errors.append({
                    'row': self._row_locator(spec, relaxed),
                    'error': '外键列置空后仍写入失败：{0}'.format(str(exc)[:300])})
            return False
        stat.sent += 1
        if pending:
            stat.fk_deferred += 1
            self.fk_updates.append((self._spec_key(spec), item, relaxed, pending))
        return True

    def _restore_fks(self) -> None:
        """把临时置空的外键列按主键回填成 dump 里的原值。"""
        if not self.fk_updates:
            return
        # 只对真正要回填的表检查主键：全局检查会在数据已写完之后抛错终止，
        # 外键列留在 NULL 且没有逐行登记。
        no_pk = sorted({key[0] for key, _item, _row, _pending in self.fk_updates
                        if not self.meta[key[0]].pk})
        if no_pk:
            for key, _item, _row, pending in self.fk_updates:
                if key[0] in no_pk:
                    stat = self.stats_by_key.get(key)
                    if stat is not None:
                        stat.fk_unresolved += len(pending)
                        if len(stat.fk_unresolved_keys) < 200:
                            stat.fk_unresolved_keys.append({
                                'pk': {},
                                'column': '、'.join(sorted(pending)),
                                'value': '、'.join(sorted(str(v) for v in pending.values())),
                                'reason': '目标表缺少主键，无法回填'})
            self.log('  ! {0} 缺少主键，无法回填外键，已登记为待回填'.format('、'.join(no_pk)))
            self.fk_updates = [e for e in self.fk_updates if e[0][0] not in no_pk]
            if not self.fk_updates:
                return
        grouped: Dict[Tuple[str, str], List[Tuple[Any, Any]]] = {}
        skipped_pk = 0
        for key, item, row, pending in self.fk_updates:
            target = key[0]
            position = {c: i for i, c in enumerate(self.insert_positions.get(key, []))}
            if not all(c in position for c in item.pk):
                skipped_pk += len(pending)
                continue
            pk_values = [row[position[c]] for c in item.pk]
            # 主键取自增默认值时无法定位行，写成 WHERE id=NULL 会命中 0 行却计成功。
            if any(v is None or v is DEFAULT_MARK for v in pk_values):
                skipped_pk += len(pending)
                stat = self.stats_by_key.get(key)
                if stat is not None:
                    stat.fk_unresolved += len(pending)
                continue
            for column, value in pending.items():
                grouped.setdefault((target, column), []).append((pk_values, value))
        restored = 0
        for (target, column), entries in sorted(grouped.items()):
            item = self.meta[target]
            # 同一 target 可能有两个 spec，按 (target, column) 分组后统一取第一个匹配的 stat。
            stat = next((s for k, s in self.stats_by_key.items() if k[0] == target), None)
            where = ' AND '.join('{0}=%s'.format(qi(c)) for c in item.pk)
            sql = 'UPDATE {0} SET {1}=%s WHERE {2}'.format(qi(target), qi(column), where)
            for start in range(0, len(entries), self.batch_rows):
                chunk = entries[start:start + self.batch_rows]
                params = [[value] + list(pk) for pk, value in chunk]
                written = 0
                try:
                    cursor = self.conn.cursor()
                    try:
                        cursor.executemany(sql, params)
                        written = cursor.rowcount if cursor.rowcount is not None else -1
                        self.conn.commit()
                    finally:
                        cursor.close()
                    if written < 0:
                        # 驱动不支持 rowcount 时退回逐行统计，避免把未命中当成成功。
                        written = 0
                        self._restore_fks_rowwise(target, item, column, where, chunk, stat)
                        continue
                    if written < len(chunk):
                        # 有主键没命中：多半是行已被删除或被别的值替换，必须登记而不是算成功。
                        self.log('    外键回填只命中 {0}/{1} 行（{2}.{3}），转入逐行核对'.format(
                            written, len(chunk), target, column))
                        missing = max(0, len(chunk) - written)
                        if stat is not None:
                            stat.fk_unresolved += missing
                        if stat is not None and len(stat.fk_unresolved_keys) < 200:
                            for pk, value in chunk:
                                if len(stat.fk_unresolved_keys) >= 200:
                                    break
                                stat.fk_unresolved_keys.append({
                                    'pk': dict(zip(item.pk, pk)),
                                    'column': column,
                                    'value': value,
                                    'reason': '回填未命中目标行'})
                    restored += written
                    if stat is not None:
                        stat.fk_resolved += written
                except Exception as exc:
                    try:
                        self.conn.rollback()
                    except Exception:
                        pass
                    self.log('    外键回填失败 {0}.{1}（{2} 行）：{3}'.format(
                        target, column, len(chunk), str(exc)[:160]))
                    self._restore_fks_rowwise(target, item, column, where, chunk, stat)
        if skipped_pk:
            self.log('    外键回填跳过 {0} 个值（主键不可用，已登记为待回填）'.format(skipped_pk))
        self.log('  外键回填完成：{0} 个值已按主键写回'.format(restored))
        self.fk_updates = []

    def _restore_fks_rowwise(self, target: str, item: TableMeta, column: str, where: str,
                             rows: Sequence[Tuple[Any, Any]], stat: Optional[TableStats]) -> None:
        sql = 'UPDATE {0} SET {1}=%s WHERE {2}'.format(qi(target), qi(column), where)
        for pk, value in rows:
            try:
                cursor = self.conn.cursor()
                try:
                    cursor.execute(sql, [value] + list(pk))
                    self.conn.commit()
                finally:
                    cursor.close()
                if stat is not None:
                    stat.fk_resolved += 1
            except Exception as exc:
                try:
                    self.conn.rollback()
                except Exception:
                    pass
                if stat is not None:
                    if is_fk_error(exc):
                        # 行本身已写入，只是父行不在目标库：按 onDelete SET NULL 语义保留 NULL 并登记待回填。
                        stat.fk_unresolved += 1
                        if len(stat.fk_unresolved_keys) < 200:
                            stat.fk_unresolved_keys.append({
                                'pk': dict(zip(item.pk, pk)),
                                'column': column,
                                'value': value})
                    else:
                        stat.failed += 1
                        if len(stat.errors) < 50:
                            stat.errors.append({
                                'row': dict(zip(item.pk, pk)),
                                'error': '外键 {0} 回填失败：{1}'.format(column, str(exc)[:240])})

    def _backfill_pending(self) -> None:
        """分轮补写因外键父行未就绪而延迟的行，必要时临时置空可空外键再回填。"""
        leftover_total = sum(len(rows) for rows in self.pending.values())
        if leftover_total:
            self.log('  -- 外键补写：{0} 行因父行未就绪而延迟，开始分轮补写（上限 {1} 轮）--'.format(
                leftover_total, self.fk_rounds))
            for round_no in range(1, self.fk_rounds + 1):
                self.parent_cache.clear()
                resolved = 0
                for spec in self.specs:
                    key = self._spec_key(spec)
                    rows = self.pending.get(key)
                    if not rows:
                        continue
                    item = self.meta[spec.target]
                    stat = self.stats_by_key[key]
                    head, tail = self._sql_parts(spec, item)
                    # 上一张表的补写可能刚好补齐了这张表的父行，这里的父值快照必须重取。
                    self.parent_cache.clear()
                    leftover: List[List[Any]] = []
                    cursor = self.conn.cursor()
                    try:
                        for start in range(0, len(rows), self.batch_rows):
                            chunk = rows[start:start + self.batch_rows]
                            if self._write_chunk(cursor, stat, head, tail, chunk):
                                stat.sent += len(chunk)
                                stat.fk_resolved += len(chunk)
                                resolved += len(chunk)
                                continue
                            try:
                                cursor.execute('START TRANSACTION')
                            except Exception:
                                pass
                            for row in chunk:
                                if self._insert_relaxed(cursor, spec, item, stat, row):
                                    resolved += 1
                                else:
                                    leftover.append(list(row))
                            try:
                                self.conn.commit()
                            except Exception:
                                try:
                                    self.conn.rollback()
                                except Exception:
                                    pass
                                leftover.extend(list(r) for r in chunk)
                                # 已提交失败意味着这批行没有落库，之前为它们登记的
                                # 外键回填任务必须一并作废，否则会回填到并不存在的行。
                                self._drop_fk_updates(key, len(chunk))
                    finally:
                        cursor.close()
                    if leftover:
                        self.pending[key] = leftover
                    else:
                        self.pending.pop(key, None)
                remaining = sum(len(rows) for rows in self.pending.values())
                self.log('    第 {0} 轮补写：本轮写入 {1} 行，剩余 {2} 行'.format(
                    round_no, resolved, remaining))
                if not remaining or not resolved:
                    break
        self._restore_fks()
        for spec in self.specs:
            key = self._spec_key(spec)
            rows = self.pending.get(key)
            if not rows:
                continue
            stat = self.stats_by_key[key]
            stat.failed += len(rows)
            if len(stat.errors) < 50:
                stat.errors.append({
                    'rows': len(rows),
                    'keys': [self._row_locator(spec, row) for row in rows[:20]],
                    'error': '外键父行在 {0} 轮补写后仍未出现，这些行未写入'.format(self.fk_rounds)})
            self.log('  ! {0} 仍有 {1} 行无法写入：外键父行在数据中不存在'.format(spec.target, len(rows)))


    # -- 校验 ---------------------------------------------------------------------
    @staticmethod
    def _intentional_nulls(stat: TableStats, pk_columns: Sequence[str]) -> Dict[str, Set[Tuple]]:
        """按策略置 NULL 的 (列 → 主键集合)：外键父行在目标库不存在，行保留、列留空。"""
        result: Dict[str, Set[Tuple]] = {}
        for entry in stat.fk_unresolved_keys:
            column = entry.get('column')
            pk = entry.get('pk') or {}
            if not column or any(name not in pk for name in pk_columns):
                continue
            key = tuple(canon(pk[name]) for name in pk_columns)
            result.setdefault(column, set()).add(key)
        return result

    def _fk_parent_values(self, spec: TargetSpec, item: TableMeta,
                          wanted: Sequence[str]) -> Dict[str, Set[Any]]:
        """可空单列外键 → 父表现有取值集合（规范化后）。

        源值不在这集合里时，脚本按策略把该列写成 NULL（见 _relax_fks）。这是既定行为，
        不是脏数据，抽样比对必须跳过它，否则 --verify-only 与写入模式会给出不同结论
        （只校验模式不会真的产生 fk_unresolved 明细）。
        """
        result: Dict[str, Set[Any]] = {}
        for fk in self.fks_by_table.get(spec.target, ()):
            if len(fk.columns) != 1 or len(fk.ref_columns) != 1:
                continue
            column = fk.columns[0]
            if column in wanted and item.nullable.get(column):
                result[column] = self._parent_values(fk)
        return result

    def _sample_check(self, spec: TargetSpec, item: TableMeta,
                      table: SpoolTable, stat: TableStats) -> None:
        """随机抽样若干行，逐列比对目标库实际值；用于发现“写进去了但值不对”。"""
        # 抽样比对的是“源值 vs 目标现值”，与本次是否真的插入了行无关；
        # 若用 stat.sent 判定，重跑时（全部命中 ON DUPLICATE）会整表跳过核对。
        if self.sample_size <= 0 or stat.planned == 0 or not item.pk:
            return
        position = {c: i for i, c in enumerate(spec.insert_columns())}
        if any(c not in position for c in item.pk):
            return
        # 原先只取前 6 个非主键列，宽表（invoices 40 列、users 36 列）的长文本与后段
        # 金额/时间列永远抽不到；这里改为全部参与写入的非主键列。
        wanted = [r.column for r in spec.rules if r.column not in item.pk and r.column in item.types]
        if not wanted:
            return
        typed = [item.types.get(rule.column, '') for rule in spec.rules]
        # 抽样会把同一批源行再转换一遍。若累计到真实 stat，default_filled /
        # zero_date_to_null / overflow_handled 会被重复计一次（抽样行被算两遍），
        # 报告数字虚高。抽样只是读，一律记到独立的 scratch 上丢弃。
        scratch = TableStats(target=spec.target, source='')
        reservoir: List[List[Any]] = []
        seen = 0
        for raw_row in replay_spool(table):
            if spec.row_filter is not None and not spec.row_filter(dict(zip(table.columns, raw_row))):
                continue
            try:
                values = self._build_values(spec, raw_row, typed, table.columns, item, scratch)
            except MigrationError:
                # 这类行本来就没写进库（已在 failed 里计过），不参与抽样。
                continue
            seen += 1
            if len(reservoir) < self.sample_size:
                reservoir.append(values)
            else:
                at = random.randrange(seen)
                if at < self.sample_size:
                    reservoir[at] = values
        pk_positions = [position[c] for c in item.pk]
        # 外键父行缺失时按策略置 NULL 的行：源值与目标 NULL 的差异是既定结论，不算抽样不一致。
        intentional = self._intentional_nulls(stat, item.pk)
        fk_parents = self._fk_parent_values(spec, item, wanted)
        select_cols = qt(list(item.pk) + wanted)
        where = ' AND '.join('{0}=%s'.format(qi(c)) for c in item.pk)
        for values in reservoir:
            pk_values = [values[i] for i in pk_positions]
            with self.conn.cursor() as cur:
                cur.execute('SELECT {0} FROM {1} WHERE {2}'.format(select_cols, qi(spec.target), where),
                            pk_values)
                row = cur.fetchone()
            stat.samples += 1
            if row is None:
                stat.sample_mismatch.append('主键 {0} 未在目标表找到'.format(pk_values))
                continue
            offset = len(item.pk)
            for i, column in enumerate(wanted):
                source_value = values[position[column]]
                if source_value is DEFAULT_MARK:
                    # DEFAULT_MARK 渲染成 DEFAULT 关键字，目标侧拿到的是表默认值，
                    # 用 canon('DEFAULT') 比较只会产生假不一致。
                    continue
                expected = canon(source_value)
                actual = canon(row[offset + i])
                if expected == actual:
                    continue
                if actual is None and expected is not None:
                    if tuple(canon(v) for v in pk_values) in intentional.get(column, ()):
                        # 已登记的“外键父行缺失、列置 NULL”，由 fk_unresolved 单独报告，不重复计为脏数据。
                        continue
                    parents = fk_parents.get(column)
                    if parents is not None and expected not in parents:
                        # 只校验模式（或明细被截断时）没有 fk_unresolved 可查，这里按同一策略判定：
                        # 可空外键的父行本该不存在 → 列写 NULL 是既定结果。
                        continue
                stat.sample_mismatch.append('{0}={1} 列 {2} 源 {3!r} 目标 {4!r}'.format(
                    item.pk[0], pk_values[0], column, expected, actual))


def print_plan_report(report: PlanReport) -> None:
    print('=' * 78)
    print('一、表级处理结论')
    print('=' * 78)
    for name in report.migrated:
        print('  迁移  {0}'.format(name))
    if report.skipped:
        print('  --- 未迁移 ---')
        for name, reason in sorted(report.skipped.items()):
            print('  跳过  {0}：{1}'.format(name, reason))
    if report.dropped_columns:
        print('  --- 旧字段在新结构无对应位置（数据丢弃）---')
        for name, cols in sorted(report.dropped_columns.items()):
            print('  {0}: {1}'.format(name, '、'.join(cols)))
    if report.filled_columns:
        print('  --- 新字段在旧库无来源（补默认值）---')
        for name, cols in sorted(report.filled_columns.items()):
            print('  {0}: {1}'.format(name, '、'.join(cols)))
    if report.notes:
        print('  --- 说明 ---')
        for note in report.notes:
            print('  * {0}'.format(note))
    if report.blockers:
        print('  --- 阻断项 ---')
        for blocker in report.blockers:
            print('  ! {0}'.format(blocker))


# ======================================================================================
# 结构差异报告
# ======================================================================================

def describe_structure(meta: Dict[str, TableMeta], dump_tables: Dict[str, SpoolTable],
                       fks: Sequence[ForeignKey], out_path: Path) -> Dict[str, Any]:
    """对比旧 dump 与新库的表/字段差异，落盘 JSON 并返回同样内容。"""
    dump_names = set(dump_tables)
    target_names = set(meta)
    only_dump = sorted(dump_names - target_names)
    only_target = sorted(target_names - dump_names)
    common = sorted(dump_names & target_names)
    columns: Dict[str, Any] = {}
    for name in common:
        source_cols = dump_tables[name].columns
        item = meta[name]
        dropped, filled = describe_schema_gap(meta, name, source_cols)
        source_only = [c for c in source_cols if c not in item.types]
        target_only = [c for c in item.columns if c not in source_cols]
        columns[name] = {
            'source_columns': source_cols,
            'target_columns': item.columns,
            'dropped_source_columns': source_only,
            'filled_target_columns': target_only,
            'generated_target_columns': sorted(item.generated),
            'notes': {'dropped': dropped, 'filled': filled},
        }
    report = {
        'dump_tables': len(dump_tables),
        'target_tables': len(meta),
        'only_in_dump': only_dump,
        'only_in_target': only_target,
        'common': len(common),
        'foreign_key_count': len(fks),
        'columns': columns,
        'row_counts': {name: dump_tables[name].rows for name in sorted(dump_tables)},
        'unique_keys': {name: meta[name].unique_keys for name in sorted(meta)},
        'primary_keys': {name: meta[name].pk for name in sorted(meta)},
    }
    out_path.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    return report


# ======================================================================================
# 命令行
# ======================================================================================

def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog='migrate_idc_dump_upsert.py',
        description='把旧版本 MySQL dump 的数据迁移到新版本数据库（只做 DML，不修改表结构）',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=(
            '示例：\n'
            '  python backend/scripts/migrate_idc_dump_upsert.py --describe-structure\n'
            '  python backend/scripts/migrate_idc_dump_upsert.py --dry-run\n'
            '  python backend/scripts/migrate_idc_dump_upsert.py\n'
            '  python backend/scripts/migrate_idc_dump_upsert.py --verify-only\n'))
    parser.add_argument('--dump', default=str(DEFAULT_DUMP), help='旧库 dump 路径（默认仓库根目录的 idc dump）')
    parser.add_argument('--env-file', default=str(DEFAULT_ENV), help='读取数据库配置的 .env 路径')
    parser.add_argument('--database', default=None,
                        help='目标库名；正式迁移必须显式指定（结构比对/试运行/只校验可省略，省略时取 .env 的 DB_DATABASE）')
    parser.add_argument('--allow-production', action='store_true',
                        help='允许写入 .env 里 DB_DATABASE 指向的正式库（默认拒绝，防止误跑）')
    parser.add_argument('--batch-rows', type=int, default=500, help='每批行数上限，默认 500')
    parser.add_argument('--batch-bytes', type=int, default=2 * 1024 * 1024, help='每批字节上限，默认 2MB')
    parser.add_argument('--conflict', choices=('overwrite', 'keep-newer'), default='overwrite',
                        help='重复键冲突策略：overwrite 以 dump 为准；keep-newer 保留 updated_at 较新的一侧')
    parser.add_argument('--on-overflow', choices=('fail', 'truncate'), default='fail',
                        help='源值超过目标列长度时：fail 直接中止（默认），truncate 截断后写入并记警告')
    parser.add_argument('--fk-rounds', type=int, default=20,
                        help='外键父行未就绪的行最多补写几轮，默认 20')
    parser.add_argument('--tables', default=None, help='只迁移指定表，逗号分隔（如 users,orders）')
    parser.add_argument('--exclude', default=None, help='额外排除的表，逗号分隔')
    parser.add_argument('--merge-operation-logs', action='store_true',
                        help='把旧库 operation_logs 并入 activity_logs（默认不并入）')
    parser.add_argument('--sample-size', type=int, default=5, help='每表抽样核对条数，0 表示不抽样')
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--describe-structure', action='store_true', help='只输出新旧结构差异，不写库')
    modes.add_argument('--dry-run', action='store_true', help='解析 dump 并生成方案，不写库')
    modes.add_argument('--verify-only', action='store_true', help='只校验已迁移结果，不写数据')
    parser.add_argument('--report-dir', default=None, help='报告输出目录（默认系统临时目录）')
    parser.add_argument('--report-root', default=None,
                        help='未指定 --report-dir 时，在此目录下创建临时报告目录（默认系统临时目录）')
    parser.add_argument('--keep-spool', action='store_true', help='保留解析中间文件（排障用）')
    parser.add_argument('--quiet', action='store_true', help='只输出结论')
    parser.add_argument('--unique-guard-max-rows', type=int, default=50000,
                        help='唯一键撞键检测的目标表行数上限，超过则跳过检测，默认 50000')
    return parser


def validate_args(parser: argparse.ArgumentParser, args: argparse.Namespace) -> None:
    """参数范围校验：负批次、0 轮补写之类会在运行中途才炸，提前拒绝。"""
    checks = (
        ('--batch-rows', args.batch_rows, 1, 100000),
        ('--batch-bytes', args.batch_bytes, 65536, 512 * 1024 * 1024),
        ('--fk-rounds', args.fk_rounds, 1, 1000),
        ('--sample-size', args.sample_size, 0, 100000),
        ('--unique-guard-max-rows', args.unique_guard_max_rows, 0, 100000000),
    )
    for name, value, low, high in checks:
        if value is None or not (low <= value <= high):
            parser.error('{0} 需在 [{1}, {2}] 之间，当前 {3}'.format(name, low, high, value))


def split_names(raw: Optional[str]) -> List[str]:
    if not raw:
        return []
    return [item.strip() for item in raw.split(',') if item.strip()]


def order_specs(specs: Sequence[TargetSpec], fks: Sequence[ForeignKey]) -> List[TargetSpec]:
    targets = sorted({spec.target for spec in specs})
    ordered = topological_order(targets, fks)
    rank = {name: i for i, name in enumerate(ordered)}
    return sorted(specs, key=lambda spec: (rank.get(spec.target, 0), spec.target))


def summarize(stats: Sequence[TableStats], report: PlanReport) -> Dict[str, Any]:
    return {
        'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'tables': [stat.to_dict() for stat in stats],
        'unmatched_only': report.unmatched_only,
        'skipped': report.skipped,
        'dropped_columns': report.dropped_columns,
        'filled_columns': report.filled_columns,
        'blockers': report.blockers,
        'total_failed': sum(stat.failed for stat in stats),
        'total_missing_keys': sum(stat.missing_keys for stat in stats),
        'total_sample_mismatch': sum(len(stat.sample_mismatch) for stat in stats),
        'total_fk_unresolved': sum(stat.fk_unresolved for stat in stats),
        'total_unique_conflict': sum(stat.unique_conflict for stat in stats),
        'total_source_row_mismatch': sum(1 for stat in stats if stat.source_row_mismatch),
        'total_source_rows': sum(stat.source_total for stat in stats),
    }


def print_summary(summary: Dict[str, Any]) -> int:
    print('=' * 78)
    print('三、校验结论')
    print('=' * 78)
    print('  {0:<30}{1:>9}{2:>9}{3:>9}{4:>9}'.format('目标表', '源行数', '写入', '表内行数', '缺失键'))
    problems = 0
    for item in summary['tables']:
        after = item['after_count']
        before = item['before_count']
        print('  {0:<30}{1:>9}{2:>9}{3:>9}{4:>9}'.format(
            item['target'], item['source_rows'], item['sent_rows'],
            '-' if after is None else after, item['missing_keys']))
        if item['failed_rows']:
            problems += 1
            print('    ! 失败 {0} 行'.format(item['failed_rows']))
        if item['missing_keys']:
            problems += 1
            print('    ! 源主键未在目标表出现 {0} 个'.format(item['missing_keys']))
        if item.get('fk_deferred'):
            print('    i 外键父行延迟 {0} 行，已补写 {1} 行'.format(
                item['fk_deferred'], item.get('fk_resolved', 0)))
        if item.get('fk_unresolved'):
            print('    i 外键父行在目标库不存在 {0} 行，该外键列已置 NULL（行已写入，待补齐父行后回填）'.format(
                item['fk_unresolved']))
            for unresolved in item.get('fk_unresolved_keys', [])[:5]:
                print('      - {0}.{1}={2}'.format(
                    unresolved['pk'], unresolved['column'], unresolved['value']))
        if item['sample_mismatch']:
            problems += 1
            for mismatch in item['sample_mismatch'][:5]:
                print('    ! 抽样不一致：{0}'.format(mismatch))
        if item.get('unique_conflict'):
            problems += 1
            print('    ! 唯一键撞车 {0} 处：源行会改写既有行而不是插入（源行主键未落库）'.format(
                item['unique_conflict']))
            for conflict in item.get('unique_conflict_keys', [])[:5]:
                print('      - {0}={1} 源主键 {2} 已被 {3} 占用'.format(
                    '/'.join(conflict['unique_key']), conflict['value'],
                    conflict['source_pk'], conflict['existing_pk']))
        if item.get('source_row_mismatch'):
            problems += 1
            print('    ! {0}'.format(item['source_row_mismatch']))
        if before is not None and after is not None and item['expected_after'] is not None:
            if after != item['expected_after']:
                print('    i 表内行数 {0} 与期望 {1} 不同（源主键与既有主键有重叠时属正常）'.format(
                    after, item['expected_after']))
    print('-' * 78)
    unresolved = summary.get('total_fk_unresolved') or 0
    if unresolved:
        print('注意：{0} 个可空外键值因父行不在目标库而保留 NULL（行本身已写入，未丢失）。'
              '若涉及 finance_ledger_entries / finance_refunds，请先在目标库执行'
              ' php artisan finance:rebuild --execute 生成派生数据，再重跑本脚本回填。'.format(unresolved))
    warnings = (1 if unresolved else 0) + (1 if summary.get('total_unique_conflict') else 0) + (
        1 if summary.get('total_source_row_mismatch') else 0)
    if problems == 0:
        if warnings:
            print('结论：迁移校验通过（无失败行、无缺失主键、抽样一致），'
                  '但有 {0} 项已登记的待处理事项（见上）'.format(warnings))
        else:
            print('结论：迁移校验通过（无失败行、无缺失主键、抽样一致）')
        return 0
    print('结论：存在 {0} 处需要人工复核的问题，详见报告'.format(problems))
    return 1


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    validate_args(parser, args)
    spool_dir = Path(tempfile.mkdtemp(prefix='idc_spool_'))
    try:
        return _run(args, spool_dir)
    finally:
        # 每次全量 spool 约 235MB（74 万行），不清理会持续占用系统临时目录。
        if args.keep_spool:
            print('  spool 已保留：{0}'.format(spool_dir))
        else:
            shutil.rmtree(spool_dir, ignore_errors=True)


def _run(args: argparse.Namespace, spool_dir: Path) -> int:
    dump_path = Path(args.dump).expanduser().resolve()
    env_path = Path(args.env_file).expanduser().resolve()
    report_dir = Path(args.report_dir).expanduser().resolve() if args.report_dir else None
    if report_dir is None:
        # 未指定报告目录时也落在用户指定的 --report-root 之下（默认系统临时目录）。
        report_root = Path(args.report_root).expanduser().resolve() if args.report_root else None
        report_dir = Path(tempfile.mkdtemp(prefix='idc_migrate_', dir=str(report_root) if report_root else None))
    report_dir.mkdir(parents=True, exist_ok=True)
    verbose = not args.quiet

    # 安全闸门必须在解析 dump 之前：解析要跑 20~30 秒，先解析再拒绝等于每次误用
    # 都白等半分钟，也容易让人误判"命令已经在跑了"。
    writing = not (args.describe_structure or args.dry_run or args.verify_only)
    if writing and not args.database:
        # 不带 --database 时原先会静默落到 .env 的 DB_DATABASE（本机即正式库 idc），
        # 一条无参命令就能对生产库做全量 upsert，必须显式指定目标库。
        raise MigrationError(
            '正式迁移必须显式指定 --database；不带 --database 会落到 .env 的 DB_DATABASE（正式库）')
    config = load_db_config(env_path, args.database)
    env_database = load_db_config(env_path, None).database
    if writing and config.database == env_database and not args.allow_production:
        raise MigrationError(
            '目标库 {0} 与 .env 的 DB_DATABASE 相同（正式库），已拒绝执行；'
            '确认要写入正式库请追加 --allow-production'.format(config.database))

    print('=' * 78)
    print('旧库 dump -> 新库 数据迁移（只做 DML，不修改表结构）')
    print('=' * 78)
    print('  dump : {0}'.format(dump_path))
    print('  报告 : {0}'.format(report_dir))
    print('  模式 : {0}'.format(
        '结构比对' if args.describe_structure else
        '试运行' if args.dry_run else '只校验' if args.verify_only else '正式迁移'))

    started = time.time()
    dump_tables = parse_dump(dump_path, spool_dir, spool=True,
                             progress_every=200 if verbose else 0)
    print('  解析完成：{0} 张表，{1} 行，用时 {2:.1f}s'.format(
        len(dump_tables), sum(t.rows for t in dump_tables.values()), time.time() - started))

    params = {k: v for k, v in vars(args).items()
              if k not in ('env_file', 'database', 'dump')}
    (report_dir / 'run_args.json').write_text(
        json.dumps({'dump': str(dump_path), 'target': config.describe(), 'args': params},
                   ensure_ascii=False, indent=2), encoding='utf-8')

    conn = open_connection(config)
    try:
        meta = load_target_meta(conn)
        fks = load_foreign_keys(conn)
        print('  目标库：{0}，{1} 张表，{2} 个外键'.format(config.describe(), len(meta), len(fks)))

        if args.describe_structure:
            diff = describe_structure(meta, dump_tables, fks, report_dir / 'structure_diff.json')
            print('  仅旧库有：{0}'.format('、'.join(diff['only_in_dump']) or '（无）'))
            print('  仅新库有：{0}'.format('、'.join(diff['only_in_target']) or '（无）'))
            differing = [name for name, info in diff['columns'].items()
                         if info['dropped_source_columns'] or info['filled_target_columns']]
            print('  两侧都有但字段有差异：{0} 张表'.format(len(differing)))
            for name in differing:
                info = diff['columns'][name]
                print('    {0}: 丢弃 [{1}] 补齐 [{2}]'.format(
                    name, '、'.join(info['dropped_source_columns']) or '-',
                    '、'.join(info['filled_target_columns']) or '-'))
            print('  已写入：{0}'.format(report_dir / 'structure_diff.json'))
            # 结构比对是诊断模式：dump 里本来就有新库没有的旧表（product_groups 等），
            # 存在差异属正常，成功产出报告即视为成功。
            return 0

        exclude = list(DEFAULT_EXCLUDED) + split_names(args.exclude)
        only = split_names(args.tables) or None
        specs, plan = build_plan(meta, dump_tables, merge_operation_logs=args.merge_operation_logs,
                                 exclude=exclude, only=only)
        if not args.quiet:
            print_plan_report(plan)
        if plan.blockers:
            print('  阻断：存在需要人工确认的问题，请先明确映射后再执行')
            for blocker in plan.blockers:
                print('  ! {0}'.format(blocker))
            # 阻断时也要落一份报告，否则事后无从复查被拦下的原因。
            (report_dir / 'migration_report.json').write_text(
                json.dumps(summarize([], plan), ensure_ascii=False, indent=2), encoding='utf-8')
            print('  报告：{0}'.format(report_dir / 'migration_report.json'))
            return 2
        specs = order_specs(specs, fks)

        migrator = Migrator(conn, meta, fks=fks,
                            batch_rows=args.batch_rows, batch_bytes=args.batch_bytes,
                            conflict=args.conflict, sample_size=args.sample_size,
                            on_overflow=args.on_overflow, fk_rounds=args.fk_rounds,
                            unique_guard_max_rows=args.unique_guard_max_rows,
                            dry_run=args.dry_run,
                            verify_only=args.verify_only, verbose=verbose)
        stats = migrator.run(specs, dump_tables)
        summary = summarize(stats, plan)
        (report_dir / 'migration_report.json').write_text(
            json.dumps(summary, ensure_ascii=False, indent=2), encoding='utf-8')
        code = print_summary(summary)
        print('  报告：{0}'.format(report_dir / 'migration_report.json'))
        print('  总用时：{0:.1f}s'.format(time.time() - started))
        return code
    finally:
        try:
            conn.close()
        except Exception:
            pass


if __name__ == '__main__':
    try:
        sys.exit(main())
    except MigrationError as exc:
        print('迁移中止：{0}'.format(exc), file=sys.stderr)
        sys.exit(2)
    except KeyboardInterrupt:
        print('已中断（已提交的批次保持生效，可直接重跑续做）', file=sys.stderr)
        sys.exit(130)
    except Exception:  # noqa: BLE001
        # 未预期异常也要给出可分辨的退出码，而不是裸 traceback 混同于"校验未通过"。
        import traceback
        traceback.print_exc()
        print('迁移异常终止（未预期错误，已提交的批次保持生效）', file=sys.stderr)
        sys.exit(3)
