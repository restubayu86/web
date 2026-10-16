<?php

namespace App\Controllers;

use App\Libraries\DolphinLocalApi;
use App\Libraries\BotBridge;
use App\Models\SettingsModel;

class Settings extends BaseController
{
    private $settingsModel;

    public function __construct()
    {
        $this->settingsModel = new SettingsModel();
    }

    public function index()
    {
        $data = [
            'api_token' => $this->settingsModel->getSetting('dolphin_api_token') ?? '',
            'local_api_url' => $this->settingsModel->getSetting('dolphin_local_url') ?: DolphinLocalApi::DEFAULT_URL,
            'telegram_bot_token' => $this->settingsModel->getSetting('telegram_bot_token') ?? '',
            'telegram_chat_id' => $this->settingsModel->getSetting('telegram_chat_id') ?? '',
            'telegram_notifications_enabled' => $this->settingsModel->getSetting('telegram_notifications_enabled') ?? '0',
            'telegram_ram_notifications_enabled' => $this->settingsModel->getSetting('telegram_ram_notifications_enabled') ?? '0',
            'ram_threshold_percent' => rtrim(rtrim(number_format($this->settingsModel->getRamThreshold(), 2, '.', ''), '0'), '.'),
            'total_loop' => $this->settingsModel->getSetting('total_loop') ?? '-1',
            'shuffle_playlist' => $this->settingsModel->getSetting('shuffle_playlist') ?? '0',
            'watchdog_interval' => $this->settingsModel->getSetting('watchdog_interval') ?? '30',
            'ad_skip_delay_seconds' => $this->settingsModel->getSetting('ad_skip_delay_seconds') ?? '0',
            'ram_stop_streams_enabled' => $this->settingsModel->getSetting('ram_stop_streams_enabled') ?? '0',
            'bot_bridge_url' => $this->settingsModel->getSetting('bot_bridge_url') ?: 'http://127.0.0.1:8766',
            'bot_bridge_token' => $this->settingsModel->getSetting('bot_bridge_token') ?? '',
            'revenue_rates' => json_decode($this->settingsModel->getSetting('revenue_rates') ?? '[]', true) ?: [],
        ];
        return view('settings', $data);
    }

    public function getRevenueRates()
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => json_decode($this->settingsModel->getSetting('revenue_rates') ?? '[]', true) ?: []
        ]);
    }

    /**
     * Kirim pengaturan yang berlaku langsung (ambang RAM) ke backend Python supaya
     * LOG dan peringatan Telegram memakai nilai yang sama dengan halaman ini.
     * Kalau backend belum berjalan, nilainya ikut terkirim saat stream dijalankan.
     */
    private function pushRuntimeSettingsToBackend(): void
    {
        $url = BotBridge::normalizeUrl($this->settingsModel->getSetting('bot_bridge_url'));
        $token = trim((string) $this->settingsModel->getSetting('bot_bridge_token'));
        if ($url === null || $token === '') {
            return;
        }

        $result = (new BotBridge())->updateSettings($url, $token, [
            'ram_threshold_percent' => $this->settingsModel->getRamThreshold(),
        ]);
        if (!($result['success'] ?? false)) {
            log_message('warning', 'Threshold RAM belum terkirim ke backend: {msg}', [
                'msg' => $result['message'] ?? 'tidak ada respons',
            ]);
        }
    }

    public function save()
    {
        $apiToken = $this->request->getPost('api_token');
        $telegramBotToken = $this->request->getPost('telegram_bot_token');
        $telegramChatId = $this->request->getPost('telegram_chat_id');
        $telegramNotificationsEnabled = $this->request->getPost('telegram_notifications_enabled') ? '1' : '0';
        $telegramRamNotificationsEnabled = $this->request->getPost('telegram_ram_notifications_enabled') ? '1' : '0';
        $ramThresholdInput = trim((string) ($this->request->getPost('ram_threshold_percent') ?? '85'));
        $ramStopStreamsEnabled = $this->request->getPost('ram_stop_streams_enabled') ? '1' : '0';
        $totalLoop = $this->request->getPost('total_loop') ?? '-1';
        $shufflePlaylist = $this->request->getPost('shuffle_playlist') ? '1' : '0';
        $watchdogInterval = $this->request->getPost('watchdog_interval') ?? '30';
        $adSkipDelaySeconds = $this->request->getPost('ad_skip_delay_seconds') ?? '0';
        $botBridgeUrl = BotBridge::normalizeUrl($this->request->getPost('bot_bridge_url'));
        $botBridgeToken = trim((string) $this->request->getPost('bot_bridge_token'));

        // URL Local API: hanya loopback; jika tidak valid, nilai lama dipertahankan.
        $localUrl = DolphinLocalApi::normalizeUrl($this->request->getPost('local_api_url'));
        if ($localUrl === null) {
            $localUrlError = 'URL Local API tidak valid (harus localhost / 127.0.0.1), nilai lama dipertahankan.';
        } else {
            $this->settingsModel->setSetting('dolphin_local_url', $localUrl);
        }

        $this->settingsModel->setSetting('dolphin_api_token', $apiToken);
        $this->settingsModel->setSetting('telegram_bot_token', $telegramBotToken);
        $this->settingsModel->setSetting('telegram_chat_id', $telegramChatId);
        $this->settingsModel->setSetting('telegram_notifications_enabled', $telegramNotificationsEnabled);
        $this->settingsModel->setSetting('telegram_ram_notifications_enabled', $telegramRamNotificationsEnabled);
        // Ambang RAM harus 1-100; nilai lain ditolak dan nilai lama dipertahankan.
        if (is_numeric($ramThresholdInput) && (float) $ramThresholdInput >= 1 && (float) $ramThresholdInput <= 100) {
            $this->settingsModel->setSetting('ram_threshold_percent', rtrim(rtrim(number_format((float) $ramThresholdInput, 2, '.', ''), '0'), '.'));
        } else {
            $ramThresholdError = 'Threshold RAM harus angka 1-100, nilai lama dipertahankan.';
        }
        $this->settingsModel->setSetting('ram_stop_streams_enabled', $ramStopStreamsEnabled);
        $this->settingsModel->setSetting('total_loop', $totalLoop);
        $this->settingsModel->setSetting('shuffle_playlist', $shufflePlaylist);
        $this->settingsModel->setSetting('watchdog_interval', $watchdogInterval);
        $this->settingsModel->setSetting('ad_skip_delay_seconds', $adSkipDelaySeconds);
        if ($botBridgeUrl === null) {
            $botBridgeUrlError = 'URL BayzBot tidak valid (harus localhost / 127.0.0.1), nilai lama dipertahankan.';
        } else {
            $this->settingsModel->setSetting('bot_bridge_url', $botBridgeUrl);
        }
        $this->settingsModel->setSetting('bot_bridge_token', $botBridgeToken);

        // Save Revenue Rates
        $countries = $this->request->getPost('rev_country') ?? [];
        $rates = $this->request->getPost('rev_rate') ?? [];
        $revenueRates = [];
        foreach ($countries as $index => $country) {
            if (!empty($country) && isset($rates[$index])) {
                $revenueRates[] = [
                    'country' => strtoupper(trim($country)),
                    'rate' => (float)$rates[$index]
                ];
            }
        }
        $this->settingsModel->setSetting('revenue_rates', json_encode($revenueRates));

        $redirect = redirect()->back()->with('message', 'Settings saved successfully!');
        $this->pushRuntimeSettingsToBackend();
        $errors = array_filter([$localUrlError ?? null, $botBridgeUrlError ?? null, $ramThresholdError ?? null]);
        if ($errors !== []) {
            $redirect->with('error', implode(' ', $errors));
        }

        return $redirect;
    }

    public function checkBotBridge()
    {
        $startup = (new BotBridge())->ensureRunning($this->settingsModel);
        if (!$startup['success']) {
            return $this->response->setJSON($startup)->setStatusCode(503);
        }

        $baseUrl = BotBridge::normalizeUrl($this->request->getPost('bot_bridge_url'));
        if ($baseUrl === null) {
            $baseUrl = BotBridge::normalizeUrl($this->settingsModel->getSetting('bot_bridge_url'));
        }
        $token = trim((string) $this->request->getPost('bot_bridge_token'));
        if ($token === '') {
            $token = trim((string) $this->settingsModel->getSetting('bot_bridge_token'));
        }
        if ($baseUrl === null || $token === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'URL loopback dan token BayzBot wajib diisi.',
            ])->setStatusCode(400);
        }

        $result = (new BotBridge())->health($baseUrl, $token);
        $status = $result['status'] ?? 503;
        unset($result['status']);
        return $this->response->setJSON($result)->setStatusCode($status);
    }

    public function checkConnection()
    {
        $apiToken = trim((string) $this->request->getPost('api_token'));
        if ($apiToken === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'API token belum diisi.']);
        }

        try {
            $response = \Config\Services::curlrequest()->get('https://anty-api.com/browser_profiles', [
                'timeout'     => 8,
                'http_errors' => false,
                'query'       => ['limit' => 1],
                'headers'     => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $apiToken,
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status === 401 || $status === 403) {
                return $this->response->setJSON(['success' => false, 'message' => 'Token ditolak oleh Dolphin{anty} (HTTP ' . $status . ').']);
            }
            if ($status < 200 || $status >= 300) {
                return $this->response->setJSON(['success' => false, 'message' => 'Dolphin{anty} API membalas HTTP ' . $status . '.']);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Terhubung ke Dolphin{anty} API, token valid.']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => client_error($e, 'Tidak bisa menghubungi Dolphin{anty} API')]);
        }
    }

    /** Cek Local API: login dengan token ke aplikasi Dolphin yang berjalan di mesin ini. */
    public function checkLocal()
    {
        $url = DolphinLocalApi::normalizeUrl($this->request->getPost('local_api_url'));
        if ($url === null) {
            return $this->response->setJSON(['success' => false, 'message' => 'URL Local API tidak valid (harus localhost / 127.0.0.1).']);
        }

        $result = (new DolphinLocalApi($url, $this->request->getPost('api_token')))->login();
        unset($result['status']); // detail internal, tidak perlu ke klien

        return $this->response->setJSON($result);
    }

    public function testTelegram()
    {
        $botToken = $this->request->getPost('telegram_bot_token');
        $chatId = $this->request->getPost('telegram_chat_id');

        if (empty($botToken) || empty($chatId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Bot token dan Chat ID harus diisi'
            ])->setStatusCode(400);
        }

        try {
            $client = \Config\Services::curlrequest();

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $payload = [
                'chat_id' => $chatId,
                'text' => '✅ *Dolphin{anty} Monitoring*: Koneksi berhasil! Bot siap digunakan.',
                'parse_mode' => 'Markdown'
            ];

            $response = $client->post($url, [
                'timeout' => 10,
                'json' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode === 200 && isset($body['ok']) && $body['ok'] === true) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Test pesan Telegram berhasil dikirim!'
                ]);
            }

            $errorMsg = $body['description'] ?? $body['error_description'] ?? 'Gagal mengirim pesan ke Telegram';
            return $this->response->setJSON([
                'success' => false,
                'message' => $errorMsg
            ])->setStatusCode(400);
        } catch (\Exception $e) {
            log_message('error', 'Telegram test error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => client_error($e, 'Error')
            ])->setStatusCode(500);
        }
    }

    public function getRamUsage()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec('powershell -Command "Get-CimInstance Win32_OperatingSystem | Select-Object -ExpandProperty FreePhysicalMemory; Get-CimInstance Win32_OperatingSystem | Select-Object -ExpandProperty TotalVisibleMemorySize"');
            if ($output) {
                $lines = array_values(array_filter(explode("\n", trim($output))));
                if (count($lines) >= 2) {
                    $free = (int)trim($lines[0]);
                    $total = (int)trim($lines[1]);
                    $used = $total - $free;
                    $percent = round(($used / $total) * 100, 2);
                    return $this->response->setJSON(['success' => true, 'total' => $total, 'used' => $used, 'percent' => $percent]);
                }
            }
        } else {
            $free = shell_exec('free');
            $free = (string)trim($free);
            $free_arr = explode("\n", $free);
            $mem = explode(" ", $free_arr[1]);
            $mem = array_filter($mem);
            $mem = array_merge($mem);
            $total = $mem[1];
            $used = $mem[2];
            $percent = round(($used / $total) * 100, 2);
            return $this->response->setJSON(['success' => true, 'total' => $total, 'used' => $used, 'percent' => $percent]);
        }
        return $this->response->setJSON(['success' => false, 'message' => 'Could not get RAM usage']);
    }

    public function sendRamNotification()
    {
        $percent = (float) $this->request->getPost('ram_percent');
        $threshold = $this->settingsModel->getRamThreshold();

        // Get telegram settings from database
        $botToken = $this->settingsModel->getSetting('telegram_bot_token');
        $chatId = $this->settingsModel->getSetting('telegram_chat_id');
        $ramNotificationsEnabled = $this->settingsModel->getSetting('telegram_ram_notifications_enabled') ?? '0';

        if ($ramNotificationsEnabled !== '1') {
            return $this->response->setJSON(['success' => false, 'message' => 'RAM notifications are disabled'])->setStatusCode(400);
        }

        if (empty($botToken) || empty($chatId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Bot token dan Chat ID harus dikonfigurasi di Settings'])->setStatusCode(400);
        }

        try {
            $client = \Config\Services::curlrequest();

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $message = '⚠️ *Peringatan RAM Tinggi*: Penggunaan RAM telah mencapai *' . number_format($percent, 2) . '*% (threshold: *' . $threshold . '*%)' . "\n\n" .
                'Perintah tersedia:' . "\n" .
                '/status - Status bot' . "\n" .
                '/ram - Status RAM' . "\n" .
                '/stopstream - Hentikan stream' . "\n" .
                '/help - Bantuan';
            $payload = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ];

            $response = $client->post($url, [
                'timeout' => 10,
                'json' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            if ($statusCode === 200 && isset($body['ok']) && $body['ok'] === true) {
                return $this->response->setJSON(['success' => true, 'message' => 'Notifikasi Telegram berhasil dikirim!']);
            }

            $errorMsg = $body['description'] ?? $body['error_description'] ?? 'Gagal mengirim notifikasi';
            return $this->response->setJSON(['success' => false, 'message' => $errorMsg])->setStatusCode(400);
        } catch (\Exception $e) {
            log_message('error', 'RAM notification error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => client_error($e, 'Error')])->setStatusCode(500);
        }
    }

    public function checkRamAndStopStreams()
    {
        $percent = (float) $this->request->getPost('ram_percent');
        $threshold = $this->settingsModel->getRamThreshold();

        // Get settings from database
        $ramStopStreamsEnabled = $this->settingsModel->getSetting('ram_stop_streams_enabled') ?? '0';
        $ramNotificationsEnabled = $this->settingsModel->getSetting('telegram_ram_notifications_enabled') ?? '0';
        $botToken = $this->settingsModel->getSetting('telegram_bot_token');
        $chatId = $this->settingsModel->getSetting('telegram_chat_id');

        if ($ramStopStreamsEnabled !== '1') {
            return $this->response->setJSON(['success' => false, 'message' => 'Auto-stop streams is disabled'])->setStatusCode(400);
        }

        if ($percent <= $threshold) {
            return $this->response->setJSON(['success' => false, 'message' => 'RAM usage is below threshold'])->setStatusCode(400);
        }

        try {
            // Get all active streams
            $streamModel = new \App\Models\StreamModel();
            $activeStreams = $streamModel->whereIn('status', ['starting', 'running', 'paused'])->findAll();

            $stoppedCount = 0;
            $errors = [];

            foreach ($activeStreams as $stream) {
                try {
                    // Call the stop API for each stream
                    $client = \Config\Services::curlrequest();
                    $baseUrl = $this->settingsModel->getSetting('bot_bridge_url') ?: 'http://127.0.0.1:8766';
                    $token = trim((string) $this->settingsModel->getSetting('bot_bridge_token'));

                    if ($baseUrl && $token) {
                        $response = $client->post($baseUrl . '/api/stream/stop/' . $stream['id'], [
                            'timeout' => 10,
                            'headers' => [
                                'Authorization' => 'Bearer ' . $token,
                                'Accept' => 'application/json',
                            ]
                        ]);

                        if ($response->getStatusCode() === 200) {
                            $stoppedCount++;
                        } else {
                            $errors[] = "Stream #{$stream['id']}: " . $response->getBody();
                        }
                    } else {
                        $errors[] = "Stream #{$stream['id']}: Bot bridge not configured";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Stream #{$stream['id']}: " . $e->getMessage();
                }
            }

            // Send Telegram notification if enabled
            $telegramSent = false;
            if ($ramNotificationsEnabled === '1' && !empty($botToken) && !empty($chatId)) {
                try {
                    $client = \Config\Services::curlrequest();
                    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                    $message = '🛑 *Auto-Stop Triggered*: RAM usage reached *' . number_format($percent, 2) . '*% (threshold: *' . $threshold . '*%)\n\nStopped ' . $stoppedCount . ' active stream(s) to prevent system instability.';
                    $payload = [
                        'chat_id' => $chatId,
                        'text' => $message,
                        'parse_mode' => 'Markdown'
                    ];
                    $response = $client->post($url, ['timeout' => 10, 'json' => $payload]);
                    $body = json_decode($response->getBody(), true);
                    $telegramSent = ($response->getStatusCode() === 200 && isset($body['ok']) && $body['ok'] === true);
                } catch (\Exception $e) {
                    log_message('error', 'RAM auto-stop telegram error: ' . $e->getMessage());
                }
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => "Auto-stop executed: {$stoppedCount} stream(s) stopped" . ($errors ? " | Errors: " . implode('; ', $errors) : ''),
                'stopped_count' => $stoppedCount,
                'errors' => $errors,
                'telegram_sent' => $telegramSent
            ]);
        } catch (\Exception $e) {
            log_message('error', 'RAM auto-stop error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => client_error($e, 'Error')])->setStatusCode(500);
        }
    }

    public function checkPythonHealth()
    {
        $baseUrl = BotBridge::normalizeUrl($this->settingsModel->getSetting('bot_bridge_url'));
        $token = trim((string) $this->settingsModel->getSetting('bot_bridge_token'));

        // Jika belum dikonfigurasi, coba auto-start terlebih dahulu
        if ($baseUrl === null || $token === '') {
            $botBridge = new BotBridge();
            $startup = $botBridge->ensureRunning($this->settingsModel);
            if (!$startup['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'running' => false,
                    'message' => 'BayzBot tidak dikonfigurasi dengan baik. ' . ($startup['message'] ?? 'Konfigurasi wajib diisi.'),
                ])->setStatusCode(400);
            }
            // Ambil konfigurasi yang baru diset oleh ensureRunning
            $baseUrl = BotBridge::normalizeUrl($this->settingsModel->getSetting('bot_bridge_url'));
            $token = trim((string) $this->settingsModel->getSetting('bot_bridge_token'));
        }

        $botBridge = new BotBridge();

        // Cek apakah sudah berjalan
        $health = $botBridge->health($baseUrl, $token);
        if ($health['success']) {
            return $this->response->setJSON([
                'success' => true,
                'running' => true,
                'message' => 'Python BayzBot sedang berjalan',
                'uptime' => $health['uptime'] ?? null,
            ]);
        }

        // Health check gagal = BayzBot mungkin belum berjalan atau sedang booting
        // Coba auto-start, tapi jangan kill process jika health check gagal
        $startup = $botBridge->ensureRunning($this->settingsModel);

        // Jika auto-start berhasil dimulai, berikan waktu untuk booting (~3 detik)
        if (!$startup['success']) {
            return $this->response->setJSON([
                'success' => false,
                'running' => false,
                'message' => $startup['message'] ?? 'Gagal menjalankan BayzBot Python',
            ])->setStatusCode(503);
        }

        // Auto-start berhasil, beri waktu untuk booting
        if ($startup['started'] ?? false) {
            return $this->response->setJSON([
                'success' => true,
                'running' => false,
                'booting' => true,
                'message' => 'BayzBot Python sedang dinyalakan di background. Silakan tunggu beberapa detik...',
            ])->setStatusCode(202);
        }

        // Sudah berjalan tapi health check gagal = error sementara
        return $this->response->setJSON([
            'success' => false,
            'running' => false,
            'message' => 'BayzBot Python tidak merespons. Cek koneksi atau restart aplikasi.',
        ])->setStatusCode(503);
    }

    private function stopPythonProcess()
    {
        // Deprecated: tidak lagi digunakan. Health check timeout bukan alasan untuk kill process.
        // Process harus dihentikan hanya jika user explicitly meminta atau ada error fatal yang persistent.
        return;
    }

    public function stopPython()
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Fungsi stop Python hanya didukung di Windows.',
            ])->setStatusCode(400);
        }

        $root = rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $windowsDirectory = rtrim((string) getenv('WINDIR'), '\\/');
        $powershell = $windowsDirectory . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

        if (!is_file($powershell)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'PowerShell tidak ditemukan; proses BayzBot tidak dihentikan.',
            ])->setStatusCode(500);
        }

        $rootLiteral = str_replace("'", "''", $root);
        $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$root = '__ROOT_PATH__'

$all = @(Get-CimInstance Win32_Process)
$byId = @{}
foreach ($p in $all) { $byId[[int]$p.ProcessId] = $p }

# Proses ini dan induknya (php/server web) tidak boleh ikut dihentikan.
$protected = @{}
$cursor = [int]$PID
while ($cursor -gt 0 -and $byId.ContainsKey($cursor) -and -not $protected.ContainsKey($cursor)) {
    $protected[$cursor] = $true
    $cursor = [int]$byId[$cursor].ParentProcessId
}

$wsPort = 8765
$httpPort = 8766
$cfgPath = Join-Path $root 'gui_config.json'
if (Test-Path -LiteralPath $cfgPath) {
    try {
        $cfg = Get-Content -LiteralPath $cfgPath -Raw -Encoding UTF8 | ConvertFrom-Json
        if ($cfg.ws_port -as [int]) { $wsPort = [int]$cfg.ws_port }
        if ($cfg.http_api_port -as [int]) { $httpPort = [int]$cfg.http_api_port }
    } catch { }
}
$ports = @($wsPort, $httpPort)

# Cocokkan hanya skrip di folder INI (path lengkap), supaya backend.py / run_*.bat
# milik proyek lain dengan nama yang sama tidak ikut dihentikan. Nama lama tetap dikenali.
$rootPrefix = $root.TrimEnd('\') + '\'
$backendPaths = @('backend.py', 'backend_gui.py') | ForEach-Object { $rootPrefix + $_ }
$launcherPaths = @('run_headless.bat', 'run_gui.bat', 'run_backend_headless.bat', 'run_backend_gui.bat') | ForEach-Object { $rootPrefix + $_ }
function Test-Own([string]$line, [string[]]$paths) {
    foreach ($path in $paths) {
        if ($line.IndexOf($path, [StringComparison]::OrdinalIgnoreCase) -ge 0) { return $true }
    }
    return $false
}

$targets = @{}
foreach ($p in $all) {
    $id = [int]$p.ProcessId
    if ($protected.ContainsKey($id)) { continue }
    $line = [string]$p.CommandLine
    if ([string]::IsNullOrEmpty($line)) { continue }
    $name = [string]$p.Name
    $isPython = $name -match '^(python|pythonw|py)\.exe$'
    $isCmd = $name -ieq 'cmd.exe'
    if ($isPython -and (Test-Own $line $backendPaths)) {
        $targets[$id] = $p
    } elseif ($isCmd -and (Test-Own $line $launcherPaths)) {
        $targets[$id] = $p
    }
}

# Python yang memegang port WebSocket / HTTP API BayzBot.
try {
    $listeners = @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $ports -contains [int]$_.LocalPort })
    foreach ($c in $listeners) {
        $id = [int]$c.OwningProcess
        if ($id -le 4 -or $protected.ContainsKey($id) -or -not $byId.ContainsKey($id)) { continue }
        if ($byId[$id].Name -match '^(python|pythonw)\.exe$') { $targets[$id] = $byId[$id] }
    }
} catch { }

# Proses anak (cek waktu pembuatan agar tidak salah kena PID yang dipakai ulang).
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

$foundIds = @($targets.Keys | ForEach-Object { [int]$_ } | Sort-Object -Unique)
if ($foundIds.Count -eq 0) {
    [pscustomobject]@{ success = $true; found = @(); stopped = @(); remaining = @(); errors = @() } |
        ConvertTo-Json -Compress
    exit 0
}

$stoppedIds = @()
$errors = @()
foreach ($processId in $foundIds) {
    try {
        Stop-Process -Id ([int]$processId) -Force -ErrorAction Stop
        $stoppedIds += [int]$processId
    } catch {
        if (Get-Process -Id ([int]$processId) -ErrorAction SilentlyContinue) {
            $errors += "PID $processId`: $($_.Exception.Message)"
        } else {
            $stoppedIds += [int]$processId
        }
    }
}

$stillRunning = @()
for ($attempt = 0; $attempt -lt 10; $attempt++) {
    Start-Sleep -Milliseconds 300
    $stillRunning = @($foundIds | Where-Object { Get-Process -Id $_ -ErrorAction SilentlyContinue })
    if ($stillRunning.Count -eq 0) { break }
}

[pscustomobject]@{
    success = ($stillRunning.Count -eq 0)
    found = @($foundIds)
    stopped = @($stoppedIds | Sort-Object -Unique)
    remaining = @($stillRunning | Sort-Object -Unique)
    errors = @($errors)
} | ConvertTo-Json -Compress
POWERSHELL;
        $script = str_replace('__ROOT_PATH__', $rootLiteral, $script);
        // Skrip terlalu panjang untuk -EncodedCommand (batas command line cmd.exe 8191
        // karakter), jadi ditulis ke file .ps1 sementara lalu dijalankan dengan -File.
        $cacheDirectory = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheDirectory) && !@mkdir($cacheDirectory, 0775, true) && !is_dir($cacheDirectory)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tidak dapat menyiapkan folder sementara untuk menghentikan proses BayzBot.',
            ])->setStatusCode(500);
        }
        $runId = bin2hex(random_bytes(6));
        $scriptPath = $cacheDirectory . DIRECTORY_SEPARATOR . 'bayzbot-stop-' . $runId . '.ps1';
        $errorPath = $cacheDirectory . DIRECTORY_SEPARATOR . 'bayzbot-stop-' . $runId . '.err';
        // BOM UTF-8 agar Windows PowerShell 5.1 membaca path non-ASCII dengan benar.
        if (@file_put_contents($scriptPath, "\xEF\xBB\xBF" . $script, LOCK_EX) === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tidak dapat menulis skrip sementara untuk menghentikan proses BayzBot.',
            ])->setStatusCode(500);
        }

        $stderrText = '';
        try {
            $command = '"' . $powershell . '" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "'
                . $scriptPath . '" 2>"' . $errorPath . '"';
            $output = shell_exec($command);
            $stderrText = trim((string) @file_get_contents($errorPath));
        } finally {
            @unlink($scriptPath);
            @unlink($errorPath);
        }
        $json = null;
        foreach (preg_split('/\R/', (string) $output) as $line) {
            $line = trim($line);
            if ($line !== '' && $line[0] === '{') {
                $json = $line;
                break;
            }
        }
        $result = $json !== null ? json_decode($json, true) : null;
        if (!is_array($result) || !isset($result['success'])) {
            $rawOutput = trim((string) $output);
            log_message('error', 'BayzBot process stop returned an invalid response. stdout={stdout}; stderr={stderr}', [
                'stdout' => mb_substr($rawOutput, 0, 800),
                'stderr' => mb_substr($stderrText, 0, 800),
            ]);
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tidak dapat memverifikasi penghentian seluruh proses BayzBot. '
                    . ($rawOutput === '' ? 'PowerShell tidak mengembalikan output. ' : '')
                    . 'Detail ada di writable/logs/log-' . date('Y-m-d') . '.log.',
                'detail' => mb_substr($rawOutput !== '' ? $rawOutput : $stderrText, 0, 400),
            ])->setStatusCode(500);
        }

        if (!$result['success']) {
            log_message('error', 'Sebagian proses BayzBot masih berjalan: ' . implode(', ', $result['remaining'] ?? []));
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Sebagian proses BayzBot gagal dihentikan. PID tersisa: '
                    . implode(', ', $result['remaining'] ?? [])
                    . (!empty($result['errors']) ? ' Detail: ' . implode('; ', $result['errors']) : ''),
                'found_pids' => $result['found'] ?? [],
                'stopped_pids' => $result['stopped'] ?? [],
                'remaining_pids' => $result['remaining'] ?? [],
            ])->setStatusCode(500);
        }

        $stoppedPids = $result['stopped'] ?? [];
        $message = empty($result['found'])
            ? 'Tidak ada proses BayzBot yang sedang berjalan.'
            : 'Seluruh proses BayzBot berhasil dihentikan. PID: ' . implode(', ', $stoppedPids);
        log_message('info', $message);
        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'found_pids' => $result['found'] ?? [],
            'stopped_pids' => $stoppedPids,
            'remaining_pids' => [],
        ]);
    }

    public function getPythonLogs()
    {
        $lines = (int) ($this->request->getGet('lines') ?? 100);
        $lines = max(1, min($lines, 1000));

        $logDir = WRITEPATH . 'logs' . DIRECTORY_SEPARATOR;

        if (!is_dir($logDir)) {
            return $this->response->setJSON([
                'success' => false,
                'logs' => [],
                'message' => 'Folder logs tidak ditemukan',
            ]);
        }

        $files = @glob($logDir . 'bayzbot-*.log', GLOB_NOSORT);
        if (!is_array($files) || $files === []) {
            return $this->response->setJSON([
                'success' => true,
                'logs' => [],
                'message' => 'Log file Python belum ada',
                'log_dir' => $logDir,
            ]);
        }

        $stdoutLog = $logDir . 'bayzbot-stdout.log';
        $stdoutSize = is_file($stdoutLog) ? filesize($stdoutLog) : false;
        if ($stdoutSize !== false && $stdoutSize > 0) {
            $latestLog = $stdoutLog;
        } else {
            $nonEmptyFiles = array_values(array_filter(
                $files,
                static fn($file) => is_file($file) && filesize($file) > 0
            ));
            if ($nonEmptyFiles === []) {
                return $this->response->setJSON([
                    'success' => true,
                    'logs' => [],
                    'message' => 'Log BayzBot masih kosong. Periksa status backend atau jalankan kembali BayzBot.',
                    'log_file' => basename($stdoutLog),
                ]);
            }
            usort($nonEmptyFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
            $latestLog = reset($nonEmptyFiles);
        }

        if (!is_file($latestLog) || !is_readable($latestLog)) {
            return $this->response->setJSON([
                'success' => false,
                'logs' => [],
                'message' => 'Log file tidak bisa dibaca',
            ]);
        }

        $fileSize = filesize($latestLog);
        if ($fileSize === false) {
            return $this->response->setJSON([
                'success' => false,
                'logs' => [],
                'message' => 'Gagal mendapatkan ukuran log file',
            ]);
        }

        $handle = @fopen($latestLog, 'r');
        if ($handle === false) {
            return $this->response->setJSON([
                'success' => false,
                'logs' => [],
                'message' => 'Gagal membuka log file',
            ]);
        }

        $logLines = [];
        $totalLines = 0;
        while (($line = fgets($handle)) !== false) {
            $totalLines++;
        }

        $startLine = max(0, $totalLines - $lines);
        rewind($handle);

        $currentLine = 0;
        while (($line = fgets($handle)) !== false) {
            if ($currentLine >= $startLine) {
                $logLines[] = rtrim($line, "\r\n");
            }
            $currentLine++;
        }
        fclose($handle);

        return $this->response->setJSON([
            'success' => true,
            'logs' => $logLines,
            'total_lines' => $totalLines,
            'lines_returned' => count($logLines),
            'log_file' => basename($latestLog),
            'file_size' => $fileSize,
        ]);
    }

    public function resetDatabase()
    {
        try {
            $db = \Config\Database::connect();
            $db->disableForeignKeyChecks();
            $db->table('streams')->truncate();
            $db->table('playlist_songs')->truncate();
            $db->table('playlists')->truncate();
            $db->table('proxies')->truncate();
            $db->table('profiles')->truncate();
            $db->table('logs')->truncate();
            $db->enableForeignKeyChecks();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Database berhasil di-reset (data profil, proxy, playlist, stream, dan log dikosongkan).',
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => client_error($e, 'Gagal mereset database'),
            ])->setStatusCode(500);
        }
    }
}
