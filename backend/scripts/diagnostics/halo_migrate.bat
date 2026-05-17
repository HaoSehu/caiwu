@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo START > _halo_migrate.txt
D:\BtSoft\php\83\php.exe artisan migrate --force >> _halo_migrate.txt 2>&1
echo EXIT=%ERRORLEVEL% >> _halo_migrate.txt
