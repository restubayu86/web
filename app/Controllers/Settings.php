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
            'ram_threshold_percent' => $this->settingsModel->getSetting('ram_threshold_percent') ?? '85',
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

    public function tutorial()
    {
        return view('tutorial');
    }

    public function save()
    {
        $apiToken = $this->request->getPost('api_token');
        $telegramBotToken = $this->request->getPost('telegram_bot_token');
        $telegramChatId = $this->request->getPost('telegram_chat_id');
        $telegramNotificationsEnabled = $this->request->getPost('telegram_notifications_enabled') ? '1' : '0';
            $telegramRamNotificationsEnabled = $this->request->getPost('telegram_ram_notifications_enabled') ? '1' : '0';
            $ramThresholdPercent = $this->request->getPost('ram_threshold_percent') ?? '85';
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
            $this->settingsModel->setSetting('ram_threshold_percent', $ramThresholdPercent);
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
            $errors = array_filter([$localUrlError ?? null, $botBridgeUrlError ?? null]);
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
                $cmd = 'wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value';
                $output = shell_exec($cmd);
                if ($output) {
                    preg_match_all('/(\d+)/', $output, $matches);
                    if (count($matches[0]) >= 2) {
                        $free = (int)$matches[0][0];
                        $total = (int)$matches[0][1];
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
                    $threshold = (float) $this->request->getPost('ram_threshold');
            
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
                        $message = '⚠️ *Peringatan RAM Tinggi*: Penggunaan RAM telah mencapai *' . number_format($percent, 2) . '*% (threshold: *' . $threshold . '*%)';
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
                                $threshold = (float) $this->request->getPost('ram_threshold');
            
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
        $backendPath = $root . 'backend_gui.py';
        $launcherPath = $root . 'run_backend_headless.bat';
        $windowsDirectory = rtrim((string) getenv('WINDIR'), '\\/');
        $powershell = $windowsDirectory . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

        if (!is_file($powershell)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'PowerShell tidak ditemukan; proses BayzBot tidak dihentikan.',
            ])->setStatusCode(500);
        }

        $backendLiteral = str_replace("'", "''", $backendPath);
        $launcherLiteral = str_replace("'", "''", $launcherPath);
        $script = <<<'POWERSHELL'
$backendPath = '__BACKEND_PATH__'
$launcherPath = '__LAUNCHER_PATH__'
$all = @(Get-CimInstance Win32_Process -ErrorAction Stop)
$targets = @{}
foreach ($process in $all) {
    $commandLine = [string]$process.CommandLine
    if ($commandLine.IndexOf($backendPath, [StringComparison]::OrdinalIgnoreCase) -ge 0 -or
        $commandLine.IndexOf($launcherPath, [StringComparison]::OrdinalIgnoreCase) -ge 0) {
        $targets[[int]$process.ProcessId] = $process
    }
}

$changed = $true
while ($changed) {
    $changed = $false
    foreach ($process in $all) {
        $processId = [int]$process.ProcessId
        $parentId = [int]$process.ParentProcessId
        if (-not $targets.ContainsKey($processId) -and $targets.ContainsKey($parentId)) {
            $targets[$processId] = $process
            $changed = $true
        }
    }
}

$foundIds = @($targets.Keys | ForEach-Object { [int]$_ })
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
        $errors += "PID $processId`: $($_.Exception.Message)"
    }
}

$stillRunning = @()
for ($attempt = 0; $attempt -lt 10; $attempt++) {
    Start-Sleep -Milliseconds 300
    $stillRunning = @(Get-CimInstance Win32_Process -ErrorAction Stop |
        Where-Object { $targets.ContainsKey([int]$_.ProcessId) } |
        ForEach-Object { [int]$_.ProcessId })
    if ($stillRunning.Count -eq 0) { break }
}

[pscustomobject]@{
    success = ($stillRunning.Count -eq 0)
    found = @($foundIds | Sort-Object -Unique)
    stopped = @($stoppedIds | Sort-Object -Unique)
    remaining = @($stillRunning | Sort-Object -Unique)
    errors = @($errors)
} | ConvertTo-Json -Compress
POWERSHELL;
        $script = str_replace(
            ['__BACKEND_PATH__', '__LAUNCHER_PATH__'],
            [$backendLiteral, $launcherLiteral],
            $script
        );
        $utf16Script = iconv('UTF-8', 'UTF-16LE', $script);
        if ($utf16Script === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tidak dapat menyiapkan perintah untuk menghentikan proses BayzBot.',
            ])->setStatusCode(500);
        }

        $command = '"' . $powershell . '" -NoProfile -NonInteractive -EncodedCommand '
            . base64_encode($utf16Script) . ' 2>&1';
        $output = shell_exec($command);
        $result = is_string($output) ? json_decode(trim($output), true) : null;
        if (!is_array($result) || !isset($result['success'])) {
            log_message('error', 'BayzBot process stop returned an invalid response: ' . (string) $output);
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tidak dapat memverifikasi penghentian seluruh proses BayzBot.',
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
                static fn ($file) => is_file($file) && filesize($file) > 0
            ));
            if ($nonEmptyFiles === []) {
                return $this->response->setJSON([
                    'success' => true,
                    'logs' => [],
                    'message' => 'Log BayzBot masih kosong. Periksa status backend atau jalankan kembali BayzBot.',
                    'log_file' => basename($stdoutLog),
                ]);
            }
            usort($nonEmptyFiles, fn ($a, $b) => filemtime($b) <=> filemtime($a));
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
}
