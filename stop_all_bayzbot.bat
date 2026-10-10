@echo off
setlocal

set "APP_ROOT=%~dp0"
set "DRY_RUN=0"
if /I "%~1"=="--dry-run" set "DRY_RUN=1"

powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command ^
 "$ErrorActionPreference = 'Stop';" ^
 "$root = [IO.Path]::GetFullPath($env:APP_ROOT);" ^
 "$backend = Join-Path $root 'backend_gui.py';" ^
 "$launcher = Join-Path $root 'run_backend_headless.bat';" ^
 "$all = @(Get-CimInstance Win32_Process);" ^
 "$targets = @{};" ^
 "foreach ($p in $all) { $line = [string]$p.CommandLine; if ($line.IndexOf($backend, [StringComparison]::OrdinalIgnoreCase) -ge 0 -or $line.IndexOf($launcher, [StringComparison]::OrdinalIgnoreCase) -ge 0) { $targets[[int]$p.ProcessId] = $p } }" ^
 "$changed = $true; while ($changed) { $changed = $false; foreach ($p in $all) { $id = [int]$p.ProcessId; $parent = [int]$p.ParentProcessId; if (-not $targets.ContainsKey($id) -and $targets.ContainsKey($parent)) { $targets[$id] = $p; $changed = $true } } }" ^
 "$ids = @($targets.Keys | ForEach-Object { [int]$_ } | Sort-Object -Unique);" ^
 "if ($ids.Count -eq 0) { Write-Output 'No BayzBot processes found.'; exit 0 }" ^
 "Write-Output ('BayzBot process IDs found: ' + ($ids -join ', '));" ^
 "if ($env:DRY_RUN -eq '1') { Write-Output 'Dry run only; no processes were stopped.'; exit 0 }" ^
 "$errors = @(); foreach ($id in $ids) { try { Stop-Process -Id $id -Force -ErrorAction Stop; Write-Output ('Stopped PID ' + $id) } catch { $errors += ('PID ' + $id + ': ' + $_.Exception.Message) } }" ^
 "Start-Sleep -Milliseconds 500;" ^
 "$remaining = @(Get-CimInstance Win32_Process | Where-Object { $targets.ContainsKey([int]$_.ProcessId) } | ForEach-Object { [int]$_.ProcessId });" ^
 "if ($remaining.Count -gt 0) { Write-Output ('Processes still running: ' + ($remaining -join ', ')); if ($errors.Count -gt 0) { $errors | ForEach-Object { Write-Output $_ } }; exit 1 }" ^
 "Write-Output 'All BayzBot processes were stopped.'; exit 0"

if errorlevel 1 (
    echo Failed to stop all BayzBot processes. Check permissions and try again.
    exit /b 1
)

endlocal
