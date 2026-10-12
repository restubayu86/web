<?php

namespace App\Libraries;

use App\Models\ChromiumProfileModel;
use App\Models\ProxyModel;

class ChromiumLauncher
{
    public function isRunning(array $profile, string $executable): bool
    {
        $processId = (int) ($profile['process_id'] ?? 0);
        if ($processId < 1 || PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $profileDirectory = WRITEPATH . 'chromium-profiles' . DIRECTORY_SEPARATOR . (int) $profile['id'];
        $result = $this->runPowerShell([
            'process_id' => $processId,
            'executable' => $executable,
            'profile_directory' => $profileDirectory,
        ], <<<'POWERSHELL'
$config = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__PAYLOAD__')) | ConvertFrom-Json
$process = Get-CimInstance Win32_Process -Filter "ProcessId=$($config.process_id)" -ErrorAction SilentlyContinue
if ($process -and $process.ExecutablePath -ieq $config.executable -and $process.CommandLine -like ('*' + $config.profile_directory + '*')) {
    [Console]::Out.Write('running')
}
POWERSHELL);

        return $result['success'] && trim($result['output']) === 'running';
    }

    public function start(array $profile, string $executable): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return $this->fail('Chromium local launch hanya didukung di Windows saat ini.', 501);
        }
        if (!is_file($executable) || strtolower(pathinfo($executable, PATHINFO_EXTENSION)) !== 'exe') {
            return $this->fail('File Chromium executable tidak ditemukan. Periksa path di halaman Chromium.', 422);
        }

        $profileId = (int) ($profile['id'] ?? 0);
        if ($profileId < 1) {
            return $this->fail('Profil Chromium tidak valid.', 400);
        }

        $profileDirectory = WRITEPATH . 'chromium-profiles' . DIRECTORY_SEPARATOR . $profileId;
        $extensionDirectory = $profileDirectory . DIRECTORY_SEPARATOR . 'bayzbot-extension';
        if (!is_dir($profileDirectory) && !mkdir($profileDirectory, 0775, true) && !is_dir($profileDirectory)) {
            return $this->fail('Folder data Chromium tidak dapat dibuat.', 500);
        }

        $proxy = null;
        if (!empty($profile['proxy_id'])) {
            $proxy = (new ProxyModel())->find((int) $profile['proxy_id']);
            if (!$proxy) {
                return $this->fail('Proxy profil ini tidak ditemukan. Pilih ulang proxy dari halaman Chromium.', 422);
            }
        }

        $extensionResult = $this->prepareExtension($extensionDirectory, $profileId, $proxy);
        if (!$extensionResult['success']) {
            return $extensionResult;
        }

        $arguments = [
            '--user-data-dir="' . $profileDirectory . '"',
            '--load-extension="' . $extensionDirectory . '"',
            '--no-first-run',
            '--no-default-browser-check',
            '--new-window',
        ];
        if ($proxy !== null) {
            $server = $this->proxyServer($proxy);
            if ($server === null) {
                return $this->fail('Format alamat proxy tidak valid.', 422);
            }
            $arguments[] = '--proxy-server=' . $server;
        }
        $arguments[] = 'https://music.youtube.com/';

        // Jika executable adalah chromedriver, kita harus memanggil chrome.exe yang berada di folder yang sama
        $chromeExecutable = $executable;
        if (str_contains(strtolower($executable), 'chromedriver')) {
            $chromeExecutable = dirname($executable) . DIRECTORY_SEPARATOR . 'chrome.exe';
        }

        $result = $this->runPowerShell([
            'executable' => $chromeExecutable,
            'arguments' => $arguments,
        ], <<<'POWERSHELL'
$config = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__PAYLOAD__')) | ConvertFrom-Json
$process = Start-Process -FilePath $config.executable -ArgumentList $config.arguments -PassThru
[Console]::Out.Write($process.Id)
POWERSHELL);
        if (!$result['success']) {
            return $result;
        }

        $processId = filter_var(trim($result['output']), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($processId === false) {
            log_message('error', 'Chromium started but its process ID could not be read.');
            return $this->fail('Chromium mungkin berjalan, tetapi ID proses tidak dapat dibaca. Periksa Task Manager.', 500);
        }

        return ['success' => true, 'process_id' => $processId];
    }

    public function stop(array $profile, string $executable): array
    {
        $processId = (int) ($profile['process_id'] ?? 0);
        if ($processId < 1) {
            return ['success' => true, 'message' => 'Profil Chromium sudah berhenti.'];
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            return $this->fail('Chromium local launch hanya didukung di Windows saat ini.', 501);
        }

        $profileDirectory = WRITEPATH . 'chromium-profiles' . DIRECTORY_SEPARATOR . (int) $profile['id'];
        $result = $this->runPowerShell([
            'process_id' => $processId,
            'executable' => $executable,
            'profile_directory' => $profileDirectory,
        ], <<<'POWERSHELL'
$config = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__PAYLOAD__')) | ConvertFrom-Json
$process = Get-CimInstance Win32_Process -Filter "ProcessId=$($config.process_id)" -ErrorAction SilentlyContinue
if (-not $process) { [Console]::Out.Write('stopped'); exit 0 }
if ($process.ExecutablePath -ine $config.executable -or $process.CommandLine -notlike ('*' + $config.profile_directory + '*')) {
    [Console]::Error.Write('Process identity does not match this Chromium profile.')
    exit 3
}
$kill = Start-Process -FilePath "$env:SystemRoot\System32\taskkill.exe" -ArgumentList @('/PID', [string]$config.process_id, '/T', '/F') -Wait -PassThru -NoNewWindow
if ($kill.ExitCode -ne 0) { [Console]::Error.Write("taskkill exited with $($kill.ExitCode)"); exit $kill.ExitCode }
[Console]::Out.Write('stopped')
POWERSHELL);

        return $result['success']
            ? ['success' => true, 'message' => 'Chromium dihentikan.']
            : $result;
    }

    private function prepareExtension(string $targetDirectory, int $profileId, ?array $proxy): array
    {
        $sourceDirectory = ROOTPATH . 'bot-extension';
        if (!is_dir($sourceDirectory) || !is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            return $this->fail('Folder ekstensi BayzBot tidak ditemukan atau tidak dapat disiapkan.', 500);
        }

        foreach (['manifest.json', 'background.js', 'chromium-config.js', 'content.js', 'options.html', 'options.js'] as $file) {
            if (!copy($sourceDirectory . DIRECTORY_SEPARATOR . $file, $targetDirectory . DIRECTORY_SEPARATOR . $file)) {
                log_message('error', 'Could not copy Chromium extension file: ' . $file);
                return $this->fail('Ekstensi BayzBot untuk Chromium tidak dapat disiapkan.', 500);
            }
        }

        $manifestPath = $targetDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return $this->fail('Manifest ekstensi BayzBot tidak valid.', 500);
        }
        $manifest['permissions'] = array_values(array_unique(array_merge(
            $manifest['permissions'] ?? [],
            ['webRequest', 'webRequestAuthProvider']
        )));
        $manifest['host_permissions'] = array_values(array_unique(array_merge(
            $manifest['host_permissions'] ?? [],
            ['http://*/*', 'https://*/*']
        )));
        if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            return $this->fail('Manifest ekstensi BayzBot tidak dapat dikonfigurasi.', 500);
        }

        $config = [
            'profileId' => (string) ChromiumProfileModel::bridgeId($profileId),
            'proxyAuth' => $proxy && (!empty($proxy['username']) || !empty($proxy['password']))
                ? [
                    'username' => (string) ($proxy['username'] ?? ''),
                    'password' => (string) ($proxy['password'] ?? ''),
                ]
                : null,
        ];
        $configJs = 'globalThis.BAYZBOT_CHROMIUM_CONFIG = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
        if (file_put_contents($targetDirectory . DIRECTORY_SEPARATOR . 'chromium-config.js', $configJs, LOCK_EX) === false) {
            return $this->fail('Konfigurasi ekstensi Chromium tidak dapat disimpan.', 500);
        }

        return ['success' => true];
    }

    private function proxyServer(array $proxy): ?string
    {
        $type = strtolower((string) ($proxy['proxy_type'] ?? 'http'));
        if (!in_array($type, ['http', 'https', 'socks4', 'socks5'], true)) {
            return null;
        }

        $address = trim((string) ($proxy['proxy_url'] ?? ''));
        if ($address === '') {
            return null;
        }
        if (str_contains($address, '://')) {
            $parts = parse_url($address);
            if (!is_array($parts) || empty($parts['host'])) {
                return null;
            }
            $host = $parts['host'];
            $port = $parts['port'] ?? (int) ($proxy['port'] ?? 0);
        } else {
            $addressParts = explode(':', $address);
            $host = trim($addressParts[0]);
            $port = (int) ($proxy['port'] ?? ($addressParts[1] ?? 0));
        }
        if ($host === '' || $port < 1 || $port > 65535 || preg_match('/[\s\/@"\\\\]/', $host)) {
            return null;
        }

        return $type . '://' . $host . ':' . $port;
    }

    private function runPowerShell(array $data, string $script): array
    {
        $payload = base64_encode(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $script = str_replace('__PAYLOAD__', $payload, $script);
        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
        $pipes = [];
        $process = proc_open(
            ['powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', $encoded],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            ROOTPATH,
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            return $this->fail('PowerShell tidak dapat dijalankan untuk mengontrol Chromium.', 500);
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            log_message('error', 'Chromium PowerShell command failed ({code}): {error}', [
                'code' => $exitCode,
                'error' => trim((string) $error),
            ]);
            return $this->fail('Perintah Chromium gagal: ' . (trim((string) $error) ?: 'PowerShell exit code ' . $exitCode), 500);
        }

        return ['success' => true, 'output' => (string) $output];
    }

    private function fail(string $message, int $status): array
    {
        return ['success' => false, 'message' => $message, 'status' => $status];
    }
}
