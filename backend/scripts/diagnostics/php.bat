@echo off
set "PHP_EXE=D:\BtSoft\php\83\php.exe"
if exist "%PHP_EXE%" (
    "%PHP_EXE%" %*
    exit /b %errorlevel%
)

php %*
