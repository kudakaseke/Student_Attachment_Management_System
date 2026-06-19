@echo off
setlocal
cd /d "%~dp0"
set "RESOLVED_PHP="
set "CANDIDATE="

if defined PHP_EXE (
  set "CANDIDATE=%PHP_EXE%"
  set "CANDIDATE=%CANDIDATE:"=%"
  if /I "%CANDIDATE%"=="php" set "RESOLVED_PHP=php"
  if not defined RESOLVED_PHP if exist "%CANDIDATE%" set "RESOLVED_PHP=%CANDIDATE%"
)

if not defined RESOLVED_PHP (
  for /f "delims=" %%I in ('where php 2^>nul') do (
    set "RESOLVED_PHP=%%I"
    goto :php_found
  )
)

:php_found
if not defined RESOLVED_PHP if exist "C:\xampp\php\php.exe" set "RESOLVED_PHP=C:\xampp\php\php.exe"

if not defined RESOLVED_PHP (
  echo PHP was not found.
  echo.
  echo Option 1: Install PHP and add it to PATH, then rerun this file.
  echo Option 2: Run with a direct path once:
  echo   set PHP_EXE=C:\path\to\php.exe ^&^& .\start-local.bat
  exit /b 1
)

echo Starting SAMS on http://localhost:8000/public/
start "" "http://localhost:8000/public/"
"%RESOLVED_PHP%" -S localhost:8000
