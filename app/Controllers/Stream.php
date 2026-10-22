<?php

namespace App\Controllers;

use App\Libraries\BotBridge;
use App\Libraries\ChromiumLauncher;
use App\Models\ChromiumProfileModel;
use App\Models\PlaylistModel;
use App\Models\SettingsModel;
use App\Models\StreamModel;
use App\Models\StreamPlayStatModel;

class Stream extends BaseController
{
    private $streamModel;

    public function __construct()
    {
        $this->streamModel = new StreamModel();
    }

    public function index()
    {
        $ramThreshold = (new SettingsModel())->getRamThreshold();
        $data = [
            'streams' => $this->streamModel->findAll(),
            // Sebelumnya tidak dikirim ke view sehingga halaman selalu memakai 85
            'ram_threshold_percent' => rtrim(rtrim(number_format($ramThreshold, 2, '.', ''), '0'), '.'),
        ];
        return view('stream', $data);
    }

    // API Endpoints
    public function getAll()
    {
        [$streams, $warnings] = $this->syncBotStreams($this->streamModel->findAll());
        $streams = $this->withProfileInfo($streams);
        $response = [
            'success' => true,
            'data' => $streams,
        ];
        if ($warnings !== []) {
            $response['warnings'] = $warnings;
        }
        return $this->response->setJSON($response);
    }

    public function getActive()
    {
        [$streams, $warnings] = $this->syncBotStreams($this->streamModel->getActiveStreams());
        $streams = $this->withProfileInfo($streams);
        $streams = array_map(fn (array $stream): array => $this->withDuration($stream), $streams);
        $streams = array_values(array_filter($streams, static fn (array $stream): bool => $stream['status'] === 'running'));
        $response = [
            'success' => true,
            'data' => $streams,
        ];
        if ($warnings !== []) {
            $response['warnings'] = $warnings;
        }
        return $this->response->setJSON($response);
    }

    public function getById($id)
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Stream not found',
            ])->setStatusCode(404);
        }

        $stream = $this->withProfileInfo([$this->withDuration($stream)])[0];
        return $this->response->setJSON([
            'success' => true,
            'data' => $stream,
        ]);
    }

    public function getByProfile($profileId)
    {
        [$streams, $warnings] = $this->syncBotStreams($this->streamModel->getStreamsByProfile($profileId));
        $streams = $this->withProfileInfo($streams);
        $streams = array_map(fn (array $stream): array => $this->withDuration($stream), $streams);
        $response = [
            'success' => true,
            'data' => $streams,
        ];
        if ($warnings !== []) {
            $response['warnings'] = $warnings;
        }
        return $this->response->setJSON($response);
    }

    public function create()
    {
        $streamName = $this->request->getPost('stream_name');
        $profileId = $this->request->getPost('profile_id');
        $playlistId = $this->request->getPost('playlist_id');

        if (!$streamName) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Stream name is required',
            ])->setStatusCode(400);
        }

        if ($profileId !== null && $profileId !== '') {
            $existingStream = $this->streamModel
                ->where('profile_id', (string) $profileId)
                ->first();
            if ($existingStream) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Profil ini sudah memiliki stream. Gunakan stream yang sudah ada.',
                ])->setStatusCode(409);
            }
        }

        $data = [
            'stream_name' => $streamName,
            'profile_id' => $profileId ?? null,
            'playlist_id' => $playlistId ?? null,
            'status' => 'stopped',
            'total_plays' => 0,
            'stream_duration' => 0,
        ];

        if ($this->streamModel->insert($data)) {
            $insertId = $this->streamModel->getInsertID();
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Stream created successfully',
                'data' => $this->streamModel->find($insertId),
            ])->setStatusCode(201);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to create stream',
        ])->setStatusCode(500);
    }

    public function update($id)
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Stream not found',
            ])->setStatusCode(404);
        }

        $data = [];
        $allowedFields = ['stream_name', 'profile_id', 'playlist_id', 'current_song', 'total_plays'];

        foreach ($allowedFields as $field) {
            $value = $this->request->getPost($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        if (empty($data)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No data to update',
            ])->setStatusCode(400);
        }

        if ($this->streamModel->update($id, $data)) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Stream updated successfully',
                'data' => $this->streamModel->find($id),
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to update stream',
        ])->setStatusCode(500);
    }

    public function delete($id)
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Stream not found',
            ])->setStatusCode(404);
        }

            // Refresh stream status from BayzBot before checking if it can be deleted
            $stream = $this->refreshStream($stream);
            if (in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Stop the stream before deleting it.',
                ])->setStatusCode(409);
            }

            if ($this->streamModel->delete($id)) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Stream deleted successfully',
                ]);
            }

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to delete stream',
            ])->setStatusCode(500);
        }

        /**
         * Refresh a single stream's status from BayzBot bridge
         */
        private function refreshStream(array $stream): array
        {
            if (empty($stream['profile_id']) || !in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
                return $this->withDuration($stream);
            }

            $settings = new SettingsModel();
            $bridgeUrl = BotBridge::normalizeUrl($settings->getSetting('bot_bridge_url'));
            $bridgeToken = trim((string) $settings->getSetting('bot_bridge_token'));
            if ($bridgeUrl === null || $bridgeToken === '') {
                return $this->withDuration($stream);
            }

            $bridge = new BotBridge();
            $health = $bridge->health($bridgeUrl, $bridgeToken);
            if (!$health['success']) {
                return $this->withDuration($stream);
            }

            $snapshot = $bridge->session($bridgeUrl, $bridgeToken, (int) $stream['profile_id']);
            if (!$snapshot['success'] || empty($snapshot['managed'])) {
                return $this->withDuration($stream);
            }

            $updates = [
                'current_song' => array_key_exists('current_song', $snapshot) ? $snapshot['current_song'] : $stream['current_song'],
                'current_artist' => array_key_exists('current_artist', $snapshot) ? $snapshot['current_artist'] : $stream['current_artist'],
                'total_plays' => max((int) $stream['total_plays'], (int) ($snapshot['total_plays'] ?? 0)),
            ];
            if (empty($snapshot['active'])) {
                $endedAt = date('Y-m-d H:i:s');
                $updates['status'] = 'stopped';
                $updates['ended_at'] = $endedAt;
                $updates['stream_duration'] = $this->streamModel->calculateDuration((int) $stream['id'], $endedAt);
            } elseif (!empty($snapshot['paused'])) {
                $updates['status'] = 'paused';
            } elseif (!empty($snapshot['playing'])) {
                $updates['status'] = 'running';
                $updates['started_at'] = $stream['started_at'] ?: date('Y-m-d H:i:s');
            } else {
                $updates['status'] = 'starting';
            }

            $changes = [];
            foreach ($updates as $field => $value) {
                if ((string) ($stream[$field] ?? '') !== (string) ($value ?? '')) {
                    $changes[$field] = $value;
                }
            }
            $saved = $changes === [] || $this->streamModel->update((int) $stream['id'], $changes);
            if (!$saved) {
                // Log warning but continue with stale data
                log_message('warning', 'Gagal memperbarui data stream ' . $stream['id'] . ' dari status BayzBot.');
            }
            if ($saved && isset($changes['total_plays'])) {
                $this->recordPlayDelta((int) $stream['id'], (int) $stream['total_plays'], (int) $changes['total_plays']);
            }
            if ($changes !== []) {
                $stream = $this->streamModel->find((int) $stream['id']) ?? $stream;
            }

            return $this->withDuration($stream);
        }

    public function start($id)
    {
        return $this->control($id, 'play');
    }

    public function stop($id)
    {
        return $this->control($id, 'stop');
    }

    public function control($id, string $action)
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Stream not found',
            ])->setStatusCode(404);
        }

        if (!in_array($action, ['play', 'pause', 'stop', 'next', 'previous', 'restart'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Unsupported playback action.',
            ])->setStatusCode(400);
        }

        if (empty($stream['profile_id'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Pilih profil Dolphin untuk stream ini terlebih dahulu.',
            ])->setStatusCode(422);
        }

        $settings = new SettingsModel();
        $bridge = new BotBridge();
        $chromiumProfile = $action === 'play'
            ? $this->chromiumProfile((int) $stream['profile_id'])
            : null;
        if ($action === 'play' && in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
            $bridgeUrl = BotBridge::normalizeUrl($settings->getSetting('bot_bridge_url'));
            $bridgeToken = trim((string) $settings->getSetting('bot_bridge_token'));
            if ($bridgeUrl === null || $bridgeToken === '') {
                return $this->bridgeUnavailable('Konfigurasi URL/token BayzBot belum siap.');
            }
            if ($chromiumProfile !== null) {
                $startup = $bridge->ensureRunning($settings);
                if (!$startup['success']) {
                    return $this->response->setJSON($startup)->setStatusCode(503);
                }
                $launch = $this->ensureChromiumRunning($chromiumProfile);
                if (!$launch['success']) {
                    return $this->response->setJSON($launch)->setStatusCode((int) ($launch['status'] ?? 500));
                }
                $deadline = microtime(true) + 30;
                do {
                    $health = $bridge->health($bridgeUrl, $bridgeToken);
                    if ($health['success']
                        && in_array((string) $stream['profile_id'], array_map('strval', $health['connected_profiles'] ?? []), true)) {
                        break;
                    }
                    usleep(500000);
                } while (microtime(true) < $deadline);
                if (!$health['success']
                    || !in_array((string) $stream['profile_id'], array_map('strval', $health['connected_profiles'] ?? []), true)) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Ekstensi BayzBot Chromium belum terhubung. Pastikan Chromium berjalan dan ekstensi BayzBot aktif.',
                    ])->setStatusCode(503);
                }
            }
            $result = $bridge->control($bridgeUrl, $bridgeToken, (int) $stream['profile_id'], 'play');
        } elseif ($action === 'play') {
            $playlist = !empty($stream['playlist_id'])
                ? (new PlaylistModel())->getWithSongs((int) $stream['playlist_id'])
                : null;
            if (!$playlist || empty($playlist['songs'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Stream ini belum memiliki playlist dengan lagu.',
                ])->setStatusCode(422);
            }

            $urls = [];
            $tracks = [];
            foreach ($playlist['songs'] as $song) {
                $videoId = (string) ($song['video_id'] ?? '');
                if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Playlist memiliki video ID yang tidak valid.',
                    ])->setStatusCode(422);
                }
                $url = 'https://music.youtube.com/watch?v=' . rawurlencode($videoId);
                $urls[] = $url;
                $tracks[] = [
                    'url' => $url,
                    'title' => (string) ($song['song_title'] ?? ''),
                    'artist' => (string) ($song['artist_name'] ?? ''),
                ];
            }

            $startup = $bridge->ensureRunning($settings);
            if (!$startup['success']) {
                return $this->response->setJSON($startup)->setStatusCode(503);
            }
            $bridgeUrl = BotBridge::normalizeUrl($settings->getSetting('bot_bridge_url'));
            $bridgeToken = trim((string) $settings->getSetting('bot_bridge_token'));
            if ($bridgeUrl === null || $bridgeToken === '') {
                return $this->bridgeUnavailable('Konfigurasi URL/token BayzBot belum siap.');
            }

            $health = $bridge->health($bridgeUrl, $bridgeToken);
            if (!$health['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Backend BayzBot belum siap: ' . $health['message'],
                ])->setStatusCode(503);
            }
            if ($chromiumProfile !== null) {
                $launch = $this->ensureChromiumRunning($chromiumProfile);
                if (!$launch['success']) {
                    return $this->response->setJSON($launch)->setStatusCode((int) ($launch['status'] ?? 500));
                }
                $profileId = (int) $stream['profile_id'];
                $deadline = microtime(true) + 30;
                while (!in_array((string) $profileId, array_map('strval', $health['connected_profiles'] ?? []), true) && microtime(true) < $deadline) {
                    usleep(500000);
                    $health = $bridge->health($bridgeUrl, $bridgeToken);
                    if (!$health['success']) {
                        break;
                    }
                }
            }
            if (!in_array((string) $stream['profile_id'], array_map('strval', $health['connected_profiles'] ?? []), true)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => $chromiumProfile !== null
                        ? 'Ekstensi BayzBot Chromium belum terhubung. Pastikan Chromium berjalan dan ekstensi BayzBot aktif.'
                        : 'Extension BayzBot untuk profil Dolphin ini belum terhubung. Buka profil yang sudah ditautkan, lalu coba lagi.',
                ])->setStatusCode(503);
            }

            $result = $bridge->play($bridgeUrl, $bridgeToken, [
                'profile_id' => (int) $stream['profile_id'],
                'urls' => $urls,
                'tracks' => $tracks,
                'settings' => [
                    'total_loop' => (int) ($settings->getSetting('total_loop') ?? -1),
                    'shuffle' => $settings->getSetting('shuffle_playlist') === '1',
                    'watchdog_interval' => (int) ($settings->getSetting('watchdog_interval') ?? 30),
                    'ad_skip_delay_seconds' => (int) ($settings->getSetting('ad_skip_delay_seconds') ?? 0),
                    ...$settings->getBackendRuntimeSettings(),
                ],
            ]);
        } else {
            $bridgeUrl = BotBridge::normalizeUrl($settings->getSetting('bot_bridge_url'));
            $bridgeToken = trim((string) $settings->getSetting('bot_bridge_token'));
            if ($bridgeUrl === null || $bridgeToken === '') {
                return $this->bridgeUnavailable('Konfigurasi URL/token BayzBot belum siap.');
            }
            
            // If we are trying to stop a stream that isn't running in the bridge,
            // we should just update the local status to 'stopped' instead of failing.
            if ($action === 'stop' && !in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
                $result = ['success' => true];
            } else {
                $result = $bridge->control($bridgeUrl, $bridgeToken, (int) $stream['profile_id'], $action);
                
                // If the bridge returns failure for 'stop', force local update to 'stopped'
                if (!$result['success'] && $action === 'stop') {
                    $result = ['success' => true];
                }
            }
        }

        if (!$result['success']) {
            $status = (int) ($result['status'] ?? 422);
            unset($result['status']);
            return $this->response->setJSON($result)->setStatusCode($status);
        }

        $updates = [];
        $message = 'Playback ' . $action . ' command sent.';
        if ($action === 'play') {
            $updates = [
                'status' => 'starting',
                'ended_at' => null,
            ];
            if (!in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
                $updates['started_at'] = date('Y-m-d H:i:s');
                $updates['stream_duration'] = 0;
                $updates['total_plays'] = 0;
                $updates['current_song'] = null;
                $updates['current_artist'] = null;
            }
            $message = 'Playback started/resumed.';
        } elseif ($action === 'pause') {
            $updates = ['status' => 'paused'];
            $message = 'Playback paused.';
        } elseif ($action === 'stop') {
            $endedAt = date('Y-m-d H:i:s');
            $updates = [
                'status' => 'stopped',
                'ended_at' => $endedAt,
                'stream_duration' => $this->streamModel->calculateDuration((int) $id, $endedAt),
            ];
            $message = 'Playback stopped. Dolphin profile remains open.';
        } elseif ($action === 'restart') {
            $updates = ['status' => 'starting'];
            $message = 'Playback restarted.';
        }

        if ($updates !== [] && !$this->streamModel->update((int) $id, $updates)) {
            log_message('error', 'Could not update stream {id} after BayzBot {action}.', [
                'id' => $id,
                'action' => $action,
            ]);
            return $this->response->setJSON([
                'success' => false,
                'message' => 'BayzBot received the command, but stream status could not be saved.',
            ])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'data' => $this->streamModel->find((int) $id),
        ]);
    }

    private function bridgeUnavailable(string $message)
    {
        return $this->response->setJSON([
            'success' => false,
            'message' => $message,
        ])->setStatusCode(503);
    }

    public function getStats($id)
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Stream not found',
            ])->setStatusCode(404);
        }

        $stream = $this->withDuration($stream);
        $duration = $this->streamModel->calculateDuration((int) $id);

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'stream_id' => $stream['id'],
                'stream_name' => $stream['stream_name'],
                'status' => $stream['status'],
                'total_plays' => $stream['total_plays'],
                'stream_duration' => $duration,
                'started_at' => $stream['started_at'],
                'ended_at' => $stream['ended_at'],
            ]
        ]);
    }

    /**
     * Zona waktu untuk batas hari/bulan pada grafik Stats. Default Asia/Jakarta;
     * bisa diganti lewat setting `stats_timezone` (mis. UTC).
     */
    private function statsTimezone(): \DateTimeZone
    {
        $name = trim((string) (new SettingsModel())->getSetting('stats_timezone'));
        try {
            return new \DateTimeZone($name !== '' ? $name : 'Asia/Jakarta');
        } catch (\Throwable $e) {
            return new \DateTimeZone('Asia/Jakarta');
        }
    }

    /**
     * Catat pertambahan putar ke tabel harian. Dipanggil hanya setelah total_plays
     * baru tersimpan, sehingga polling berulang tidak menghitung dua kali.
     */
    private function recordPlayDelta(int $streamId, int $oldTotal, int $newTotal): void
    {
        $delta = $newTotal - $oldTotal;
        if ($delta <= 0) {
            return;
        }

        try {
            $today = (new \DateTimeImmutable('now', $this->statsTimezone()))->format('Y-m-d');
            (new StreamPlayStatModel())->addPlays($streamId, $today, $delta);
        } catch (\Throwable $e) {
            log_message('warning', 'Gagal mencatat statistik putar stream {id}: {msg}', [
                'id'  => $streamId,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Peta kode negara (uppercase) => rate dari setting revenue_rates.
     *
     * @return array<string,float>
     */
    private function revenueRateMap(): array
    {
        $rates = json_decode((string) (new SettingsModel())->getSetting('revenue_rates'), true);
        $map   = [];
        foreach (is_array($rates) ? $rates : [] as $row) {
            $country = strtoupper(trim((string) ($row['country'] ?? '')));
            if ($country !== '') {
                $map[$country] = (float) ($row['rate'] ?? 0);
            }
        }

        return $map;
    }

    private function parseStatsDate($value, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);

        return ($date && $date->format('Y-m-d') === $value) ? $date : null;
    }

    /**
     * Data grafik bar per stream: total putar dan estimasi revenue per hari/bulan.
     * Query: start=Y-m-d, end=Y-m-d, group=day|month.
     */
    public function getChart($id)
    {
        $stream = $this->streamModel->find($id);
        if (!$stream) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Stream not found',
            ])->setStatusCode(404);
        }

        $tz     = $this->statsTimezone();
        $today  = new \DateTimeImmutable('today', $tz);
        $group  = $this->request->getGet('group') === 'month' ? 'month' : 'day';
        $start  = $this->parseStatsDate($this->request->getGet('start'), $tz)
            ?? ($group === 'month' ? $today->modify('first day of this month')->modify('-5 months') : $today->modify('-6 days'));
        $end    = $this->parseStatsDate($this->request->getGet('end'), $tz) ?? $today;

        if ($start > $end) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tanggal mulai tidak boleh setelah tanggal akhir.',
            ])->setStatusCode(422);
        }

        // Bulanan: perluas ke batas bulan penuh supaya bulan di tepi tidak terpotong.
        if ($group === 'month') {
            $start = $start->modify('first day of this month');
            $end   = $end->modify('last day of this month');
            $months = ((int) $end->format('Y') - (int) $start->format('Y')) * 12
                + ((int) $end->format('n') - (int) $start->format('n')) + 1;
            if ($months > 36) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Rentang bulanan maksimal 36 bulan.',
                ])->setStatusCode(422);
            }
        } elseif ($start->diff($end)->days + 1 > 366) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Rentang harian maksimal 366 hari.',
            ])->setStatusCode(422);
        }

        $buckets = [];
        if ($group === 'month') {
            for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
                $buckets[$cursor->format('Y-m')] = 0;
            }
        } else {
            for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
                $buckets[$cursor->format('Y-m-d')] = 0;
            }
        }

        $totals = (new StreamPlayStatModel())->totalsByDate((int) $id, $start->format('Y-m-d'), $end->format('Y-m-d'));
        foreach ($totals as $date => $plays) {
            $key = $group === 'month' ? substr($date, 0, 7) : $date;
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += $plays;
            }
        }

        // Negara stream belum tersimpan di database; yang unknown dikaitkan ke ID (Indonesia).
        $country = strtoupper(trim((string) ($stream['proxy_country'] ?? '')));
        if ($country === '' || $country === 'UNKNOWN') {
            $country = 'ID';
        }
        $rateMap = $this->revenueRateMap();
        $hasRate = array_key_exists($country, $rateMap);
        $rate    = $hasRate ? $rateMap[$country] : 0.0;

        $rows = [];
        $totalPlays = 0;
        foreach ($buckets as $key => $plays) {
            $totalPlays += $plays;
            $rows[] = [
                'key'     => $key,
                'plays'   => $plays,
                'revenue' => round($plays * $rate, 4),
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'stream_id'     => (int) $stream['id'],
                'stream_name'   => $stream['stream_name'],
                'group'         => $group,
                'start'         => $start->format('Y-m-d'),
                'end'           => $end->format('Y-m-d'),
                'timezone'      => $tz->getName(),
                'rate_country'  => $country,
                'rate'          => $rate,
                'has_rate'      => $hasRate,
                'total_plays'   => $totalPlays,
                'total_revenue' => round($totalPlays * $rate, 4),
                'buckets'       => $rows,
            ],
        ]);
    }

    private function withDuration(array $stream): array
    {
        $stream['stream_duration'] = $this->streamModel->calculateDuration((int) $stream['id']);

        return $stream;
    }

    private function withProfileInfo(array $streams): array
    {
        $chromiumProfiles = (new ChromiumProfileModel())->findAll();
        $byBridgeId = [];
        foreach ($chromiumProfiles as $profile) {
            $byBridgeId[(string) ChromiumProfileModel::bridgeId((int) $profile['id'])] = $profile;
        }

        foreach ($streams as &$stream) {
            $profileId = (string) ($stream['profile_id'] ?? '');
            $chromiumProfile = ctype_digit($profileId)
                ? ($byBridgeId[$profileId] ?? null)
                : null;
            if ($chromiumProfile !== null) {
                $stream['browser_type'] = 'Chromium';
                $stream['profile_name'] = (string) $chromiumProfile['profile_name'];
            } elseif ($profileId !== '') {
                $stream['browser_type'] = 'Dolphin';
                $stream['profile_name'] = 'Profile #' . $profileId;
            } else {
                $stream['browser_type'] = null;
                $stream['profile_name'] = null;
            }
        }
        unset($stream);

        return $streams;
    }

    private function chromiumProfile(int $bridgeId): ?array
    {
        $profileId = ChromiumProfileModel::idFromBridgeId($bridgeId);
        if ($profileId === null) {
            return null;
        }

        return (new ChromiumProfileModel())->find($profileId) ?: null;
    }

    private function ensureChromiumRunning(array $profile): array
    {
        $model = new ChromiumProfileModel();
        $executable = ChromiumProfileModel::executablePath();
        $launcher = new ChromiumLauncher();
        if ($launcher->isRunning($profile, $executable)) {
            return ['success' => true];
        }

        $result = $launcher->start($profile, $executable);
        if (!$result['success']) {
            return $result;
        }
        if (!$model->update((int) $profile['id'], ['process_id' => (int) $result['process_id']])) {
            log_message('error', 'Chromium stream opened, but its process ID could not be saved for profile {id}.', [
                'id' => $profile['id'],
            ]);
            return ['success' => false, 'message' => 'Chromium dibuka, tetapi status proses gagal disimpan.', 'status' => 500];
        }

        return ['success' => true];
    }

    private function syncBotStreams(array $streams): array
    {
        $hasUnfinishedProfileStream = false;
        foreach ($streams as $stream) {
            if (
                in_array($stream['status'], ['starting', 'running', 'paused'], true)
                && !empty($stream['profile_id'])
            ) {
                $hasUnfinishedProfileStream = true;
                break;
            }
        }
        if (!$hasUnfinishedProfileStream) {
            return [array_map(fn (array $stream): array => $this->withDuration($stream), $streams), []];
        }

        $settings = new SettingsModel();
        $bridgeUrl = BotBridge::normalizeUrl($settings->getSetting('bot_bridge_url'));
        $bridgeToken = trim((string) $settings->getSetting('bot_bridge_token'));
        if ($bridgeUrl === null || $bridgeToken === '') {
            return [array_map(fn (array $stream): array => $this->withDuration($stream), $streams), []];
        }

        $bridge = new BotBridge();
        $health = $bridge->health($bridgeUrl, $bridgeToken);
        if (!$health['success']) {
            // Jika backend mati, tandai semua stream yang aktif sebagai stopped
            $warnings = ['BayzBot backend tidak aktif. Menandai stream sebagai stopped.'];
            foreach ($streams as &$stream) {
                if (in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
                    $endedAt = date('Y-m-d H:i:s');
                    $this->streamModel->update((int) $stream['id'], [
                        'status' => 'stopped',
                        'ended_at' => $endedAt,
                        'stream_duration' => $this->streamModel->calculateDuration((int) $stream['id'], $endedAt)
                    ]);
                    $stream = $this->streamModel->find((int) $stream['id']);
                }
                $stream = $this->withDuration($stream);
            }
            return [array_values($streams), $warnings];
        }

        $warnings = [];
        foreach ($streams as &$stream) {
            if (!in_array($stream['status'], ['starting', 'running', 'paused'], true) || empty($stream['profile_id'])) {
                $stream = $this->withDuration($stream);
                continue;
            }
            $snapshot = $bridge->session($bridgeUrl, $bridgeToken, (int) $stream['profile_id']);
            if (!$snapshot['success']) {
                // Jika gagal baca status profil, anggap instance mati
                $endedAt = date('Y-m-d H:i:s');
                $this->streamModel->update((int) $stream['id'], [
                    'status' => 'stopped',
                    'ended_at' => $endedAt,
                    'stream_duration' => $this->streamModel->calculateDuration((int) $stream['id'], $endedAt)
                ]);
                $stream = $this->streamModel->find((int) $stream['id']) ?? $stream;
                $stream = $this->withDuration($stream);
                continue;
            }
            if (empty($snapshot['managed'])) {
                $stream = $this->withDuration($stream);
                continue;
            }

            $updates = [
                'current_song' => array_key_exists('current_song', $snapshot) ? $snapshot['current_song'] : $stream['current_song'],
                'current_artist' => array_key_exists('current_artist', $snapshot) ? $snapshot['current_artist'] : $stream['current_artist'],
                'total_plays' => max((int) $stream['total_plays'], (int) ($snapshot['total_plays'] ?? 0)),
            ];
            if (empty($snapshot['active'])) {
                $endedAt = date('Y-m-d H:i:s');
                $updates['status'] = 'stopped';
                $updates['ended_at'] = $endedAt;
                $updates['stream_duration'] = $this->streamModel->calculateDuration((int) $stream['id'], $endedAt);
            } elseif (!empty($snapshot['paused'])) {
                $updates['status'] = 'paused';
            } elseif (!empty($snapshot['playing'])) {
                $updates['status'] = 'running';
                $updates['started_at'] = $stream['started_at'] ?: date('Y-m-d H:i:s');
            } else {
                $updates['status'] = 'starting';
            }
            $changes = [];
            foreach ($updates as $field => $value) {
                if ((string) ($stream[$field] ?? '') !== (string) ($value ?? '')) {
                    $changes[$field] = $value;
                }
            }
            $saved = $changes === [] || $this->streamModel->update((int) $stream['id'], $changes);
            if (!$saved) {
                $warnings[] = 'Gagal memperbarui data stream ' . $stream['id'] . ' dari status BayzBot.';
            }
            if ($saved && isset($changes['total_plays'])) {
                $this->recordPlayDelta((int) $stream['id'], (int) $stream['total_plays'], (int) $changes['total_plays']);
            }
            if ($changes !== []) {
                $stream = $this->streamModel->find((int) $stream['id']) ?? $stream;
            }
            $stream = $this->withDuration($stream);
        }
        unset($stream);

        return [array_values($streams), array_values(array_unique($warnings))];
    }

}
