<?php

namespace App\Models;

use CodeIgniter\Model;

class ProfilePlaylistModel extends Model
{
    protected $table      = 'profile_playlists';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'profile_id',
        'playlist_id',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getForProfiles(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }

        return $this->builder()
            ->select('profile_playlists.profile_id, profile_playlists.playlist_id, playlists.playlist_name')
            ->join('playlists', 'playlists.id = profile_playlists.playlist_id', 'left')
            ->whereIn('profile_playlists.profile_id', $profileIds)
            ->get()
            ->getResultArray();
    }
}
