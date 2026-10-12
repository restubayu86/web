<?php

namespace App\Controllers;

use App\Libraries\BotBridge;
use App\Libraries\ChromiumLauncher;
use App\Models\ChromiumProfileModel;
use App\Models\PlaylistModel;
use App\Models\ProfilePlaylistModel;
use App\Models\ProxyModel;
use App\Models\SettingsModel;
use App\Models\StreamModel;

class Chromium extends BaseController
{
    private ChromiumProfileModel $profiles;
    private SettingsModel $settings;

    public function __construct()
    {
        $this->profiles = new ChromiumProfileModel();
        $this->settings = new SettingsModel();
    }

    public function index()
    {
        return view('chromium', [
            'executable' => $this->executablePath(),
        ]);
    }

    public function getProfiles()
    {
        $profiles = $this->profiles
            ->orderBy('id', 'ASC')
            ->findAll();
        $assignments = new ProfilePlaylistModel();
        $proxyModel = new ProxyModel();
        $launcher = new ChromiumLauncher();
        $executable = $this->executablePath();

        foreach ($profiles as &$profile) {
            $profile['bridge_id'] = ChromiumProfileModel::bridgeId((int) $profile['id']);
            $profile['running'] = $launcher->isRunning($profile, $executable);
            if (!$profile['running'] && !empty($profile['process_id'])) {
                $this->profiles->update((int) $profile['id'], ['process_id' => null]);
                $profile['process_id'] = null;
            }
            $proxy = !empty($profile['proxy_id']) ? $proxyModel->find((int) $profile['proxy_id']) : null;
            $profile['proxy_name'] = $proxy['name'] ?? null;
            $assignment = $assignments->where('profile_id', (string) $profile['bridge_id'])->first();
            $profile['playlist_id'] = $assignment['playlist_id'] ?? null;
        }
        unset($profile);

        return $this->response->setJSON(['success' => true, 'data' => $profiles]);
    }

    public function getProxies()
    {
        $proxies = (new ProxyModel())
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'data' => array_map(static fn (array $proxy): array => [
                'id' => (int) $proxy['id'],
                'name' => (string) $proxy['name'],
                'host' => (string) $proxy['proxy_url'],
                'type' => (string) ($proxy['proxy_type'] ?? 'http'),
                'country' => (string) ($proxy['country'] ?? ''),
                'has_auth' => !empty($proxy['username']) || !empty($proxy['password']),
            ], $proxies),
        ]);
    }

    public function createProfile()
    {
        $name = trim((string) $this->request->getPost('profile_name'));
        $proxyId = $this->validatedProxyId($this->request->getPost('proxy_id'));
        $playlistId = $this->validatedPlaylistId($this->request->getPost('playlist_id'));
        if ($name === '' || mb_strlen($name) > 255 || $proxyId === false || $playlistId === false) {
            return $this->jsonError('Nama profil, proxy, atau playlist tidak valid.', 400);
        }

        $id = $this->profiles->insert([
            'profile_name' => $name,
            'proxy_id' => $proxyId,
            'process_id' => null,
        ]);
        if ($id === false) {
            log_message('error', 'Could not create Chromium profile: {errors}', ['errors' => json_encode($this->profiles->errors())]);
            return $this->jsonError('Profil Chromium gagal dibuat.', 500);
        }

        $bridgeId = ChromiumProfileModel::bridgeId((int) $id);
        if ($playlistId !== null && !$this->savePlaylistAssignment($bridgeId, $playlistId)) {
            $this->profiles->delete((int) $id);
            return $this->jsonError('Playlist profil Chromium gagal disimpan.', 500);
        }
        $streamModel = new StreamModel();
        if (!$streamModel->insert([
            'stream_name' => $name,
            'profile_id' => (string) $bridgeId,
            'playlist_id' => $playlistId,
            'status' => 'stopped',
            'total_plays' => 0,
            'stream_duration' => 0,
        ])) {
            log_message('error', 'Could not create Chromium stream for profile {id}.', ['id' => $id]);
            (new ProfilePlaylistModel())->where('profile_id', (string) $bridgeId)->delete();
            $this->profiles->delete((int) $id);
            return $this->jsonError('Profil dibuat, tetapi stream Chromium gagal disiapkan.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Profil Chromium dan stream-nya dibuat. Cookies tersimpan terpisah di folder profil ini.',
            'data' => ['id' => (int) $id, 'bridge_id' => $bridgeId],
        ])->setStatusCode(201);
    }

    public function updateProfile($id)
    {
        $profile = $this->profiles->find((int) $id);
        if (!$profile) {
            return $this->jsonError('Profil Chromium tidak ditemukan.', 404);
        }
        $stream = (new StreamModel())->where('profile_id', (string) ChromiumProfileModel::bridgeId((int) $id))->first();
        if ($stream && in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
            return $this->jsonError('Hentikan stream Chromium sebelum mengubah pengaturannya.', 409);
        }
        if ((new ChromiumLauncher())->isRunning($profile, $this->executablePath())) {
            return $this->jsonError('Tutup profil Chromium sebelum mengubah pengaturannya.', 409);
        }

        $name = trim((string) $this->request->getPost('profile_name'));
        $proxyId = $this->validatedProxyId($this->request->getPost('proxy_id'));
        $playlistId = $this->validatedPlaylistId($this->request->getPost('playlist_id'));
        if ($name === '' || mb_strlen($name) > 255 || $proxyId === false || $playlistId === false) {
            return $this->jsonError('Nama profil, proxy, atau playlist tidak valid.', 400);
        }

        if (!$this->profiles->update((int) $id, ['profile_name' => $name, 'proxy_id' => $proxyId])) {
            log_message('error', 'Could not update Chromium profile {id}: {errors}', [
                'id' => $id,
                'errors' => json_encode($this->profiles->errors()),
            ]);
            return $this->jsonError('Pengaturan profil Chromium gagal disimpan.', 500);
        }

        $bridgeId = ChromiumProfileModel::bridgeId((int) $id);
        if (!$this->savePlaylistAssignment($bridgeId, $playlistId)) {
            return $this->jsonError('Pengaturan profil tersimpan, tetapi playlist gagal disimpan.', 500);
        }
        $streamModel = new StreamModel();
        $stream = $streamModel->where('profile_id', (string) $bridgeId)->first();
        if ($stream && !$streamModel->update((int) $stream['id'], [
            'stream_name' => $name,
            'playlist_id' => $playlistId,
        ])) {
            log_message('error', 'Could not update stream configuration for Chromium profile {id}.', ['id' => $id]);
            return $this->jsonError('Profil disimpan, tetapi pengaturan stream gagal diperbarui.', 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Pengaturan profil Chromium disimpan.']);
    }

    public function deleteProfile($id)
    {
        $profile = $this->profiles->find((int) $id);
        if (!$profile) {
            return $this->jsonError('Profil Chromium tidak ditemukan.', 404);
        }
        if ((new ChromiumLauncher())->isRunning($profile, $this->executablePath())) {
            return $this->jsonError('Tutup profil Chromium sebelum menghapusnya.', 409);
        }

        $bridgeId = ChromiumProfileModel::bridgeId((int) $id);
        $streamModel = new StreamModel();
        $stream = $streamModel->where('profile_id', (string) $bridgeId)->first();
        if ($stream && in_array($stream['status'], ['starting', 'running', 'paused'], true)) {
            return $this->jsonError('Hentikan stream Chromium sebelum menghapus profil.', 409);
        }
        if ($stream && !$streamModel->delete((int) $stream['id'])) {
            log_message('error', 'Could not delete stream for Chromium profile {id}.', ['id' => $id]);
            return $this->jsonError('Stream Chromium gagal dihapus.', 500);
        }
        (new ProfilePlaylistModel())->where('profile_id', (string) $bridgeId)->delete();
        if (!$this->profiles->delete((int) $id)) {
            log_message('error', 'Could not delete Chromium profile {id}.', ['id' => $id]);
            return $this->jsonError('Profil Chromium gagal dihapus.', 500);
        }
        $this->removeProfileDirectory((int) $id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Profil dan cookies Chromium dihapus.',
        ]);
    }

    public function startProfile($id)
    {
        $profile = $this->profiles->find((int) $id);
        if (!$profile) {
            return $this->jsonError('Profil Chromium tidak ditemukan.', 404);
        }
        $launcher = new ChromiumLauncher();
        if ($launcher->isRunning($profile, $this->executablePath())) {
            return $this->response->setJSON(['success' => true, 'message' => 'Profil Chromium sudah terbuka.']);
        }

        $result = $launcher->start($profile, $this->executablePath());
        if (!$result['success']) {
            return $this->response->setJSON($result)->setStatusCode((int) ($result['status'] ?? 500));
        }
        if (!$this->profiles->update((int) $id, ['process_id' => (int) $result['process_id']])) {
            log_message('error', 'Chromium profile {id} started but process ID could not be saved.', ['id' => $id]);
            return $this->jsonError('Chromium dibuka, tetapi status proses gagal disimpan.', 500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Chromium dibuka. Login akan tersimpan di profil ini.',
            'data' => ['bridge_id' => ChromiumProfileModel::bridgeId((int) $id)],
        ]);
    }

    public function stopProfile($id)
    {
        $profile = $this->profiles->find((int) $id);
        if (!$profile) {
            return $this->jsonError('Profil Chromium tidak ditemukan.', 404);
        }

        $bridgeId = ChromiumProfileModel::bridgeId((int) $id);
        $bridgeUrl = BotBridge::normalizeUrl($this->settings->getSetting('bot_bridge_url'));
        $bridgeToken = trim((string) $this->settings->getSetting('bot_bridge_token'));
        if ($bridgeUrl !== null && $bridgeToken !== '') {
            $result = (new BotBridge())->stop($bridgeUrl, $bridgeToken, $bridgeId);
            if (!$result['success'] && ($profile['process_id'] ?? null)) {
                log_message('warning', 'BayzBot session stop failed before Chromium close for profile {id}: {message}', [
                    'id' => $id,
                    'message' => $result['message'] ?? 'Unknown bridge error',
                ]);
            }
        }

        $result = (new ChromiumLauncher())->stop($profile, $this->executablePath());
        if (!$result['success']) {
            return $this->response->setJSON($result)->setStatusCode((int) ($result['status'] ?? 500));
        }
        if (!$this->profiles->update((int) $id, ['process_id' => null])) {
            log_message('error', 'Chromium stopped but its process ID could not be cleared for profile {id}.', ['id' => $id]);
            return $this->jsonError('Chromium dihentikan, tetapi status proses gagal diperbarui.', 500);
        }

        return $this->response->setJSON($result);
    }

    public function saveExecutable()
    {
        $path = trim((string) $this->request->getPost('executable'));
        if ($path === '' || str_contains($path, '"') || !is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'exe') {
            return $this->jsonError('Pilih path executable Chromium (.exe) yang valid.', 422);
        }

        $realPath = realpath($path) ?: $path;
        $rootPath = realpath(ROOTPATH) ?: ROOTPATH;

        // Jika path berada di dalam ROOTPATH, simpan sebagai path relatif dengan placeholder
        if (str_starts_with($realPath, $rootPath)) {
            $path = '{{ROOTPATH}}' . substr($realPath, strlen($rootPath));
        } else {
            $path = $realPath;
        }

        foreach ($this->profiles->findAll() as $profile) {
            if ((new ChromiumLauncher())->isRunning($profile, $this->executablePath())) {
                return $this->jsonError('Tutup semua profil Chromium sebelum mengubah path executable.', 409);
            }
        }

        if (!$this->settings->setSetting('chromium_executable', $path)) {
            log_message('error', 'Could not save Chromium executable path.');
            return $this->jsonError('Path Chromium gagal disimpan.', 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Path executable Chromium disimpan.']);
    }

    private function executablePath(): string
    {
        return ChromiumProfileModel::executablePath();
    }

    private function validatedProxyId($value)
    {
        if ($value === null || $value === '' || $value === 'none') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id !== false && (new ProxyModel())->find($id) ? (int) $id : false;
    }

    private function validatedPlaylistId($value)
    {
        if ($value === null || $value === '' || $value === 'none') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id !== false && (new PlaylistModel())->find($id) ? (int) $id : false;
    }

    private function savePlaylistAssignment(int $bridgeId, ?int $playlistId): bool
    {
        $model = new ProfilePlaylistModel();
        $existing = $model->where('profile_id', (string) $bridgeId)->first();
        if ($playlistId === null) {
            return !$existing || (bool) $model->delete((int) $existing['id']);
        }
        if ($existing) {
            return (bool) $model->update((int) $existing['id'], ['playlist_id' => $playlistId]);
        }

        return (bool) $model->insert(['profile_id' => (string) $bridgeId, 'playlist_id' => $playlistId]);
    }

    private function removeProfileDirectory(int $id): void
    {
        $baseDirectory = realpath(WRITEPATH . 'chromium-profiles');
        $profileDirectory = WRITEPATH . 'chromium-profiles' . DIRECTORY_SEPARATOR . $id;
        $resolvedProfileDirectory = realpath($profileDirectory);
        if ($baseDirectory === false || $resolvedProfileDirectory === false
            || dirname($resolvedProfileDirectory) !== $baseDirectory) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resolvedProfileDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($resolvedProfileDirectory);
    }

    private function jsonError(string $message, int $status)
    {
        return $this->response->setJSON(['success' => false, 'message' => $message])->setStatusCode($status);
    }
}
