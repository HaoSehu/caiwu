#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "${BACKEND_DIR}"

echo "[export-schema] 开始导出 Laravel 基础 schema"
php artisan schema:dump --database=mysql
echo "[export-schema] 导出完成：${BACKEND_DIR}/database/schema/mysql-schema.sql"
