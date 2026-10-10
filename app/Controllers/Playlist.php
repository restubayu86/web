<?php

namespace App\Controllers;

use App\Models\PlaylistModel;
use App\Models\ProfilePlaylistModel;
use App\Models\PlaylistSongModel;

class Playlist extends BaseController
{
    private $playlistModel;
    private $songModel;

    public function __construct()
    {
        $this->playlistModel = new PlaylistModel();
        $this->songModel = new PlaylistSongModel();
    }

    public function index()
    {
        $data = [
            'playlists' => $this->playlistModel->findAll(),
        ];
        return view('playlist', $data);
    }

    // Playlist API Endpoints
    public function getAll()
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => $this->playlistModel->findAll(),
        ]);
    }

    public function getById($id)
    {
        $playlist = $this->playlistModel->getWithSongs($id);
        if (!$playlist) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist not found',
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $playlist,
        ]);
    }

    public function create()
    {
        $playlistName = $this->request->getPost('playlist_name');

        if (!$playlistName) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist name is required',
            ])->setStatusCode(400);
        }

        $data = [
            'playlist_name' => $playlistName,
            'youtube_id' => $this->request->getPost('youtube_id') ?? null,
            'description' => $this->request->getPost('description') ?? null,
            'total_songs' => 0,
            'is_active' => 1,
        ];

        if ($this->playlistModel->insert($data)) {
            $insertId = $this->playlistModel->getInsertID();
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Playlist created successfully',
                'data' => $this->playlistModel->find($insertId),
            ])->setStatusCode(201);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to create playlist',
        ])->setStatusCode(500);
    }

    public function update($id)
    {
        $playlist = $this->playlistModel->find($id);
        if (!$playlist) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist not found',
            ])->setStatusCode(404);
        }

        $data = [];
        $allowedFields = ['playlist_name', 'youtube_id', 'description', 'is_active'];

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

        if ($this->playlistModel->update($id, $data)) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Playlist updated successfully',
                'data' => $this->playlistModel->find($id),
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to update playlist',
        ])->setStatusCode(500);
    }

    public function delete($id)
    {
        $playlist = $this->playlistModel->find($id);
        if (!$playlist) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist not found',
            ])->setStatusCode(404);
        }

        // Delete associated songs
        $this->songModel->where('playlist_id', $id)->delete();

        if ($this->playlistModel->delete($id)) {
            (new ProfilePlaylistModel())->where('playlist_id', $id)->delete();
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Playlist deleted successfully',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to delete playlist',
        ])->setStatusCode(500);
    }

    public function assignToProfile($profileId)
    {
        $profileId = (int) $profileId;
        $playlistValue = $this->request->getPost('playlist_id');
        $clearPlaylist = $playlistValue === 'none';
        $playlistId = $clearPlaylist ? null : filter_var($playlistValue, FILTER_VALIDATE_INT);

        if ($profileId < 1 || (!$clearPlaylist && ($playlistId === false || $playlistId < 1))) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Select a valid playlist or choose No playlist.',
            ])->setStatusCode(400);
        }

        if (!$clearPlaylist && !$this->playlistModel->find($playlistId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Selected playlist was not found.',
            ])->setStatusCode(404);
        }

        $assignmentModel = new ProfilePlaylistModel();
        $assignment = $assignmentModel->where('profile_id', (string) $profileId)->first();
        $saved = $assignment ?
            $assignmentModel->update($assignment['id'], ['playlist_id' => $playlistId]) :
            $assignmentModel->insert(['profile_id' => (string) $profileId, 'playlist_id' => $playlistId]);

        if (!$saved) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to save playlist for this profile.',
            ])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $clearPlaylist ? 'Playlist removed from profile.' : 'Playlist assigned to profile.',
            'data' => [
                'profile_id' => $profileId,
                'playlist_id' => $playlistId,
            ],
        ]);
    }

    // Song API Endpoints
    public function addSong($playlistId)
    {
        $playlist = $this->playlistModel->find($playlistId);
        if (!$playlist) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist not found',
            ])->setStatusCode(404);
        }

        $videoId = $this->request->getPost('video_id');
        if (!$videoId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Video ID is required',
            ])->setStatusCode(400);
        }

        $position = $this->songModel->where('playlist_id', $playlistId)->countAllResults() + 1;

        $data = [
            'playlist_id' => $playlistId,
            'video_id' => $videoId,
            'song_title' => $this->request->getPost('song_title') ?? 'Unknown Title',
            'artist_name' => $this->request->getPost('artist_name') ?? 'Unknown Artist',
            'duration' => $this->request->getPost('duration') ?? 0,
            'play_count' => 0,
            'status' => 'Menunggu',
            'position' => $position,
        ];

        if ($this->songModel->insert($data)) {
            $this->playlistModel->updateSongCount($playlistId);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Song added successfully',
                'data' => $this->songModel->where('playlist_id', $playlistId)->where('video_id', $videoId)->first(),
            ])->setStatusCode(201);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to add song',
        ])->setStatusCode(500);
    }

    public function removeSong($songId)
    {
        $song = $this->songModel->find($songId);
        if (!$song) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Song not found',
            ])->setStatusCode(404);
        }

        $playlistId = $song['playlist_id'];

        if ($this->songModel->delete($songId)) {
            $this->playlistModel->updateSongCount($playlistId);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Song removed successfully',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to remove song',
        ])->setStatusCode(500);
    }

    public function getSongs($playlistId)
    {
        $songs = $this->songModel->getByPlaylistId($playlistId);
        return $this->response->setJSON([
            'success' => true,
            'data' => $songs,
        ]);
    }

    public function refreshSongDuration($songId)
    {
        $song = $this->songModel->find($songId);
        if (!$song) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Song not found.',
            ])->setStatusCode(404);
        }

        $duration = (int) ($song['duration'] ?? 0);
        if ($duration > 0) {
            return $this->response->setJSON([
                'success' => true,
                'data' => ['duration' => $duration],
            ]);
        }

        $duration = $this->fetchYoutubeDuration((string) $song['video_id']);
        if ($duration === null) {
            return $this->response->setJSON([
                'success' => true,
                'data' => ['duration' => null],
            ]);
        }

        if (!$this->songModel->update($songId, ['duration' => $duration])) {
            log_message('error', 'Could not save refreshed duration for playlist song {songId}', [
                'songId' => $songId,
            ]);
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Duration was found but could not be saved.',
            ])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => ['duration' => $duration],
        ]);
    }

    public function updateSongStatus($songId)
    {
        $song = $this->songModel->find($songId);
        if (!$song) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Song not found',
            ])->setStatusCode(404);
        }

        $status = $this->request->getPost('status');
        if (!$status) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Status is required',
            ])->setStatusCode(400);
        }

        if ($this->songModel->updateStatus($songId, $status)) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Song status updated',
                'data' => $this->songModel->find($songId),
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to update song status',
        ])->setStatusCode(500);
    }

    public function refreshSong($songId)
    {
        $song = $this->songModel->find($songId);
        if (!$song) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Song not found',
            ])->setStatusCode(404);
        }

        $youtubeData = $this->fetchYoutubeData($song['video_id']);
        if ($youtubeData) {
            $data = [
                'song_title' => $youtubeData['title'] ?? $song['song_title'],
                'artist_name' => $youtubeData['artist'] ?? $song['artist_name'],
                'album' => $youtubeData['album'] ?? $song['album'],
            ];

            if ($this->songModel->update($songId, $data)) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Song refreshed successfully',
                    'data' => $this->songModel->find($songId),
                ]);
            }
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to refresh song data',
        ])->setStatusCode(500);
    }

    // NEW FEATURES: File Upload & YouTube Data Fetching

    public function uploadPlaylistFile()
    {
        $file = $this->request->getFile('playlist_file');

        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No file uploaded',
            ])->setStatusCode(400);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'File upload error',
            ])->setStatusCode(400);
        }

        // Only allow .txt files
        if ($file->getMimeType() !== 'text/plain') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Only .txt files are allowed',
            ])->setStatusCode(400);
        }

        // Read file content
        $content = file_get_contents($file->getTempName());
        $urls = array_filter(array_map('trim', explode("\n", $content)));

        return $this->response->setJSON([
            'success' => true,
            'message' => 'File uploaded successfully',
            'urls' => $urls,
            'count' => count($urls),
        ]);
    }

    public function checkAndFetchYoutubeData()
    {
        $urls = $this->request->getPost('urls');

        if (!is_array($urls) || empty($urls)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'URLs required',
            ])->setStatusCode(400);
        }

        $results = [];
        $errors = [];

        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) continue;

            try {
                // Extract YouTube Video ID
                $videoId = $this->extractYoutubeVideoId($url);

                if (!$videoId) {
                    $errors[] = [
                        'url' => $url,
                        'error' => 'Invalid YouTube URL'
                    ];
                    continue;
                }

                // Fetch YouTube data
                $youtubeData = $this->fetchYoutubeData($videoId);

                if ($youtubeData) {
                    $results[] = [
                        'video_id' => $videoId,
                        'url' => $url,
                        'song_title' => $youtubeData['title'] ?? 'Unknown Title',
                        'artist_name' => $youtubeData['artist'] ?? 'Unknown Artist',
                        'album' => $youtubeData['album'] ?? 'Unknown Album',
                        'duration' => $youtubeData['duration'] ?? 0,
                        'thumbnail' => $youtubeData['thumbnail'] ?? null,
                        'status' => 'valid'
                    ];
                } else {
                    $errors[] = [
                        'url' => $url,
                        'video_id' => $videoId,
                        'error' => 'Could not fetch video data'
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'url' => $url,
                    'error' => client_error($e, 'Terjadi kesalahan')
                ];
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'valid_count' => count($results),
            'error_count' => count($errors),
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    public function saveBulkSongs()
    {
        $playlistId = $this->request->getPost('playlist_id');
        $songs = $this->request->getPost('songs');

        if (!$playlistId || !is_array($songs) || empty($songs)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist ID and songs required',
            ])->setStatusCode(400);
        }

        $playlist = $this->playlistModel->find($playlistId);
        if (!$playlist) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Playlist not found',
            ])->setStatusCode(404);
        }

        $savedCount = 0;
        $duplicates = [];
        $errors = [];

        foreach ($songs as $song) {
            try {
                // Check for duplicates
                $existing = $this->songModel
                    ->where('playlist_id', $playlistId)
                    ->where('video_id', $song['video_id'])
                    ->first();

                if ($existing) {
                    $duplicates[] = [
                        'video_id' => $song['video_id'],
                        'title' => $song['song_title'],
                        'message' => 'Song already exists in playlist'
                    ];
                    continue;
                }

                $position = $this->songModel
                    ->where('playlist_id', $playlistId)
                    ->countAllResults() + 1;

                $data = [
                    'playlist_id' => $playlistId,
                    'video_id' => $song['video_id'],
                    'song_title' => $song['song_title'] ?? 'Unknown Title',
                    'artist_name' => $song['artist_name'] ?? 'Unknown Artist',
                    'album' => $song['album'] ?? 'Unknown Album',
                    'duration' => $song['duration'] ?? 0,
                    'play_count' => 0,
                    'play_today' => 0,
                    'status' => 'Menunggu',
                    'position' => $position,
                ];

                if ($this->songModel->insert($data)) {
                    $savedCount++;
                } else {
                    $errors[] = [
                        'video_id' => $song['video_id'],
                        'title' => $song['song_title'],
                        'error' => 'Failed to save song'
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'video_id' => $song['video_id'] ?? 'unknown',
                    'error' => client_error($e, 'Terjadi kesalahan')
                ];
            }
        }

        // Update playlist song count
        if ($savedCount > 0) {
            $this->playlistModel->updateSongCount($playlistId);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => "Successfully saved {$savedCount} songs",
            'saved' => $savedCount,
            'duplicates_count' => count($duplicates),
            'errors_count' => count($errors),
            'duplicates' => $duplicates,
            'errors' => $errors,
        ])->setStatusCode($savedCount > 0 ? 201 : 400);
    }

    // Helper Methods

    private function extractYoutubeVideoId(string $url): ?string
    {
        // Match various YouTube URL formats
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/live\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
            '/^([a-zA-Z0-9_-]{11})$/', // Direct video ID
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function fetchYoutubeData(string $videoId): ?array
    {
        try {
            $response = \Config\Services::curlrequest()->get('https://www.youtube.com/oembed', [
                'timeout' => 10,
                'http_errors' => false,
                'query' => [
                    'url' => 'https://www.youtube.com/watch?v=' . $videoId,
                    'format' => 'json',
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                log_message('warning', 'YouTube oEmbed returned HTTP {status} for video {videoId}', [
                    'status' => $response->getStatusCode(),
                    'videoId' => $videoId,
                ]);
                return null;
            }

            $youtubeData = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($youtubeData) || empty($youtubeData['title'])) {
                return null;
            }

            return [
                'title' => trim($youtubeData['title']),
                'artist' => trim($youtubeData['author_name'] ?? 'Unknown Artist'),
                'album' => 'YouTube Video',
                'duration' => $this->fetchYoutubeDuration($videoId) ?? 0,
                'thumbnail' => $youtubeData['thumbnail_url'] ?? null,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'YouTube fetch error: ' . $e->getMessage());
        }

        return null;
    }

    private function fetchYoutubeDuration(string $videoId): ?int
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
            return null;
        }

        try {
            $response = \Config\Services::curlrequest()->get('https://www.youtube.com/watch', [
                'timeout' => 8,
                'http_errors' => false,
                'query' => ['v' => $videoId],
                'headers' => [
                    'Accept' => 'text/html',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                log_message('warning', 'YouTube duration lookup returned HTTP {status} for video {videoId}', [
                    'status' => $response->getStatusCode(),
                    'videoId' => $videoId,
                ]);
                return null;
            }

            $html = (string) $response->getBody();
            if (preg_match('/"lengthSeconds"\s*:\s*"(\d+)"/', $html, $matches)) {
                return max(0, (int) $matches[1]);
            }

            if (preg_match('/itemprop=["\']duration["\'][^>]*content=["\'](PT[^"\']+)["\']/i', $html, $matches)) {
                return $this->parseYoutubeDuration($matches[1]);
            }

            log_message('warning', 'YouTube duration metadata not found for video {videoId}', [
                'videoId' => $videoId,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'YouTube duration lookup failed for video {videoId}: {message}', [
                'videoId' => $videoId,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function parseYoutubeDuration(string $duration): int
    {
        if (!preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $matches)) {
            return 0;
        }

        return ((int) ($matches[1] ?? 0) * 3600)
            + ((int) ($matches[2] ?? 0) * 60)
            + (int) ($matches[3] ?? 0);
    }

    private function extractArtistName(string $title): string
    {
        // Simple extraction: if title contains " - ", use first part as artist
        if (strpos($title, ' - ') !== false) {
            $parts = explode(' - ', $title);
            return trim($parts[0]);
        }

        return 'Unknown Artist';
    }
}
