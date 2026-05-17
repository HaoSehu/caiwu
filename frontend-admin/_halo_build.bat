@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo START > _halo_status.txt
call npm.cmd run build >> _halo_status.txt 2>&1
echo EXIT=%ERRORLEVEL% >> _halo_status.txt
