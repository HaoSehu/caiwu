@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo START > _seo_status.txt
call npm.cmd run build >> _seo_status.txt 2>&1
echo EXIT=%ERRORLEVEL% >> _seo_status.txt
