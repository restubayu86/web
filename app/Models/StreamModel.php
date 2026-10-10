<?php

namespace App\Models;

use CodeIgniter\Model;

class StreamModel extends Model
{
    protected $table      = 'streams';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'stream_name',
        'profile_id',
        'playlist_id',
        'status',
        'current_song',
        'current_artist',
        'total_plays',
        'stream_duration',
        'started_at',
        'ended_at',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getActiveStreams()
    {
        return $this->where('status', 'running')->findAll();
    }

    public function getStreamsByProfile($profileId)
    {
        return $this->where('profile_id', $profileId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getUnfinishedStreamsByProfile($profileId): array
    {
        return $this->where('profile_id', $profileId)
            ->whereIn('status', ['starting', 'running'])
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function updateStatus($id, $status)
    {
        return $this->update($id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function calculateDuration(int $id, ?string $endAt = null): int
    {
        $stream = $this->find($id);
        if ($stream && !empty($stream['started_at'])) {
            $start = strtotime((string)$stream['started_at']);
            $endValue = $endAt ?? ($stream['ended_at'] ?: date('Y-m-d H:i:s'));
            $end = strtotime((string)$endValue);
            if ($start !== false && $end !== false) {
                return max(0, (int)($end - $start));
            }
        }
        return 0;
    }
}
