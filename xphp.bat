@echo off
title Xampp PHP Switcher
setlocal EnableExtensions EnableDelayedExpansion

cd /D %~dp0

rem ---------------------------------------------
for /F "tokens=* USEBACKQ" %%v in (`where php`) do (
    if not exist "%%v" goto phpBinNotFound
)

set XPHP_APP_DIR=%~dp0
if not "%XPHP_APP_DIR:~-2%"==":\" set XPHP_APP_DIR=%XPHP_APP_DIR:~0,-1%

set XPHP_TMP_DIR=%XPHP_APP_DIR%\tmp
set XPHP_POWER_EXECUTOR=%XPHP_APP_DIR%\support\PowerExec.vbs
set XPHP_PHP_CONTROLLER=%XPHP_APP_DIR%\xphp.php

if not exist "%XPHP_TMP_DIR%" mkdir "%XPHP_TMP_DIR%"
goto startCommand

rem ---------------------------------------------
:phpBinNotFound
echo.
echo Cannot find PHP CLI.
echo Make sure you have add the path to your PHP directory into Windows Path Environment Variable.
call %~fx0:clearEnvVars
exit /B 1

rem ---------------------------------------------
:installationFailed
echo.
echo Installation Xampp PHP Switcher failed.
echo Please review the instructions carefully before installation.
del /Q "%XPHP_APP_DIR%\settings.ini"
echo.
pause>nul|set/p =Press any key to exit terminal...
call :clearEnvVars
exit 1

rem ---------------------------------------------
:missingArgs
echo.
echo Xampp PHP Switcher error: The "command" argument is missing.
echo.
goto help
call :clearEnvVars
exit /B 1

rem ---------------------------------------------
:clearEnvVars
set XPHP_APP_DIR=
set XPHP_TMP_DIR=
set XPHP_POWER_EXECUTOR=
set XPHP_PHP_CONTROLLER=
exit /B

rem ---------------------------------------------
:help
type %XPHP_APP_DIR%\xphp.hlp
call :clearEnvVars
exit /B

rem ---------------------------------------------
:install
FSUTIL dirty query %SystemDrive%>nul
if %errorLevel% NEQ 0 (
    echo.
    echo This process can only be run with elevated permission.
    pause>nul|set/p =Press any key to start this process in Administrator mode...
    echo.
    cscript //NoLogo "%XPHP_POWER_EXECUTOR%" -e -x -n "%~fx0" "install"
    if errorLevel 1 (
        echo The installation was canceled by user.
        exit /B 1
    ) else (
        echo The installation started in new window with Elevated permission.
        exit /B
    )
)

php -n -d output_buffering=0 "%XPHP_PHP_CONTROLLER%" "install"
if errorLevel 1 goto installationFailed

echo|set/p =Moving directory of the current PHP build into the repository...
for /F "tokens=* USEBACKQ" %%v in ("%XPHP_TMP_DIR%\.phpdir") do (set XPHP_PHP_DIR=%%v)
for /F "tokens=* USEBACKQ" %%v in ("%XPHP_TMP_DIR%\.storage_path") do (set XPHP_PHP_REPO=%%v)
move /Y "%XPHP_PHP_DIR%" "%XPHP_PHP_REPO%" >nul 2>&1
echo          Successful

echo|set/p =Creating symbolic link to the current PHP build in repository...
mklink /J "%XPHP_PHP_DIR%" "%XPHP_PHP_REPO%" >nul 2>&1
echo          Successful

del /Q "%XPHP_TMP_DIR%\."
set XPHP_PHP_DIR=
set XPHP_PHP_REPO=

echo.
echo -----------------------------------------------------------------------------------
echo XAMPP PHP SWITCHER WAS INSTALLED SUCCESSFULLY.
echo TO START USING IT, PLEASE EXIT YOUR TERMINAL TO
echo DELETE TEMPORARY PROCESS ENVIRONMENT VARIABLES.
echo.
pause>nul|set/p =Press any key to exit terminal...
call :clearEnvVars
exit

rem ---------------------------------------------
:addVersion
php -n -d output_buffering=0 %XPHP_PHP_CONTROLLER% "addVersion" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:removeVersion
php -n -d output_buffering=0 %XPHP_PHP_CONTROLLER% "removeVersion" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:listVersions
php -n -d output_buffering=0 %XPHP_PHP_CONTROLLER% "listVersions"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:showVersion
php -n -d output_buffering=0 %XPHP_PHP_CONTROLLER% "showVersion" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:switchVersion
php -n -d output_buffering=0 %XPHP_PHP_CONTROLLER% "switchVersion" "%~2"
call :clearEnvVars
exit /B %errorLevel%

rem ---------------------------------------------
:startCommand
cls
if "%~1"=="" goto missingArgs
if "%~1"=="help" goto help
if "%~1"=="install" goto install
if "%~1"=="add" goto addVersion
if "%~1"=="remove" goto removeVersion
if "%~1"=="list" goto listVersions
if "%~1"=="info" goto showVersion
if "%~1"=="switch" goto switchVersion

rem Call command with unknown param -------------
echo.
echo Xampp PHP Switcher error: "%~1" is invalid xphp command.
echo.
goto help

endlocal