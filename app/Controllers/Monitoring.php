<?php

namespace App\Controllers;

use App\Libraries\DolphinLocalApi;
use App\Libraries\BotBridge;
use App\Models\PlaylistModel;
use App\Models\PlaylistSongModel;
use App\Models\ProfilePlaylistModel;
use App\Models\SettingsModel;
use App\Models\StreamModel;

class Monitoring extends BaseController
{
    private $apiUrl = 'https://anty-api.com';
    private $settingsModel;

    public function __construct()
    {
        $this->settingsModel = new SettingsModel();
    }

    public function index()
    {
        return view('dashboard');
    }

    private function getApiToken()
    {
        return $this->settingsModel->getSetting('dolphin_api_token');
    }

    public function getProfiles()
    {
        $apiToken = $this->getApiToken();
        
        if (!$apiToken) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'API token not configured. Please set it in settings.',
            ])->setStatusCode(400);
        }

        $client = \Config\Services::curlrequest();
        
        try {
            // Filter opsional dari UI: pencarian, halaman, dan jumlah per halaman.
            $query = [];
            $search = trim((string) $this->request->getGet('query'));
            if ($search !== '') {
                $query['query'] = mb_substr($search, 0, 100);
            }
            $page = (int) $this->request->getGet('page');
            if ($page > 0) {
                $query['page'] = $page;
            }
            $limit = (int) $this->request->getGet('limit');
            if ($limit > 0) {
                $query['limit'] = min($limit, 100);
            }

            $response = $client->get($this->apiUrl . '/browser_profiles', [
                'query'   => $query,
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiToken,
                ]
            ]);

            $body = json_decode((string) $response->getBody(), true);
            if (!is_array($body) || !isset($body['data']) || !is_array($body['data'])) {
                return $this->response->setJSON($response->getBody())->setStatusCode($response->getStatusCode());
            }

            $profileIds = array_values(array_filter(array_map(
                static fn ($profile) => isset($profile['id']) ? (string) $profile['id'] : null,
                $body['data']
            )));
            $assignments = (new ProfilePlaylistModel())->getForProfiles($profileIds);
            $assignmentByProfile = [];
            foreach ($assignments as $assignment) {
                $assignmentByProfile[(string) $assignment['profile_id']] = $assignment;
            }

            foreach ($body['data'] as &$profile) {
                $assignment = $assignmentByProfile[(string) ($profile['id'] ?? '')] ?? null;
                $profile['playlistId'] = $assignment && $assignment['playlist_id'] !== null ?
                    (int) $assignment['playlist_id'] :
                    null;
                $profile['playlistName'] = $assignment['playlist_name'] ?? null;
            }
            unset($profile);

            return $this->response->setJSON($body)->setStatusCode($response->getStatusCode());
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Could not connect to Dolphin{anty} API.',
                'error' => client_error($e, 'Terjadi kesalahan')
            ])->setStatusCode(500);
        }
    }

    /**
     * Start/stop lewat Local API Dolphin{anty} (aplikasi harus berjalan di mesin ini).
     */
    private function localApi(): DolphinLocalApi
    {
        return new DolphinLocalApi(
            $this->settingsModel->getSetting('dolphin_local_url'),
            $this->getApiToken()
        );
    }

    private function localResponse(array $result)
    {
        $code = $result['success'] ? 200 : ($result['status'] ?? 422);
        unset($result['status']);

        return $this->response->setJSON($result)->setStatusCode($code);
    }

    private function savePlaylistStream(
        StreamModel $streamModel,
        array $playlist,
        int $profileId,
        bool $running,
        ?string $currentSong = null,
        bool $starting = false
    ): ?int
    {
        $now = date('Y-m-d H:i:s');
        $streamData = [
            'stream_name' => mb_substr(trim((string) $playlist['playlist_name']) . ' - Profile ' . $profileId, 0, 255),
            'profile_id' => $profileId,
            'playlist_id' => (int) $playlist['id'],
            'status' => $running ? 'running' : ($starting ? 'starting' : 'stopped'),
            'current_song' => $currentSong,
            'total_plays' => $running ? 1 : 0,
            'stream_duration' => 0,
            'started_at' => $running ? $now : null,
            'ended_at' => $running || $starting ? null : $now,
        ];
        $existing = $streamModel
            ->where('profile_id', $profileId)
            ->where('playlist_id', (int) $playlist['id'])
            ->where('status', 'stopped')
            ->orderBy('id', 'DESC')
            ->first();
        if ($existing) {
            $streamId = (int) $existing['id'];
            return $streamModel->update($streamId, $streamData) ? $streamId : null;
        }

        if (!$streamModel->insert($streamData)) {
            return null;
        }
        return (int) $streamModel->getInsertID();
    }

    public function startProfile($id)
    {
        $profileId = (int) $id;
        if ($profileId < 1) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'A valid profile ID is required.',
            ])->setStatusCode(400);
        }

        $assignment = (new ProfilePlaylistModel())
            ->where('profile_id', (string) $profileId)
            ->first();
        if (!$assignment || empty($assignment['playlist_id'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Pilih dan simpan playlist untuk profil ini terlebih dahulu. Pengaturan ini cukup dilakukan sekali.',
            ])->setStatusCode(422);
        }

        $playlist = (new PlaylistModel())->getWithSongs((int) $assignment['playlist_id']);
        if (!$playlist) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist profil ini tidak ditemukan.',
            ])->setStatusCode(404);
        }
        $songs = $playlist['songs'] ?? [];
        if ($songs === []) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist belum memiliki lagu.',
            ])->setStatusCode(422);
        }
        $urls = [];
        foreach ($songs as $song) {
            $videoId = (string) ($song['video_id'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Playlist memiliki video ID yang tidak valid.',
                ])->setStatusCode(422);
            }
            $urls[] = 'https://music.youtube.com/watch?v=' . rawurlencode($videoId);
        }

        $bridge = new BotBridge();
        $startup = $bridge->ensureRunning($this->settingsModel);
        if (!$startup['success']) {
            log_message('error', 'BayzBot autostart failed before starting profile {id}: {message}', [
                'id' => $profileId,
                'message' => $startup['message'],
            ]);
            return $this->response->setJSON([
                'success' => false,
                'message' => $startup['message'],
            ])->setStatusCode(503);
        }
        $bridgeUrl = BotBridge::normalizeUrl($this->settingsModel->getSetting('bot_bridge_url'));
        $bridgeToken = trim((string) $this->settingsModel->getSetting('bot_bridge_token'));
        if ($bridgeUrl === null || $bridgeToken === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Konfigurasi URL/token BayzBot belum siap. Muat ulang dashboard beberapa detik lagi.',
            ])->setStatusCode(503);
        }

        $health = $bridge->health($bridgeUrl, $bridgeToken);
        $startupDeadline = microtime(true) + 30;
        while (!$health['success'] && microtime(true) < $startupDeadline) {
            usleep(250000);
            $health = $bridge->health($bridgeUrl, $bridgeToken);
        }
        if (!$health['success']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Backend BayzBot sedang dinyalakan atau gagal berjalan. Tunggu sebentar, periksa writable/logs/bayzbot-stderr.log, lalu coba lagi.',
            ])->setStatusCode(503);
        }

        $result = $this->localApi()->start($profileId);
        if (!$result['success']) {
            return $this->localResponse($result);
        }

        $connectedProfiles = array_map('strval', $health['connected_profiles'] ?? []);
        $deadline = microtime(true) + 30;
        while (!in_array((string) $profileId, $connectedProfiles, true) && microtime(true) < $deadline) {
            usleep(500000);
            $health = $bridge->health($bridgeUrl, $bridgeToken);
            if (!$health['success']) {
                break;
            }
            $connectedProfiles = array_map('strval', $health['connected_profiles'] ?? []);
        }

        $streamModel = new StreamModel();
        if (!in_array((string) $profileId, $connectedProfiles, true)) {
            $streamId = $this->savePlaylistStream($streamModel, $playlist, $profileId, false);
            $message = 'Profil Dolphin sudah berjalan, tetapi ekstensi BayzBot belum memetakan ID profil '
                . $profileId . '. Pastikan ekstensi aktif dan isi Dolphin Browser Profile ID satu kali di Options ekstensi.';
            if ($streamId === null) {
                log_message('error', 'Could not save stopped stream record for unmapped profile {id}.', ['id' => $profileId]);
                $message .= ' Data stream gagal disimpan.';
            }
            return $this->response->setJSON([
                'success' => false,
                'warning' => true,
                'profile_running' => true,
                'message' => $message,
                'data' => ['profile_id' => $profileId, 'stream' => $streamId ? $streamModel->find($streamId) : null],
            ])->setStatusCode(503);
        }

        $playback = $bridge->play($bridgeUrl, $bridgeToken, [
            'profile_id' => $profileId,
            'urls' => $urls,
            'settings' => [
                'total_loop' => (int) ($this->settingsModel->getSetting('total_loop') ?? -1),
                'shuffle' => $this->settingsModel->getSetting('shuffle_playlist') === '1',
                'watchdog_interval' => (int) ($this->settingsModel->getSetting('watchdog_interval') ?? 30),
                'ad_skip_delay_seconds' => (int) ($this->settingsModel->getSetting('ad_skip_delay_seconds') ?? 0),
            ],
        ]);
        if (!$playback['success']) {
            $streamId = $this->savePlaylistStream($streamModel, $playlist, $profileId, false);
            $message = 'Profil Dolphin sudah berjalan, tetapi playlist gagal dimulai: ' . $playback['message'];
            if ($streamId === null) {
                log_message('error', 'Could not save stopped stream record for profile {id} after playback failure.', ['id' => $profileId]);
                $message .= ' Data stream gagal disimpan.';
            }
            return $this->response->setJSON([
                'success' => false,
                'warning' => true,
                'profile_running' => true,
                'message' => $message,
                'data' => ['profile_id' => $profileId, 'stream' => $streamId ? $streamModel->find($streamId) : null],
            ])->setStatusCode(503);
        }

        $playing = ($playback['playing'] ?? true) === true;
        $playbackDeadline = microtime(true) + 12;
        while (!$playing && microtime(true) < $playbackDeadline) {
            usleep(500000);
            $snapshot = $bridge->session($bridgeUrl, $bridgeToken, $profileId);
            if (!$snapshot['success'] || empty($snapshot['managed'])) {
                break;
            }
            $playing = ($snapshot['playing'] ?? false) === true;
            if ($playing && !empty($snapshot['current_song'])) {
                $playback['current_song'] = (string) $snapshot['current_song'];
            }
        }
        $streamId = $this->savePlaylistStream(
            $streamModel,
            $playlist,
            $profileId,
            $playing,
            isset($playback['current_song']) ? (string) $playback['current_song'] : null,
            !$playing
        );
        if ($streamId === null) {
            $stopResult = $bridge->stop($bridgeUrl, $bridgeToken, $profileId);
            log_message('error', 'Playback started for profile {id}, but its stream record could not be saved.', ['id' => $profileId]);
            $message = 'Playback dimulai tetapi data stream gagal disimpan.';
            if ($stopResult['success']) {
                $message .= ' Playback dihentikan untuk menjaga konsistensi.';
            } else {
                $message .= ' Playback juga gagal dihentikan: ' . $stopResult['message'];
            }
            return $this->response->setJSON([
                'success' => false,
                'profile_running' => true,
                'message' => $message,
            ])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'success' => true,
            'profile_running' => true,
            'warning' => false,
            'message' => $playing
                ? 'Profil berjalan dan playlist berhasil diputar melalui BayzBot.'
                : ($playback['message'] ?? 'Profil berjalan; menunggu konfirmasi playback dari ekstensi.'),
            'pending' => !$playing,
            'data' => [
                'profile_id' => $profileId,
                'stream' => $streamModel->find($streamId),
            ],
        ]);
    }

    public function stopProfile($id)
    {
        $profileId = (int) $id;
        if ($profileId < 1) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'A valid profile ID is required.',
            ])->setStatusCode(400);
        }

        $bridgeWarning = null;
        $bridgeUrl = BotBridge::normalizeUrl($this->settingsModel->getSetting('bot_bridge_url'));
        $bridgeToken = trim((string) $this->settingsModel->getSetting('bot_bridge_token'));
        if ($bridgeUrl !== null && $bridgeToken !== '') {
            $bridgeResult = (new BotBridge())->stop($bridgeUrl, $bridgeToken, $profileId);
            if (!$bridgeResult['success']) {
                $bridgeWarning = 'Playlist BayzBot tidak dapat dihentikan secara terpisah: ' . $bridgeResult['message'];
            }
        }

        $result = $this->localApi()->stop($profileId);
        if (!$result['success']) {
            return $this->localResponse($result);
        }

        $streamModel = new StreamModel();
        $endedAt = date('Y-m-d H:i:s');
        foreach ($streamModel->getUnfinishedStreamsByProfile($profileId) as $stream) {
            if (!$streamModel->update((int) $stream['id'], [
                'status' => 'stopped',
                'ended_at' => $endedAt,
                'stream_duration' => $streamModel->calculateDuration((int) $stream['id'], $endedAt),
            ])) {
                log_message('error', 'Could not stop stream {id} after stopping profile {profile}.', [
                    'id' => $stream['id'],
                    'profile' => $profileId,
                ]);
                return $this->response->setJSON([
                    'success' => false,
                    'profile_running' => false,
                    'message' => 'Profile stopped, but its stream data could not be updated.',
                ])->setStatusCode(500);
            }
        }

        if ($bridgeWarning !== null) {
            $result['warning'] = true;
            $result['message'] = 'Profil dihentikan. ' . $bridgeWarning;
        }
        $result['profile_running'] = false;
        return $this->localResponse($result);
    }
}
