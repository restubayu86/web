@echo off
setlocal
rem ==========================================================================
rem  stop_all.bat - hentikan semua proses backend BayzBot.
rem
rem  Pemakaian:
rem    stop_all.bat              hentikan semua proses BayzBot
rem    stop_all.bat --dry-run    hanya tampilkan, tidak menghentikan
rem    stop_all.bat --no-pause   jangan menunggu tombol di akhir
rem    stop_all.bat --keep-web   jangan hentikan web server (php spark serve)
rem
rem  Yang dihentikan:
rem    1. python.exe/pythonw.exe/py.exe yang menjalankan backend.py di folder INI
rem       (dari folder mana pun, termasuk yang dijalankan dengan nama
rem       (path lengkap; nama lama backend_gui.py juga dikenali).
rem    2. cmd.exe yang menjalankan run_headless.bat / run_gui.bat di folder INI.
rem    3. python.exe/pythonw.exe yang memegang port WebSocket / HTTP API di
rem       gui_config.json (default 8765 / 8766).
rem    4. Web server CI4: php.exe yang menjalankan "spark serve", jendela Git Bash
rem       (bash.exe/mintty.exe) yang menjalankannya, dan cmd.exe yang menjalankan
rem       start_server.bat (lewati semuanya dengan --keep-web).
rem    5. Semua proses anak dari yang di atas.
rem  Proses non-Python yang memegang port hanya DILAPORKAN, tidak dihentikan.
rem ==========================================================================

set "APP_ROOT=%~dp0"
set "BAT_PATH=%~f0"
set "DRY_RUN=0"
set "PAUSE_END=1"
set "KEEP_WEB=0"
for %%A in (%*) do (
    if /I "%%~A"=="--dry-run" set "DRY_RUN=1"
    if /I "%%~A"=="--no-pause" set "PAUSE_END=0"
    if /I "%%~A"=="--keep-web" set "KEEP_WEB=1"
)

powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "$s=[IO.File]::ReadAllText($env:BAT_PATH);$i=$s.LastIndexOf('#PS-BEGIN');& ([scriptblock]::Create($s.Substring($i)))"
set "RC=%errorlevel%"

if "%RC%"=="0" (
    echo.
    echo Selesai.
) else (
    echo.
    echo GAGAL menghentikan semua proses BayzBot. Coba jalankan sebagai Administrator.
)
if "%PAUSE_END%"=="1" pause
exit /b %RC%

#PS-BEGIN
$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'
$root = [IO.Path]::GetFullPath($env:APP_ROOT)
$rootPrefix = $root.TrimEnd('\') + '\'
$dryRun = ($env:DRY_RUN -eq '1')
$keepWeb = ($env:KEEP_WEB -eq '1')

# --- Port dari gui_config.json (fallback ke default) ---
$wsPort = 8765
$httpPort = 8766
$cfgPath = Join-Path $root 'gui_config.json'
if (Test-Path -LiteralPath $cfgPath) {
    try {
        $cfg = Get-Content -LiteralPath $cfgPath -Raw -Encoding UTF8 | ConvertFrom-Json
        if ($cfg.ws_port -as [int]) { $wsPort = [int]$cfg.ws_port }
        if ($cfg.http_api_port -as [int]) { $httpPort = [int]$cfg.http_api_port }
    } catch {
        Write-Output ('Peringatan: gui_config.json tidak terbaca (' + $_.Exception.Message + '); memakai port default.')
    }
}
$ports = @($wsPort, $httpPort) | Sort-Object -Unique
Write-Output ('Port yang dipantau: ' + ($ports -join ', '))

# --- Snapshot proses ---
$all = @(Get-CimInstance Win32_Process)
$byId = @{}
foreach ($p in $all) { $byId[[int]$p.ProcessId] = $p }

# Skrip ini dan induknya (cmd/explorer) tidak boleh ikut dihentikan.
$protected = @{}
$cursor = [int]$PID
while ($cursor -gt 0 -and $byId.ContainsKey($cursor) -and -not $protected.ContainsKey($cursor)) {
    $protected[$cursor] = $true
    $cursor = [int]$byId[$cursor].ParentProcessId
}

$targets = @{}

# 1, 2 & 4. Cocokkan berdasarkan nama proses + command line.
function Test-Cmd([string]$line, [string]$text) {
    return $line.IndexOf($text, [StringComparison]::OrdinalIgnoreCase) -ge 0
}

# Cocokkan hanya skrip di folder INI (path lengkap), supaya backend.py / run_*.bat
# milik proyek lain dengan nama yang sama tidak ikut dihentikan. Nama lama tetap dikenali.
$backendNames = @('backend.py', 'backend_gui.py')
$launcherNames = @('run_headless.bat', 'run_gui.bat', 'run_backend_headless.bat', 'run_backend_gui.bat')
function Test-Own([string]$line, [string[]]$names) {
    foreach ($name in $names) {
        if (Test-Cmd $line ($rootPrefix + $name)) { return $true }
    }
    return $false
}
foreach ($p in $all) {
    $id = [int]$p.ProcessId
    if ($protected.ContainsKey($id)) { continue }
    $line = [string]$p.CommandLine
    if ([string]::IsNullOrEmpty($line)) { continue }
    $name = [string]$p.Name
    $isPython = $name -match '^(python|pythonw|py)\.exe$'
    $isPhp = $name -match '^php(-cgi|-win)?\.exe$'
    $isCmd = $name -ieq 'cmd.exe'
    $isShell = $name -match '^(bash|sh|mintty)\.exe$'
    if ($isPython -and (Test-Own $line $backendNames)) {
        $targets[$id] = $p
    } elseif ($isCmd -and (Test-Own $line $launcherNames)) {
        $targets[$id] = $p
    } elseif (-not $keepWeb -and $isPhp -and ($line -match '(?i)\bspark\b') -and ($line -match '(?i)\bserve\b')) {
        $targets[$id] = $p
    } elseif (-not $keepWeb -and $isShell -and ($line -match '(?i)\bspark\b') -and ($line -match '(?i)\bserve\b')) {
        $targets[$id] = $p
    } elseif (-not $keepWeb -and $isCmd -and (Test-Cmd $line ($rootPrefix + 'start_server.bat'))) {
        $targets[$id] = $p
    }
}

# 3. Pemegang port (hanya Python yang dihentikan; lainnya cuma dilaporkan).
$foreign = @()
try {
    $listeners = @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $ports -contains [int]$_.LocalPort })
} catch {
    $listeners = @()
    Write-Output 'Peringatan: Get-NetTCPConnection tidak tersedia; pemeriksaan port dilewati.'
}
foreach ($c in $listeners) {
    $id = [int]$c.OwningProcess
    if ($id -le 4 -or $protected.ContainsKey($id) -or -not $byId.ContainsKey($id)) { continue }
    $owner = $byId[$id]
    if ($owner.Name -match '^(python|pythonw)\.exe$') {
        $targets[$id] = $owner
    } else {
        $foreign += ('Port ' + $c.LocalPort + ' dipegang ' + $owner.Name + ' (PID ' + $id + '), bukan Python - tidak dihentikan.')
    }
}

# 5. Proses anak (cek waktu pembuatan agar tidak salah kena PID yang dipakai ulang).
$changed = $true
while ($changed) {
    $changed = $false
    foreach ($p in $all) {
        $id = [int]$p.ProcessId
        if ($protected.ContainsKey($id) -or $targets.ContainsKey($id)) { continue }
        $parentId = [int]$p.ParentProcessId
        if (-not $targets.ContainsKey($parentId)) { continue }
        $parent = $byId[$parentId]
        if ($parent.CreationDate -and $p.CreationDate -and $parent.CreationDate -gt $p.CreationDate) { continue }
        $targets[$id] = $p
        $changed = $true
    }
}

foreach ($note in ($foreign | Sort-Object -Unique)) { Write-Output $note }

$ids = @($targets.Keys | ForEach-Object { [int]$_ } | Sort-Object -Unique)
if ($ids.Count -eq 0) {
    Write-Output 'Tidak ada proses BayzBot yang berjalan.'
    exit 0
}

Write-Output ('Proses BayzBot ditemukan (' + $ids.Count + '):')
foreach ($id in $ids) {
    $line = [string]$targets[$id].CommandLine
    if ($line.Length -gt 110) { $line = $line.Substring(0, 107) + '...' }
    Write-Output ('  PID ' + $id + '  ' + $targets[$id].Name + '  ' + $line)
}

if ($dryRun) {
    Write-Output 'Dry run: tidak ada proses yang dihentikan.'
    exit 0
}

# --- Hentikan ---
$errors = @()
foreach ($id in $ids) {
    try {
        Stop-Process -Id $id -Force -ErrorAction Stop
        Write-Output ('Dihentikan PID ' + $id)
    } catch {
        if (Get-Process -Id $id -ErrorAction SilentlyContinue) {
            $errors += ('PID ' + $id + ': ' + $_.Exception.Message)
        }
    }
}

# --- Verifikasi (tunggu maksimal 5 detik) ---
$deadline = (Get-Date).AddSeconds(5)
do {
    Start-Sleep -Milliseconds 300
    $remaining = @($ids | Where-Object { Get-Process -Id $_ -ErrorAction SilentlyContinue })
} while ($remaining.Count -gt 0 -and (Get-Date) -lt $deadline)

if ($remaining.Count -gt 0) {
    Write-Output ('Proses masih berjalan: ' + ($remaining -join ', '))
    foreach ($e in $errors) { Write-Output $e }
    exit 1
}

# Pastikan port benar-benar bebas.
try {
    $stillListening = @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $ports -contains [int]$_.LocalPort })
    foreach ($c in $stillListening) {
        Write-Output ('Peringatan: port ' + $c.LocalPort + ' masih dipegang PID ' + $c.OwningProcess + '.')
    }
} catch { }

Write-Output 'Semua proses BayzBot sudah dihentikan.'
exit 0
