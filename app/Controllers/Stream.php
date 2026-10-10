<?php

namespace App\Controllers;

use App\Libraries\BotBridge;
use App\Models\PlaylistModel;
use App\Models\SettingsModel;
use App\Models\StreamModel;

class Stream extends BaseController
{
    private $streamModel;

    public function __construct()
    {
        $this->streamModel = new StreamModel();
    }

    public function index()
    {
        $data = [
            'streams' => $this->streamModel->findAll(),
        ];
        return view('stream', $data);
    }

    // API Endpoints
    public function getAll()
    {
        [$streams, $warnings] = $this->syncBotStreams($this->streamModel->findAll());
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

        $stream = $this->withDuration($stream);
        return $this->response->setJSON([
            'success' => true,
            'data' => $stream,
        ]);
    }

    public function getByProfile($profileId)
    {
        [$streams, $warnings] = $this->syncBotStreams($this->streamModel->getStreamsByProfile($profileId));
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
            if ($changes !== [] && !$this->streamModel->update((int) $stream['id'], $changes)) {
                // Log warning but continue with stale data
                log_message('warning', 'Gagal memperbarui data stream ' . $stream['id'] . ' dari status BayzBot.');
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
        if ($action === 'play' && in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
            $bridgeUrl = BotBridge::normalizeUrl($settings->getSetting('bot_bridge_url'));
            $bridgeToken = trim((string) $settings->getSetting('bot_bridge_token'));
            if ($bridgeUrl === null || $bridgeToken === '') {
                return $this->bridgeUnavailable('Konfigurasi URL/token BayzBot belum siap.');
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
            if (!in_array((string) $stream['profile_id'], array_map('strval', $health['connected_profiles'] ?? []), true)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Extension BayzBot untuk profil Dolphin ini belum terhubung. Buka profil yang sudah ditautkan, lalu coba lagi.',
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

    private function withDuration(array $stream): array
    {
        $stream['stream_duration'] = $this->streamModel->calculateDuration((int) $stream['id']);

        return $stream;
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
            if ($changes !== [] && !$this->streamModel->update((int) $stream['id'], $changes)) {
                $warnings[] = 'Gagal memperbarui data stream ' . $stream['id'] . ' dari status BayzBot.';
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
