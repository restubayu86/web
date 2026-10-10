<?php

namespace App\Libraries;

/**
 * Klien untuk Local API Dolphin{anty} (default http://localhost:3001).
 *
 * Alur resmi (docs.dolphin-anty.com > API > Basic Automation):
 *   - POST /v1.0/auth/login-with-token   {"token": "..."}   -> {"success": true}
 *   - GET  /v1.0/browser_profiles/{id}/start
 *   - GET  /v1.0/browser_profiles/{id}/stop
 *
 * Syarat dari Dolphin: aplikasinya harus berjalan dan terotorisasi, serta
 * request datang dari mesin yang sama. Karena itu URL dibatasi ke loopback.
 */
class DolphinLocalApi
{
    public const DEFAULT_URL = 'http://localhost:3001';

    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    private string $baseUrl;
    private string $token;

    public function __construct(?string $baseUrl, ?string $token)
    {
        $this->baseUrl = self::normalizeUrl($baseUrl) ?? self::DEFAULT_URL;
        $this->token   = trim((string) $token);
    }

    /**
     * Rapikan URL Local API. Mengembalikan null jika tidak valid.
     * Kosong = default. Hanya host loopback yang diizinkan.
     */
    public static function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return self::DEFAULT_URL;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        if (isset($parts['path']) && trim($parts['path'], '/') !== '') {
            return null;
        }

        $host = strtolower($parts['host']);
        if (! in_array($host, self::LOOPBACK_HOSTS, true)) {
            return null;
        }

        $port = $parts['port'] ?? 3001;
        if ($port < 1 || $port > 65535) {
            return null;
        }

        return strtolower($parts['scheme']) . '://' . $host . ':' . $port;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Login ke aplikasi Dolphin memakai API token. */
    public function login(): array
    {
        if ($this->token === '') {
            return $this->fail('API token belum diisi di Settings.', 400);
        }

        $r = $this->call('POST', '/v1.0/auth/login-with-token', ['json' => ['token' => $this->token]]);
        if ($r['unreachable']) {
            return $this->unreachable();
        }

        if ($this->isOk($r)) {
            return ['success' => true, 'message' => 'Terhubung dan terotorisasi di Dolphin{anty} Local API (' . $this->baseUrl . ').'];
        }

        return $this->fail('Login ke Dolphin{anty} gagal: ' . $this->messageFrom($r, 'token ditolak atau tidak valid'), 422);
    }

    public function start(int $profileId): array
    {
        return $this->profileAction($profileId, 'start', '');
    }

    public function stop(int $profileId): array
    {
        return $this->profileAction($profileId, 'stop', '');
    }

    private function profileAction(int $id, string $action, string $query): array
    {
        $path = "/v1.0/browser_profiles/{$id}/{$action}{$query}";
        $timeout = $action === 'start' ? 60 : 10;
        $options = ['timeout' => $timeout];
        $r = $this->call('GET', $path, $options);

        // Belum terotorisasi: login sekali dengan token lalu coba ulang.
        if (! $r['unreachable'] && $r['status'] === 401) {
            if ($this->token === '') {
                return $this->fail('Dolphin{anty} belum terotorisasi. Login di aplikasi Dolphin atau isi API token di Settings.', 422);
            }
            $login = $this->login();
            if (! $login['success']) {
                return $login;
            }
            $r = $this->call('GET', $path, $options);
        }

        if ($r['unreachable']) {
            if ($r['timeout']) {
                return $this->fail(
                    'Dolphin{anty} Local API tidak merespons selama ' . $timeout . ' detik saat ' . ($action === 'start' ? 'memulai' : 'menghentikan') . ' profil. Periksa status profil di Dolphin{anty}; jika belum berjalan, coba lagi.',
                    504
                );
            }

            return $this->unreachable();
        }

        if ($this->isOk($r)) {
            $out = ['success' => true, 'message' => $action === 'start' ? 'Profil dijalankan.' : 'Profil dihentikan.'];

            return $out;
        }

        return $this->fail($this->messageFrom($r, 'Dolphin{anty} menolak perintah ' . $action), 422);
    }

    /** @return array{status:int,body:?array,unreachable:bool,timeout:bool} */
    private function call(string $method, string $path, array $options = []): array
    {
        try {
            $response = \Config\Services::curlrequest()->request($method, $this->baseUrl . $path, array_merge([
                'http_errors' => false,
                'timeout'     => 10,
                'headers'     => ['Accept' => 'application/json'],
            ], $options));
        } catch (\Throwable $e) {
            log_message('error', 'Dolphin Local API tidak terjangkau ({url}): {msg}', ['url' => $this->baseUrl, 'msg' => $e->getMessage()]);

            return [
                'status' => 0,
                'body' => null,
                'unreachable' => true,
                'timeout' => stripos($e->getMessage(), 'timed out') !== false,
            ];
        }

        $body = json_decode((string) $response->getBody(), true);

        return [
            'status' => $response->getStatusCode(),
            'body' => is_array($body) ? $body : null,
            'unreachable' => false,
            'timeout' => false,
        ];
    }

    private function isOk(array $r): bool
    {
        return $r['status'] >= 200 && $r['status'] < 300 && ($r['body']['success'] ?? true) !== false;
    }

    private function messageFrom(array $r, string $fallback): string
    {
        foreach (['message', 'error'] as $key) {
            if (isset($r['body'][$key]) && is_string($r['body'][$key]) && $r['body'][$key] !== '') {
                return mb_substr($r['body'][$key], 0, 200);
            }
        }

        return $fallback . ' (HTTP ' . $r['status'] . ')';
    }

    private function fail(string $message, int $status): array
    {
        return ['success' => false, 'message' => $message, 'status' => $status];
    }

    private function unreachable(): array
    {
        return $this->fail(
            'Dolphin{anty} Local API tidak terjangkau di ' . $this->baseUrl . '. Pastikan aplikasi Dolphin{anty} berjalan di mesin yang sama dengan server ini, dan URL Local API di Settings benar (port tampil di jendela Health).',
            503
        );
    }
}
