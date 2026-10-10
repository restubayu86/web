@echo off
title BayzBot Control Panel - Launcher
setlocal enabledelayedexpansion

echo ================================
echo  BayzBot Control Panel
echo ================================
echo.

:: 1. Cek Python terpasang
where python >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Python tidak ditemukan di PATH.
    echo Install Python dari https://www.python.org/downloads/ lalu jalankan lagi.
    echo ^(Saat instal, centang "Add python.exe to PATH"^)
    pause
    exit /b 1
)

python --version
echo.

:: 2. Cek modul tkinter (bawaan Python, tapi kadang tidak ter-include)
python -c "import tkinter" 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Modul 'tkinter' tidak tersedia di instalasi Python ini.
    echo Install ulang Python dari python.org ^(centang opsi tcl/tk saat instal^).
    pause
    exit /b 1
)

:: 3. Cek modul websockets, install kalau belum ada
echo Memeriksa dependensi (websockets)...
python -c "import websockets" 2>nul
if %errorlevel% neq 0 (
    echo Modul 'websockets' belum ada, menginstal...
    python -m pip install --upgrade pip --quiet
    python -m pip install websockets --quiet
    if %errorlevel% neq 0 (
        echo [ERROR] Gagal menginstal 'websockets'. Cek koneksi internet kamu.
        pause
        exit /b 1
    )
    echo Berhasil diinstal.
) else (
    echo OK - websockets sudah terpasang.
)
echo.

:: 4. Pastikan backend_gui.py ada di folder yang sama
if not exist "%~dp0backend_gui.py" (
    echo [ERROR] backend_gui.py tidak ditemukan di folder ini:
    echo %~dp0
    pause
    exit /b 1
)

:: 5. Bersihkan __pycache__ (Chrome extension loader tidak bisa handle Python cache)
if exist "%~dp0__pycache__" (
    echo Membersihkan cache Python...
    rmdir /s /q "%~dp0__pycache__" >nul 2>nul
)

:: 6. Jalankan aplikasinya
echo Menjalankan BayzBot Control Panel...
echo ^(Jangan tutup jendela ini selagi aplikasi masih dipakai^)
echo.
cd /d "%~dp0"
python -u backend_gui.py --gui
set BAYZBOT_EXIT=%errorlevel%

echo.
echo Exit code Python: %BAYZBOT_EXIT%
echo Aplikasi BayzBot ditutup.
pause
