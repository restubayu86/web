@echo off
setlocal
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo PHP tidak ditemukan di PATH. Pastikan PHP terpasang dan PATH sudah dikonfigurasi.
    pause
    exit /b 1
)

php spark serve --host 127.0.0.1
