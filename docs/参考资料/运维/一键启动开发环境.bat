@echo off
setlocal
title 开发环境一键启动
color 0A

rem ============================================================
rem  项目根目录（路径不同请改这里）
rem ============================================================
set "ROOT=e:\caiwu"

set "MODE=%~1"
if "%MODE%"=="" set "MODE=all"

if not exist "%ROOT%\package.json" (
    echo [错误] 找不到项目根目录：%ROOT%
    echo        请用记事本打开本脚本，修改 ROOT 变量后重试。
    echo.
    pause
    exit /b 1
)

if /i "%MODE%"=="help"    goto :usage
if /i "%MODE%"=="backend" goto :only_backend
if /i "%MODE%"=="admin"   goto :only_admin
if /i "%MODE%"=="www"     goto :only_www
if /i "%MODE%"=="console" goto :only_console
goto :all

:only_backend
call :start_backend
goto :done

:only_admin
call :start_admin
goto :done

:only_www
call :start_www
goto :done

:only_console
call :start_console
goto :done

:all
echo.
echo === 启动 后端 + 三个前端 ===
echo.
call :start_backend
call :start_admin
call :start_www
call :start_console
goto :done

:done
echo.
echo ==========================================================
echo   已发起启动，各服务在独立窗口运行，关掉窗口即停止服务
echo.
echo   后端 API     http://127.0.0.1:8000
echo   用户控制台   http://127.0.0.1:5173
echo   管理后台     http://127.0.0.1:5174
echo   官网         http://127.0.0.1:5175
echo ==========================================================
echo   端口被占用时会先结束占用进程，再重新启动
echo.
echo 本窗口 10 秒后自动关闭，按任意键立即关闭。
timeout /t 10 >nul
exit /b 0

rem ==================== 子程序 ====================

:start_backend
call :kill_port 8000
echo [启动] 后端  cd backend  ^>  php artisan app:serve  ^>  8000
start "后端 app:serve (8000)" /D "%ROOT%\backend" cmd /k "php artisan app:serve"
timeout /t 3 >nul
exit /b 0

:start_admin
call :kill_port 5174
echo [启动] 管理后台 admin-v3  ^>  5174
start "管理后台 admin-v3 (5174)" /D "%ROOT%" cmd /k "npm run dev:admin-v3"
timeout /t 3 >nul
exit /b 0

:start_www
call :kill_port 5175
echo [启动] 官网 user-v3-www  ^>  5175
start "官网 user-v3-www (5175)" /D "%ROOT%" cmd /k "npm run dev:user-v3-www"
timeout /t 3 >nul
exit /b 0

:start_console
call :kill_port 5173
echo [启动] 用户控制台 user-v4-console  ^>  5173
start "用户控制台 user-v4-console (5173)" /D "%ROOT%" cmd /k "npm run dev:user-v4-console"
timeout /t 3 >nul
exit /b 0

:kill_port
rem 用法：call :kill_port 端口号  ->  结束所有监听该端口的进程
set "KILLED=0"
for /f "tokens=5" %%P in ('netstat -ano -p tcp ^| findstr /c:":%~1 " ^| findstr /i "LISTENING"') do call :kill_pid %~1 %%P
if "%KILLED%"=="1" timeout /t 1 >nul
exit /b 0

:kill_pid
echo [清理] 端口 %1 被 PID %2 占用，正在结束该进程...
taskkill /F /PID %2 >nul 2>&1
if errorlevel 1 (
    echo [警告] 结束 PID %2 失败，可能权限不足或进程已退出，请手动处理
) else (
    set "KILLED=1"
    echo [完成] 已结束 PID %2
)
exit /b 0

:usage
echo.
echo 用法：直接双击本文件即可全部启动。
echo.
echo   一键启动开发环境.bat            后端 + 三个前端
echo   一键启动开发环境.bat backend    只启动后端 app:serve
echo   一键启动开发环境.bat admin      只启动管理后台 5174
echo   一键启动开发环境.bat www        只启动官网 5175
echo   一键启动开发环境.bat console    只启动用户控制台 5173
echo   一键启动开发环境.bat help       显示本帮助
echo.
pause
exit /b 0
