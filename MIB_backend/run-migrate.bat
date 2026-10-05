@echo off
set "PATH=C:\xampp\php;C:\ProgramData\ComposerSetup\bin;%PATH%"
cd /d "%~dp0"
echo ================================================
echo Running Laravel Database Migrations...
echo ================================================
php artisan migrate
pause
