<?php

namespace App\Models;

use CodeIgniter\Model;

class StreamPlayStatModel extends Model
{
    protected $table            = 'stream_play_stats';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['stream_id', 'stat_date', 'plays', 'created_at', 'updated_at'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Tambah jumlah putar pada tanggal tertentu (insert baru atau akumulasi).
     */
    public function addPlays(int $streamId, string $date, int $plays): bool
    {
        if ($plays <= 0) {
            return true;
        }

        $existing = $this->where('stream_id', $streamId)->where('stat_date', $date)->first();
        if ($existing) {
            return (bool) $this->update((int) $existing['id'], [
                'plays' => (int) $existing['plays'] + $plays,
            ]);
        }

        return (bool) $this->insert([
            'stream_id' => $streamId,
            'stat_date' => $date,
            'plays'     => $plays,
        ]);
    }

    /**
     * @return array<string,int> tanggal (Y-m-d) => jumlah putar
     */
    public function totalsByDate(int $streamId, string $startDate, string $endDate): array
    {
        $rows = $this->where('stream_id', $streamId)
            ->where('stat_date >=', $startDate)
            ->where('stat_date <=', $endDate)
            ->findAll();

        $totals = [];
        foreach ($rows as $row) {
            $date = substr((string) $row['stat_date'], 0, 10);
            $totals[$date] = ($totals[$date] ?? 0) + (int) $row['plays'];
        }

        return $totals;
    }
}
