<?php

namespace App\Controllers;

use App\Models\ProxyModel;
use App\Models\SettingsModel;

/**
 * Proxy manager (local DB + Dolphin Anty cloud).
 *
 * PENTING: tabel lokal butuh kolom `dolphin_id` (id proxy di Dolphin).
 * ID lokal (auto-increment) TIDAK sama dengan ID Dolphin, jadi semua
 * DELETE/PATCH ke Dolphin harus memakai dolphin_id, bukan $id lokal.
 *
 *   ALTER TABLE proxies
 *     ADD COLUMN dolphin_id INT UNSIGNED NULL AFTER id,
 *     ADD INDEX idx_proxies_dolphin_id (dolphin_id);
 *
 * Dan tambahkan 'dolphin_id' ke $allowedFields di App\Models\ProxyModel.
 */
class Proxies extends BaseController
{
    private $proxyModel;
    private $settingsModel;
    private $dolphinApiUrl = 'https://dolphin-anty-api.com'; // satu-satunya tempat base URL
    private $dolphinApiToken;

    private const VALID_TYPES = ['http', 'socks4', 'socks5', 'ssh'];

    public function __construct()
    {
        $this->proxyModel = new ProxyModel();
        $this->settingsModel = new SettingsModel();

        // Get token from Settings database
        $this->dolphinApiToken = $this->getDolphinTokenFromSettings();
    }

    private function getDolphinTokenFromSettings()
    {
        try {
            $setting = $this->settingsModel->where('key', 'dolphin_api_token')->first();
            return $setting ? $setting['value'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Satu pintu untuk semua panggilan ke Dolphin.
     * Mengembalikan: ['ok' => bool, 'status' => int, 'body' => array|null, 'message' => string]
     * Retry sekali kalau kena rate limit (429).
     */
    private function dolphin(string $method, string $path, array $options = []): array
    {
        if (empty($this->dolphinApiToken)) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'message' => 'Dolphin API token not configured'];
        }

        $options['headers'] = array_merge([
            'Authorization' => 'Bearer ' . $this->dolphinApiToken,
            'Accept'        => 'application/json',
        ], $options['headers'] ?? []);
        $options['timeout']     = $options['timeout'] ?? 15;
        $options['http_errors'] = false;

        $response = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = \Config\Services::curlrequest()->request(strtoupper($method), $this->dolphinApiUrl . $path, $options);
            } catch (\Throwable $e) {
                log_message('error', "Dolphin {$method} {$path} failed: " . $e->getMessage());
                return ['ok' => false, 'status' => 0, 'body' => null, 'message' => client_error($e, 'Dolphin API unreachable')];
            }

            if ($response->getStatusCode() === 429 && $attempt === 0) {
                sleep(2);
                continue;
            }
            break;
        }

        $status = $response->getStatusCode();
        $raw    = (string) $response->getBody();
        $body   = json_decode($raw, true);
        $ok     = $status >= 200 && $status < 300;

        $message = 'HTTP ' . $status;
        if (is_array($body)) {
            $message = $body['message'] ?? $body['error'] ?? $message;
            if (is_array($message)) {
                $message = json_encode($message);
            }
        } elseif ($raw !== '') {
            $message .= ' - ' . substr(strip_tags($raw), 0, 120);
        }

        if (!$ok) {
            log_message('error', "Dolphin {$method} {$path} => {$status}: " . substr($raw, 0, 300));
        }

        return ['ok' => $ok, 'status' => $status, 'body' => is_array($body) ? $body : null, 'message' => (string) $message];
    }

    /** "host:port" -> [host, port]. Pakai titik dua TERAKHIR supaya IPv6 tidak rusak. */
    private function splitProxyUrl(?string $url): array
    {
        $url = trim((string) $url);
        $pos = strrpos($url, ':');
        if ($pos === false) {
            return [$url, ''];
        }
        return [substr($url, 0, $pos), substr($url, $pos + 1)];
    }

    /**
     * Bentuk baris DB -> bentuk yang dipakai view (host/type/login/password).
     * Dipakai getAll() DAN getById() supaya modal Edit tidak kosong / salah protokol.
     */
    private function normalize(array $proxy): array
    {
        [$host, $urlPort] = $this->splitProxyUrl($proxy['proxy_url'] ?? '');

        $proxy['host'] = $host;
        $proxy['port'] = !empty($proxy['port']) ? $proxy['port'] : ($urlPort !== '' ? $urlPort : null);
        $proxy['type'] = strtolower($proxy['proxy_type'] ?? 'http');
        $proxy['login'] = $proxy['username'] ?? null;

        // backward compatibility
        $proxy['proxy_login']    = $proxy['username'] ?? null;
        $proxy['proxy_password'] = $proxy['password'] ?? null;
        $proxy['proxy_host']     = $proxy['host'];
        $proxy['proxy_port']     = $proxy['port'];
        $proxy['profiles_count'] = $proxy['browser_profiles_count'] ?? 0;

        return $proxy;
    }

    /**
     * Tes proxy + ambil negara EXIT IP-nya.
     * Satu request ke ip-api LEWAT proxy sudah memberi: koneksi hidup + IP + negara
     * (rate limit ip-api dihitung per IP proxy, bukan IP server).
     * Fallback ke httpbin kalau ip-api gagal (negara tidak diketahui).
     *
     * @return array{ok:bool, ip:?string, country:?string, code:?string, message:string}
     */
    private function probeProxy(string $proxyUrl): array
    {
        $opts = ['timeout' => 12, 'proxy' => $proxyUrl, 'verify' => false, 'http_errors' => false];
        $lastError = 'Connection failed';

        try {
            $res = \Config\Services::curlrequest()->get('http://ip-api.com/json?fields=status,message,country,countryCode,query', $opts);
            if ($res->getStatusCode() === 200) {
                $j = json_decode((string) $res->getBody(), true);
                if (($j['status'] ?? '') === 'success') {
                    return [
                        'ok'      => true,
                        'ip'      => $j['query'] ?? null,
                        'country' => $j['country'] ?? null,
                        'code'    => $j['countryCode'] ?? null,
                        'message' => 'Proxy is working',
                    ];
                }
            } else {
                $lastError = 'Connection failed with status ' . $res->getStatusCode();
            }
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
        }

        try {
            $res = \Config\Services::curlrequest()->get('https://httpbin.org/ip', $opts);
            if ($res->getStatusCode() === 200) {
                $j  = json_decode((string) $res->getBody(), true);
                $ip = isset($j['origin']) ? trim(explode(',', $j['origin'])[0]) : null;
                return ['ok' => true, 'ip' => $ip, 'country' => null, 'code' => null, 'message' => 'Proxy is working (country unknown)'];
            }
            $lastError = 'Connection failed with status ' . $res->getStatusCode();
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
        }

        return ['ok' => false, 'ip' => null, 'country' => null, 'code' => null, 'message' => $lastError];
    }

    /** "FR" -> "France". Nama yang sudah panjang dibiarkan; gagal/tanpa ext-intl -> apa adanya. */
    private function countryFullName(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z]{2}$/', $value) && class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('und_' . strtoupper($value), 'en');
            if ($name !== '' && strcasecmp($name, $value) !== 0) {
                return $name;
            }
        }
        return $value;
    }

    public function index()
    {
        $data = [
            'proxies' => $this->proxyModel->findAll(),
        ];
        return view('proxies', $data);
    }

    // ------------------------------------------------------------------
    // API Endpoints
    // ------------------------------------------------------------------

    public function getAll()
    {
        $proxies = array_map([$this, 'normalize'], $this->proxyModel->findAll());

        return $this->response->setJSON([
            'success' => true,
            'data' => $proxies,
            'count' => count($proxies),
        ]);
    }

    public function getDolphinOptions()
    {
        $page = 1;
        $lastPage = 1;
        $proxies = [];

        do {
            $res = $this->dolphin('GET', '/proxy?limit=100&page=' . $page . '&query=&sortBy=name&order=ASC');
            if (!$res['ok'] || !isset($res['body']['data']) || !is_array($res['body']['data'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to load proxies from Dolphin{anty}: ' . $res['message'],
                ])->setStatusCode($res['status'] ?: 502);
            }

            foreach ($res['body']['data'] as $proxy) {
                if (empty($proxy['id'])) {
                    continue;
                }
                $proxies[] = [
                    'id' => (int) $proxy['id'],
                    'name' => (string) ($proxy['name'] ?? ''),
                    'host' => (string) ($proxy['host'] ?? ''),
                    'port' => (int) ($proxy['port'] ?? 0),
                    'type' => (string) ($proxy['type'] ?? 'http'),
                    'lastCheck' => [
                        'status' => (bool) ($proxy['lastCheck']['status'] ?? false),
                        'ip' => (string) ($proxy['lastCheck']['ip'] ?? ''),
                        'country' => (string) ($proxy['lastCheck']['country'] ?? ''),
                        'timezone' => (string) ($proxy['lastCheck']['timezone'] ?? ''),
                    ],
                ];
            }

            $lastPage = max(1, (int) ($res['body']['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage && $page <= 100);

        return $this->response->setJSON([
            'success' => true,
            'data' => $proxies,
        ]);
    }

    public function assignToProfile($profileId)
    {
        $profileId = (int) $profileId;
        $proxyValue = $this->request->getPost('proxy_id');
        $clearProxy = $proxyValue === 'none';
        $proxyId = $clearProxy ? null : filter_var($proxyValue, FILTER_VALIDATE_INT);
        if ($profileId < 1 || (!$clearProxy && ($proxyId === false || $proxyId < 1))) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Select a valid Dolphin proxy or choose No proxy.',
            ])->setStatusCode(400);
        }

        $res = $this->dolphin('PATCH', '/browser_profiles/' . $profileId, [
            'json' => ['proxy' => $clearProxy ? null : ['id' => $proxyId]],
        ]);
        if (!$res['ok']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to change profile proxy: ' . $res['message'],
            ])->setStatusCode($res['status'] >= 400 ? $res['status'] : 502);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Profile proxy updated.',
        ]);
    }

    public function testDolphinProxy($proxyId)
    {
        $proxyId = (int) $proxyId;
        if ($proxyId < 1) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Valid Dolphin proxy ID is required.',
            ])->setStatusCode(400);
        }

        $res = $this->dolphin('GET', '/proxy?ids%5B%5D=' . $proxyId);
        if (!$res['ok']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to load proxy from Dolphin{anty}: ' . $res['message'],
            ])->setStatusCode($res['status'] >= 400 ? $res['status'] : 502);
        }

        $proxyData = $res['body']['data'] ?? null;
        if (is_array($proxyData) && array_is_list($proxyData)) {
            $proxyData = $proxyData[0] ?? null;
        }
        if (!is_array($proxyData) || (int) ($proxyData['id'] ?? 0) !== $proxyId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Proxy not found in Dolphin{anty}.',
            ])->setStatusCode(404);
        }

        $type = strtolower((string) ($proxyData['type'] ?? 'http'));
        if ($type === 'ssh') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'SSH proxies cannot be tested by this connection checker.',
            ])->setStatusCode(400);
        }

        $host = (string) ($proxyData['host'] ?? '');
        $port = (int) ($proxyData['port'] ?? 0);
        if ($host === '' || $port < 1 || $port > 65535) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Proxy host or port is invalid.',
            ])->setStatusCode(422);
        }

        $auth = '';
        if (!empty($proxyData['login']) && !empty($proxyData['password'])) {
            $auth = rawurlencode((string) $proxyData['login']) . ':' . rawurlencode((string) $proxyData['password']) . '@';
        }
        $probe = $this->probeProxy($type . '://' . $auth . $host . ':' . $port);

        return $this->response->setJSON([
            'success' => $probe['ok'],
            'message' => $probe['message'],
            'ip' => $probe['ip'],
            'country' => $probe['country'] ?? 'Unknown',
            'country_code' => $probe['code'],
        ])->setStatusCode($probe['ok'] ? 200 : 400);
    }

    public function getById($id)
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Proxy not found',
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $this->normalize($proxy),
        ]);
    }

    public function create()
    {
        $proxyUrl  = trim((string) $this->request->getPost('proxy_url'));
        $proxyHost = trim((string) $this->request->getPost('proxy_host'));
        $proxyPort = trim((string) $this->request->getPost('proxy_port'));
        $proxyName = trim((string) $this->request->getPost('proxy_name'));
        $type      = strtolower(trim((string) ($this->request->getPost('proxy_type') ?? 'http')));

        // View selalu mengirim proxy_url = "host:port" (bisa jadi ":" saja) -> validasi host & port nyata.
        if ($proxyUrl !== '') {
            [$urlHost, $urlPort] = $this->splitProxyUrl($proxyUrl);
            $proxyHost = $proxyHost !== '' ? $proxyHost : $urlHost;
            $proxyPort = $proxyPort !== '' ? $proxyPort : $urlPort;
        }

        if ($proxyHost === '' || !ctype_digit($proxyPort) || (int) $proxyPort < 1 || (int) $proxyPort > 65535) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Valid Host and Port are required',
            ])->setStatusCode(400);
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            $type = 'http';
        }

        $proxyUrl = $proxyHost . ':' . $proxyPort;

        $data = [
            'name' => $proxyName !== '' ? $proxyName : $proxyUrl,
            'proxy_url' => $proxyUrl,
            'proxy_type' => strtoupper($type),
            'port' => (int) $proxyPort,
            'country' => '',
            'username' => $this->request->getPost('proxy_login') ?: null,
            'password' => $this->request->getPost('proxy_password') ?: null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $localId = $this->proxyModel->insert($data);
        if (!$localId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to create proxy' . ($this->proxyModel->errors() ? ': ' . implode(', ', $this->proxyModel->errors()) : ''),
            ])->setStatusCode(500);
        }

        // Push ke Dolphin (kegagalan tidak membatalkan simpan lokal, tapi dilaporkan jujur)
        $sync = ['synced' => false, 'message' => 'Dolphin API token not configured'];
        if (!empty($this->dolphinApiToken)) {
            $res = $this->dolphin('POST', '/proxy', [
                'json' => [
                    'type'        => $type,
                    'host'        => $proxyHost,
                    'port'        => (int) $proxyPort,
                    'login'       => $data['username'],
                    'password'    => $data['password'],
                    'name'        => $data['name'],
                    'changeIpUrl' => '',
                    'provider'    => '',
                ],
            ]);

            $sync = ['synced' => $res['ok'], 'message' => $res['message']];

            if ($res['ok']) {
                // Bentuk respons create belum terdokumentasi di sini -> coba beberapa kemungkinan.
                $dolphinId = $res['body']['data']['id'] ?? $res['body']['id'] ?? $res['body']['proxyId'] ?? null;
                if ($dolphinId) {
                    $this->proxyModel->update($localId, ['dolphin_id' => (int) $dolphinId]);
                } else {
                    $sync['message'] = 'Synced, but Dolphin id not returned - press IMPORT once to link it';
                }
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $sync['synced'] ? 'Proxy created and synced to Dolphin' : 'Proxy saved locally (Dolphin: ' . $sync['message'] . ')',
            'dolphin' => $sync,
            'data' => $this->normalize($this->proxyModel->find($localId)),
        ])->setStatusCode(201);
    }

    public function testConnection()
    {
        $proxyUrl  = trim((string) $this->request->getPost('proxy_url'));
        $username  = $this->request->getPost('proxy_login');
        $password  = $this->request->getPost('proxy_password');
        $proxyType = strtolower($this->request->getPost('proxy_type') ?? 'http');

        [$host, $port] = $this->splitProxyUrl($proxyUrl);
        if ($host === '' || $port === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Host and port required']);
        }

        if ($proxyType === 'ssh') {
            return $this->response->setJSON(['success' => false, 'message' => 'SSH proxies cannot be tested from the server (curl has no SSH proxy support)']);
        }

        $auth = (!empty($username) && !empty($password)) ? rawurlencode($username) . ':' . rawurlencode($password) . '@' : '';
        $fullProxyUrl = "{$proxyType}://{$auth}{$proxyUrl}";

        $probe = $this->probeProxy($fullProxyUrl);

        return $this->response->setJSON([
            'success'      => $probe['ok'],
            'message'      => $probe['message'],
            'ip'           => $probe['ip'],
            'country'      => $probe['country'] ?? 'Unknown',
            'country_code' => $probe['code'],
        ]);
    }

    public function update($id)
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Proxy not found',
            ])->setStatusCode(404);
        }

        // View mengirim proxy_login / proxy_password / proxy_port; kolom DB: username / password / port.
        $aliases = [
            'name'       => ['name', 'proxy_name'],
            'proxy_url'  => ['proxy_url'],
            'proxy_type' => ['proxy_type'],
            'username'   => ['username', 'proxy_login'],
            'password'   => ['password', 'proxy_password'],
            'port'       => ['port', 'proxy_port'],
            'is_active'  => ['is_active'],
        ];

        $post = $this->request->getPost();
        $data = [];
        foreach ($aliases as $column => $keys) {
            foreach ($keys as $key) {
                if (isset($post[$key])) {
                    $data[$column] = $post[$key];
                    break;
                }
            }
        }

        if (empty($data)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No data to update',
            ])->setStatusCode(400);
        }

        if (isset($data['proxy_url'])) {
            [$host, $urlPort] = $this->splitProxyUrl($data['proxy_url']);
            if ($host === '') {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Host is required',
                ])->setStatusCode(400);
            }
            if (!isset($data['port']) && $urlPort !== '') {
                $data['port'] = $urlPort;
            }
        }
        if (isset($data['port'])) {
            $data['port'] = (int) $data['port'];
        }
        if (isset($data['proxy_type'])) {
            $t = strtolower($data['proxy_type']);
            $data['proxy_type'] = strtoupper(in_array($t, self::VALID_TYPES, true) ? $t : 'http');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (!$this->proxyModel->update($id, $data)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to update proxy',
            ])->setStatusCode(500);
        }

        // Sync ke Dolphin memakai dolphin_id (BUKAN id lokal)
        $sync = ['synced' => false, 'message' => 'Not linked to Dolphin (no dolphin_id)'];
        $merged = array_merge($proxy, $data);
        if (!empty($proxy['dolphin_id'])) {
            [$mHost] = $this->splitProxyUrl($merged['proxy_url']);
            $res = $this->dolphin('PATCH', '/proxy/' . (int) $proxy['dolphin_id'], [
                'json' => [
                    'type'     => strtolower($merged['proxy_type'] ?? 'http'),
                    'host'     => $mHost,
                    'port'     => (int) $merged['port'],
                    'login'    => $merged['username'],
                    'password' => $merged['password'],
                    'name'     => $merged['name'],
                ],
            ]);
            $sync = ['synced' => $res['ok'], 'message' => $res['message']];
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Proxy updated successfully',
            'dolphin' => $sync,
            'data' => $this->normalize($this->proxyModel->find($id)),
        ]);
    }

    /**
     * DELETE api/proxy/delete/{localId}[?force=1]
     *
     * - Ada dolphin_id  -> hapus di Dolphin dulu. Sukses/404 => lanjut hapus lokal.
     *                      Gagal (401/403/422/429/5xx/timeout) => lokal TIDAK dihapus
     *                      (kalau dihapus, IMPORT berikutnya memunculkannya lagi).
     * - Tanpa dolphin_id -> hanya hapus lokal (tidak menebak id di Dolphin).
     * - ?force=1         -> hapus lokal walau Dolphin gagal.
     */
    public function delete($id)
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Proxy not found',
            ])->setStatusCode(404);
        }

        $force  = (bool) $this->request->getGet('force');
        $dolphin = 'skipped';
        $note    = '';

        if (!empty($proxy['dolphin_id']) && !empty($this->dolphinApiToken)) {
            $res = $this->dolphin('DELETE', '/proxy/' . (int) $proxy['dolphin_id']);

            if ($res['ok']) {
                $dolphin = 'deleted';
            } elseif ($res['status'] === 404) {
                $dolphin = 'already_gone';
            } elseif (!$force) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Dolphin refused to delete: ' . $res['message'],
                    'dolphin_status' => $res['status'],
                ])->setStatusCode(502);
            } else {
                $dolphin = 'failed_forced';
                $note = ' (Dolphin: ' . $res['message'] . ')';
            }
        } elseif (!empty($proxy['dolphin_id'])) {
            if (!$force) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Dolphin API token not configured',
                ])->setStatusCode(400);
            }
            $dolphin = 'failed_forced';
            $note = ' (Dolphin token missing)';
        }

        if ($this->proxyModel->delete($id)) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Proxy deleted successfully' . $note,
                'dolphin' => $dolphin,
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to delete proxy',
        ])->setStatusCode(500);
    }

    public function test($id)
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Proxy not found',
            ])->setStatusCode(404);
        }

        try {
            $probe = $this->probeProxy($this->proxyModel->getProxyUrl($id));

            if ($probe['ok']) {
                $this->proxyModel->updateTestStatus($id, 'working');

                // Setelah tes berhasil: simpan negara terbaru supaya nama negara + bendera di kolom Name ikut berubah
                if (!empty($probe['country'])) {
                    $this->proxyModel->update($id, [
                        'country'    => $probe['country'],
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                $detail = trim(($probe['country'] ?? '') . ($probe['ip'] ? ' (' . $probe['ip'] . ')' : ''));

                return $this->response->setJSON([
                    'success'      => true,
                    'message'      => $probe['message'] . ($detail !== '' ? ' - ' . $detail : ''),
                    'status'       => 'working',
                    'country'      => $probe['country'],
                    'country_code' => $probe['code'],
                    'ip'           => $probe['ip'],
                ]);
            }

            $this->proxyModel->updateTestStatus($id, 'failed');
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Proxy test failed: ' . $probe['message'],
                'status'  => 'failed',
            ])->setStatusCode(400);
        } catch (\Exception $e) {
            $this->proxyModel->updateTestStatus($id, 'failed');
            return $this->response->setJSON([
                'success' => false,
                'message' => client_error($e, 'Proxy test error'),
                'status'  => 'failed',
            ])->setStatusCode(500);
        }
    }

    public function toggle($id)
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Proxy not found',
            ])->setStatusCode(404);
        }

        $newStatus = $proxy['is_active'] ? 0 : 1;
        if ($this->proxyModel->update($id, ['is_active' => $newStatus])) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Proxy status updated',
                'data' => $this->normalize($this->proxyModel->find($id)),
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to update proxy status',
        ])->setStatusCode(500);
    }

    /**
     * Fetch proxies from Dolphin Anty Browser API (semua halaman) dan
     * simpan dolphin_id supaya delete/update bisa menarget proxy yang benar.
     */
    public function fetchFromDolphinAPI()
    {
        if (!$this->dolphinApiToken) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Dolphin Anty API token not configured',
            ])->setStatusCode(400);
        }

        set_time_limit(0);

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $total    = 0;
        $page     = 1;
        $lastPage = 1;

        do {
            $res = $this->dolphin('GET', '/proxy?limit=100&page=' . $page . '&query=&sortBy=name&order=ASC');

            if (!$res['ok'] || !isset($res['body']['data']) || !is_array($res['body']['data'])) {
                if ($page === 1) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Failed to fetch proxies from API: ' . $res['message'],
                        'status'  => $res['status'],
                    ])->setStatusCode(400);
                }
                break; // halaman berikutnya gagal: pakai yang sudah didapat
            }

            $lastPage = (int) ($res['body']['last_page'] ?? 1);

            foreach ($res['body']['data'] as $apiProxy) {
                $total++;
                if (empty($apiProxy['id']) || !isset($apiProxy['host']) || !isset($apiProxy['port'])) {
                    $skipped++;
                    continue;
                }

                $dolphinId = (int) $apiProxy['id'];
                $proxyUrl  = $apiProxy['host'] . ':' . $apiProxy['port'];

                // 1) cocokkan lewat dolphin_id, 2) baris lama yang belum punya dolphin_id (backfill)
                $existing = $this->proxyModel->where('dolphin_id', $dolphinId)->first();
                if (!$existing) {
                    $existing = $this->proxyModel->where('proxy_url', $proxyUrl)->where('dolphin_id', null)->first();
                }

                // Jangan mengarang negara: kalau Dolphin belum punya data, simpan string kosong
                // (string kosong, bukan NULL, supaya aman walau kolom country NOT NULL di SQLite)
                $country  = $this->countryFullName((string) ($apiProxy['lastCheck']['country'] ?? $apiProxy['country'] ?? ''));
                $status   = ($apiProxy['lastCheck']['status'] ?? false) ? 'working' : 'failed';
                $isActive = ($status === 'working') ? 1 : 0;
                $profiles = (int) ($apiProxy['browser_profiles_count'] ?? 0);

                try {
                    if (!$existing) {
                        $this->proxyModel->insert([
                            'dolphin_id' => $dolphinId,
                            'name' => !empty($apiProxy['name']) ? $apiProxy['name'] : strtolower($apiProxy['type'] ?? 'http') . '://' . $proxyUrl,
                            'proxy_url' => $proxyUrl,
                            'proxy_type' => strtoupper($apiProxy['type'] ?? 'HTTP'),
                            'port' => (int) $apiProxy['port'],
                            'country' => $country,
                            'test_status' => $status,
                            'username' => !empty($apiProxy['login']) ? $apiProxy['login'] : null,
                            'password' => !empty($apiProxy['password']) ? $apiProxy['password'] : null,
                            'browser_profiles_count' => $profiles,
                            'is_active' => $isActive,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                        $imported++;
                    } else {
                        $upd = [
                            'dolphin_id' => $dolphinId,
                            'test_status' => $status,
                            'is_active' => $isActive,
                            'browser_profiles_count' => $profiles,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ];
                        if ($country !== '') {
                            $upd['country'] = $country;
                        }
                        $this->proxyModel->update($existing['id'], $upd);
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                    log_message('error', 'Import proxy skipped (' . $proxyUrl . '): ' . $e->getMessage());
                }
            }

            $page++;
        } while ($page <= $lastPage && $page <= 100);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Proxies imported from Dolphin Anty API',
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $total,
        ])->setStatusCode(200);
    }

    /**
     * Sync with Dolphin Anty Browser via extension
     * This endpoint receives proxy data from the Dolphin extension
     */
    public function syncFromExtension()
    {
        $json = $this->request->getJSON(true);

        if (empty($json) || !isset($json['proxies']) || !is_array($json['proxies'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid proxy data',
            ])->setStatusCode(400);
        }

        try {
            $importedCount = 0;

            foreach ($json['proxies'] as $proxyData) {
                if (empty($proxyData['host']) || empty($proxyData['port'])) {
                    continue;
                }

                $proxyUrl = $proxyData['host'] . ':' . $proxyData['port'];

                $existing = $this->proxyModel->where('proxy_url', $proxyUrl)->first();

                if (!$existing) {
                    // Pakai nama kolom yang sebenarnya (sebelumnya proxy_name/proxy_host/proxy_login... tidak ada di tabel)
                    $this->proxyModel->insert([
                        'name' => $proxyData['name'] ?? $proxyUrl,
                        'proxy_url' => $proxyUrl,
                        'proxy_type' => strtoupper($proxyData['type'] ?? 'HTTP'),
                        'port' => (int) $proxyData['port'],
                        'username' => $proxyData['login'] ?? null,
                        'password' => $proxyData['password'] ?? null,
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $importedCount++;
                }
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Proxies synced from extension',
                'imported' => $importedCount,
                'total' => count($json['proxies']),
            ])->setStatusCode(200);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => client_error($e, 'Sync Error'),
            ])->setStatusCode(500);
        }
    }

    /**
     * Export proxies in Dolphin Anty format
     */
    public function exportForDolphin()
    {
        $proxies = array_map([$this, 'normalize'], $this->proxyModel->findAll());

        $formatted = array_map(function ($proxy) {
            return [
                'ip' => $proxy['host'] ?? '',
                'port' => $proxy['port'] ?? '',
                'login' => $proxy['login'] ?? '',
                'password' => $proxy['password'] ?? '',
                'protocol' => $proxy['type'] ?? 'http',
            ];
        }, $proxies);

        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setHeader('Content-Disposition', 'attachment; filename="proxies_export.json"')
            ->setJSON([
                'proxies' => $formatted,
                'count' => count($formatted),
                'exported_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
