@echo off
setlocal EnableExtensions
rem ==========================================================================
rem  start_server.bat - jalankan "php spark serve" di Git Bash.
rem
rem  Pemakaian:
rem    start_server.bat            buka jendela Git Bash baru yang menjalankan server
rem    start_server.bat --inline   jalankan di jendela CMD ini lewat bash.exe Git
rem
rem  Git Bash dicari otomatis (git.exe di PATH, lokasi standar, registry).
rem  Bisa juga ditentukan manual:  set GIT_ROOT=D:\Tools\Git
rem  Hentikan server dengan Ctrl+C di jendela Git Bash, atau pakai
rem  stop_all.bat.
rem ==========================================================================

cd /d "%~dp0"

set "INLINE=0"
for %%A in (%*) do (
    if /I "%%~A"=="--inline" set "INLINE=1"
)

where php >nul 2>nul
if errorlevel 1 (
    echo PHP tidak ditemukan di PATH. Pastikan PHP terpasang dan PATH sudah dikonfigurasi.
    pause
    exit /b 1
)

rem --- Cari instalasi Git for Windows (harus ada bin\bash.exe dan git-bash.exe) ---
rem 0) GIT_ROOT yang ditentukan manual (kalau tidak valid, lanjut deteksi otomatis)
set "GIT_PRESET=%GIT_ROOT%"
set "GIT_ROOT="
if defined GIT_PRESET call :try_root "%GIT_PRESET%"
if defined GIT_ROOT goto :git_found

rem 1) Dari git.exe di PATH (...\Git\cmd\git.exe atau ...\Git\mingw64\bin\git.exe)
for /f "delims=" %%G in ('where git 2^>nul') do (
    if not defined GIT_ROOT call :try_root "%%~dpG.."
    if not defined GIT_ROOT call :try_root "%%~dpG..\.."
)
if defined GIT_ROOT goto :git_found

rem 2) Lokasi instalasi standar
set "PF86=%ProgramFiles(x86)%"
for %%D in ("%ProgramFiles%\Git" "%ProgramW6432%\Git" "%PF86%\Git" "%LocalAppData%\Programs\Git") do (
    if not defined GIT_ROOT call :try_root "%%~D"
)
if defined GIT_ROOT goto :git_found

rem 3) Registry (instalasi untuk semua user, lalu user saat ini)
for /f "tokens=2,*" %%A in ('reg query "HKLM\SOFTWARE\GitForWindows" /v InstallPath 2^>nul ^| find "InstallPath"') do call :try_root "%%B"
if defined GIT_ROOT goto :git_found
for /f "tokens=2,*" %%A in ('reg query "HKCU\SOFTWARE\GitForWindows" /v InstallPath 2^>nul ^| find "InstallPath"') do call :try_root "%%B"
if defined GIT_ROOT goto :git_found

echo Git Bash tidak ditemukan.
echo Pasang Git for Windows dari https://git-scm.com/download/win
echo atau tentukan lokasinya dulu, contoh:  set GIT_ROOT=D:\Tools\Git
pause
exit /b 1

:git_found
set "GIT_BASH_EXE=%GIT_ROOT%\git-bash.exe"
set "BASH_EXE=%GIT_ROOT%\bin\bash.exe"
rem Supaya login shell tetap di folder proyek (tidak pindah ke home).
set "CHERE_INVOKING=1"
set "SERVER_CMD=php spark serve --host 127.0.0.1"

echo Git Bash : %GIT_ROOT%
echo Folder   : %CD%
echo Perintah : %SERVER_CMD%
echo.

if "%INLINE%"=="1" goto :inline

start "BayzBot Web Server" "%GIT_BASH_EXE%" --cd="%CD%" -c "%SERVER_CMD%; echo; echo '[Web server berhenti]'; exec bash"
exit /b 0

:inline
"%BASH_EXE%" --login -i -c "%SERVER_CMD%"
exit /b %errorlevel%

:try_root
set "CAND=%~f1"
if exist "%CAND%\bin\bash.exe" if exist "%CAND%\git-bash.exe" set "GIT_ROOT=%CAND%"
exit /b 0
