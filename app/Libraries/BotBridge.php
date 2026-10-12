<?php

namespace App\Libraries;

use App\Models\SettingsModel;

class BotBridge
{
    private const DEFAULT_HTTP_API_PORT = 8766;

    public static function normalizeUrl($url): ?string
    {
        if (!is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'http'
            || !in_array(strtolower((string) ($parts['host'] ?? '')), ['localhost', '127.0.0.1'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['path']) && !in_array($parts['path'], ['', '/'], true)
            || isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535)
        ) {
            return null;
        }

        return rtrim(trim($url), '/');
    }

    public function health(string $baseUrl, string $token): array
    {
        // Increase timeout to 5 seconds to give Python backend time to start up.
        return $this->request($baseUrl, $token, 'GET', '/api/health', [], 5);
    }

    public function play(string $baseUrl, string $token, array $payload): array
    {
        return $this->request($baseUrl, $token, 'POST', '/api/play', $payload, 60);
    }

    public function stop(string $baseUrl, string $token, int $profileId): array
    {
        return $this->request($baseUrl, $token, 'POST', '/api/stop', ['profile_id' => $profileId], 10);
    }

    public function control(string $baseUrl, string $token, int $profileId, string $action): array
    {
        return $this->request($baseUrl, $token, 'POST', '/api/control', [
            'profile_id' => $profileId,
            'action' => $action,
        ], 25);
    }

    public function session(string $baseUrl, string $token, int $profileId): array
    {
        return $this->request($baseUrl, $token, 'GET', '/api/session/' . $profileId, [], 2);
    }

    public function ensureRunning(SettingsModel $settings): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return ['success' => false, 'message' => 'Autostart BayzBot saat ini hanya didukung di Windows.'];
        }

        $root = rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $configPath = $root . 'gui_config.json';
        $bridgeUrl = self::normalizeUrl($settings->getSetting('bot_bridge_url'))
            ?? 'http://127.0.0.1:' . self::DEFAULT_HTTP_API_PORT;
        $bridgeParts = parse_url($bridgeUrl);
        $defaultPort = isset($bridgeParts['port']) ? (int) $bridgeParts['port'] : self::DEFAULT_HTTP_API_PORT;

        if (!is_file($configPath)) {
            try {
                $initialConfig = json_encode([
                    'http_api_port' => $defaultPort,
                    'http_api_token' => bin2hex(random_bytes(32)),
                ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (file_put_contents($configPath, $initialConfig, LOCK_EX) === false) {
                    return ['success' => false, 'message' => 'Tidak bisa membuat konfigurasi gui_config.json.'];
                }
            } catch (\Throwable $e) {
                log_message('error', 'Gagal membuat konfigurasi awal BayzBot: ' . $e->getMessage());
                return ['success' => false, 'message' => 'Gagal menyiapkan konfigurasi BayzBot lokal.'];
            }
        }

        $configContents = @file_get_contents($configPath);
        $config = is_string($configContents) ? json_decode($configContents, true) : null;
        if (!is_array($config)) {
            return ['success' => false, 'message' => 'gui_config.json tidak valid; periksa konfigurasi BayzBot.'];
        }
        $port = filter_var(
            $config['http_api_port'] ?? self::DEFAULT_HTTP_API_PORT,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]
        );
        $token = $config['http_api_token'] ?? null;
        if ($port === false) {
            return ['success' => false, 'message' => 'Port HTTP API tidak valid di gui_config.json.'];
        }
        if (!is_string($token) || $token === '') {
            try {
                $token = bin2hex(random_bytes(32));
                $config['http_api_token'] = $token;
                $config['http_api_port'] = $port;
                $encodedConfig = json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (file_put_contents($configPath, $encodedConfig, LOCK_EX) === false) {
                    return ['success' => false, 'message' => 'Tidak bisa menyimpan token API di gui_config.json.'];
                }
            } catch (\Throwable $e) {
                log_message('error', 'Gagal menyiapkan token API BayzBot: ' . $e->getMessage());
                return ['success' => false, 'message' => 'Gagal menyiapkan token API BayzBot lokal.'];
            }
        }

        $bridgeUrl = 'http://127.0.0.1:' . $port;
        if ($settings->getSetting('bot_bridge_url') !== $bridgeUrl) {
            $settings->setSetting('bot_bridge_url', $bridgeUrl);
        }
        if ($settings->getSetting('bot_bridge_token') !== $token) {
            $settings->setSetting('bot_bridge_token', $token);
        }

        $health = $this->health($bridgeUrl, $token);
        if ($health['success']) {
            return ['success' => true, 'started' => false, 'message' => 'BayzBot Python sudah berjalan.'];
        }

        if (in_array($health['status'] ?? 0, [401, 403], true)) {
            return [
                'success' => false,
                'message' => 'BayzBot sudah berjalan tetapi token tidak cocok dengan gui_config.json. Restart backend Python.',
            ];
        }

        $lockDirectory = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($lockDirectory) && !@mkdir($lockDirectory, 0775, true) && !is_dir($lockDirectory)) {
            log_message('error', 'Tidak bisa membuat folder lock autostart BayzBot: ' . $lockDirectory);
            return ['success' => false, 'message' => 'Tidak bisa menyiapkan autostart BayzBot.'];
        }
        $lock = @fopen($lockDirectory . DIRECTORY_SEPARATOR . 'bayzbot-autostart.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            return ['success' => false, 'message' => 'Tidak bisa mengunci proses autostart BayzBot.'];
        }

        try {
            $health = $this->health($bridgeUrl, $token);
            if ($health['success']) {
                return ['success' => true, 'started' => false, 'message' => 'BayzBot Python sudah berjalan.'];
            }

            if (in_array($health['status'] ?? 0, [401, 403], true)) {
                return [
                    'success' => false,
                    'message' => 'BayzBot sudah berjalan tetapi token tidak cocok dengan gui_config.json. Restart backend Python.',
                ];
            }

            rewind($lock);
            $lastStart = (int) trim((string) stream_get_contents($lock));
            if ($lastStart > 0 && time() - $lastStart < 120) {
                return [
                    'success' => true,
                    'started' => true,
                    'message' => 'BayzBot Python sedang dinyalakan di background.',
                ];
            }

            $wsPort = filter_var(
                $config['ws_port'] ?? 8765,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 65535]]
            ) ?: 8765;
            $probe = @fsockopen('127.0.0.1', $wsPort, $probeErrno, $probeError, 0.5);
            if (is_resource($probe)) {
                fclose($probe);
                log_message('warning', 'BayzBot autostart dibatalkan: port WebSocket {port} sudah dipakai proses lain (health status={status}).', [
                    'port' => $wsPort,
                    'status' => $health['status'] ?? 'n/a',
                ]);
                return [
                    'success' => false,
                    'message' => 'Port WebSocket ' . $wsPort . ' dipakai proses lain (bukan BayzBot folder ini). Tutup proses itu atau ubah ws_port di gui_config.json.',
                ];
            }

            log_message('warning', 'BayzBot autostart: health gagal (status={status}, pesan={msg}); menjalankan backend.', [
                'status' => $health['status'] ?? 'n/a',
                'msg' => $health['message'] ?? '',
            ]);

            $launcher = $root . 'run_backend_headless.bat';
            $backend = $root . 'backend_gui.py';
            if (!is_file($launcher) || !is_file($backend)) {
                return ['success' => false, 'message' => 'Launcher headless atau backend Python tidak ditemukan di folder web.'];
            }

            $logDirectory = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logDirectory) && !@mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
                return ['success' => false, 'message' => 'Tidak bisa membuat folder log BayzBot.'];
            }

            // Arsipkan log run sebelumnya ke subfolder (tidak ikut terbaca panel log).
            // rename gagal diam-diam kalau file masih dipakai backend yang sedang jalan.
            $archiveDirectory = $logDirectory . DIRECTORY_SEPARATOR . 'bayzbot-archive';
            foreach (['stdout', 'stderr'] as $logName) {
                $currentLog = $logDirectory . DIRECTORY_SEPARATOR . 'bayzbot-' . $logName . '.log';
                if (is_file($currentLog) && filesize($currentLog) > 0) {
                    if (!is_dir($archiveDirectory)) {
                        @mkdir($archiveDirectory, 0775, true);
                    }
                    @rename($currentLog, $archiveDirectory . DIRECTORY_SEPARATOR . 'bayzbot-' . $logName . '-' . date('Ymd-His', (int) filemtime($currentLog)) . '.log');
                }
            }

            $powershell = rtrim((string) getenv('WINDIR'), '\\/')
                . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
            $commandInterpreter = rtrim((string) getenv('WINDIR'), '\\/') . '\\System32\\cmd.exe';
            if (!is_file($powershell) || !is_file($commandInterpreter)) {
                return ['success' => false, 'message' => 'PowerShell atau Windows command interpreter tidak ditemukan.'];
            }
            $quotePowerShell = static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'";
            $arguments = 'Start-Process -FilePath ' . $quotePowerShell($commandInterpreter)
                . ' -ArgumentList @(' . $quotePowerShell('/d') . ', ' . $quotePowerShell('/c')
                . ', ' . $quotePowerShell('call "' . $launcher . '"') . ')'
                . ' -WorkingDirectory ' . $quotePowerShell($root)
                . ' -WindowStyle Hidden'
                . ' -RedirectStandardOutput ' . $quotePowerShell($logDirectory . '\\bayzbot-stdout.log')
                . ' -RedirectStandardError ' . $quotePowerShell($logDirectory . '\\bayzbot-stderr.log');
            $launcherStdoutPath = $logDirectory . DIRECTORY_SEPARATOR . 'bayzbot-launcher-stdout.log';
            $launcherStderrPath = $logDirectory . DIRECTORY_SEPARATOR . 'bayzbot-launcher-stderr.log';
            $process = @proc_open(
                [$powershell, '-NoProfile', '-NonInteractive', '-Command', $arguments],
                [
                    0 => ['pipe', 'r'],
                    1 => ['file', $launcherStdoutPath, 'wb'],
                    2 => ['file', $launcherStderrPath, 'wb'],
                ],
                $pipes
            );
            if (!is_resource($process)) {
                return ['success' => false, 'message' => 'Tidak bisa memulai proses BayzBot background.'];
            }
            fclose($pipes[0]);
            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                log_message('error', 'PowerShell gagal menjalankan BayzBot. stdout={stdout}; stderr={stderr}', [
                    'stdout' => trim((string) @file_get_contents($launcherStdoutPath)),
                    'stderr' => trim((string) @file_get_contents($launcherStderrPath)),
                ]);
                return ['success' => false, 'message' => 'Windows gagal menjalankan backend Python. Periksa log aplikasi.'];
            }

            rewind($lock);
            ftruncate($lock, 0);
            fwrite($lock, (string) time());
            fflush($lock);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['success' => true, 'started' => true, 'message' => 'BayzBot Python dinyalakan di background tanpa GUI.'];
    }

    private function request(
        string $baseUrl,
        string $token,
        string $method,
        string $path,
        array $payload = [],
        int $timeout = 5
    ): array {
        try {
            $options = [
                'timeout' => $timeout,
                'http_errors' => false,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ];
            if ($method === 'POST') {
                $options['json'] = $payload;
            }

            $response = \Config\Services::curlrequest()->request($method, $baseUrl . $path, $options);
            $status = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);
            if (!is_array($body)) {
                return [
                    'success' => false,
                    'message' => 'BayzBot mengembalikan respons yang tidak valid (HTTP ' . $status . ').',
                    'status' => $status,
                ];
            }

            $body['success'] = ($body['success'] ?? false) === true && $status >= 200 && $status < 300;
            $body['status'] = $status;
            if (!$body['success'] && empty($body['message'])) {
                $body['message'] = 'BayzBot menolak request (HTTP ' . $status . ').';
            }
            return $body;
        } catch (\Exception $e) {
            log_message('error', 'BayzBot bridge request failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => client_error($e, 'Tidak bisa menghubungi BayzBot lokal'),
                'status' => 503,
            ];
        }
    }
}
