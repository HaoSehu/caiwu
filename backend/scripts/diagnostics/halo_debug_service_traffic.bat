@echo off
setlocal
set "PHP_EXE=D:\BtSoft\php\83\php.exe"
if not exist "%PHP_EXE%" (
    echo php.exe not found at %PHP_EXE% 1>&2
    exit /b 1
)
"%PHP_EXE%" "%~dp0_halo_debug_service_traffic.php" %*
exit /b %errorlevel%
