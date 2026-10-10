<?php

namespace App\Models;

use CodeIgniter\Model;

class PlaylistSongModel extends Model
{
    protected $table      = 'playlist_songs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'playlist_id',
        'video_id',
        'song_title',
        'artist_name',
        'duration',
        'play_count',
        'status',
        'position',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getByPlaylistId($playlistId)
    {
        return $this->where('playlist_id', $playlistId)
            ->orderBy('position', 'ASC')
            ->findAll();
    }

    public function incrementPlayCount($songId)
    {
        $song = $this->find($songId);
        if ($song) {
            return $this->update($songId, ['play_count' => $song['play_count'] + 1]);
        }
        return false;
    }

    public function updateStatus($songId, $status)
    {
        return $this->update($songId, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
