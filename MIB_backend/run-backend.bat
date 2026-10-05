@echo off
set "PATH=C:\xampp\php;C:\ProgramData\ComposerSetup\bin;%PATH%"
cd /d "%~dp0"
echo ================================================
echo Starting Myintellbook Backend (Laravel 12)...
echo http://127.0.0.1:8000
echo ================================================
php artisan serve --port=8000
pause
