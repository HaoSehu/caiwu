@echo off
chcp 65001 > nul
php artisan migrate --force --no-ansi > _migrate_result.txt 2>&1
php _check_schema2.php > nul 2>&1
