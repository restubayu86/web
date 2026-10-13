@echo off
setlocal
cd /d "%~dp0"

where python >nul 2>nul
if errorlevel 1 exit /b 1

python -c "import websockets" >nul 2>nul
if errorlevel 1 (
    python -m pip install websockets --quiet
    if errorlevel 1 exit /b 1
)

python -u "%~dp0backend.py" --headless
