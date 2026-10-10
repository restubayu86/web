<?php

namespace App\Models;

use CodeIgniter\Model;

class PlaylistModel extends Model
{
    protected $table      = 'playlists';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'playlist_name',
        'youtube_id',
        'description',
        'total_songs',
        'is_active',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getActivePlaylist()
    {
        return $this->where('is_active', 1)->findAll();
    }

    public function getWithSongs($playlistId)
    {
        $playlist = $this->find($playlistId);
        if ($playlist) {
            $songModel = new PlaylistSongModel();
            $playlist['songs'] = $songModel->where('playlist_id', $playlistId)
                ->orderBy('position', 'ASC')
                ->findAll();
        }
        return $playlist;
    }

    public function updateSongCount($playlistId)
    {
        $songModel = new PlaylistSongModel();
        $count = $songModel->where('playlist_id', $playlistId)->countAllResults();
        return $this->update($playlistId, ['total_songs' => $count]);
    }
}
