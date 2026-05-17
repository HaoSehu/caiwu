@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo START > _halo_phpunit.txt
call vendor\bin\phpunit.bat --no-coverage --filter "OrderZeroAmountPaymentFlowTest|OrderPaymentOrderBindingRegressionTest|OrderQuantityCheckoutFlowTest" >> _halo_phpunit.txt 2>&1
echo EXIT=%ERRORLEVEL% >> _halo_phpunit.txt
