<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingsModel extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['key', 'value', 'created_at', 'updated_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getSetting(string $key): ?string
    {
        $result = $this->where('key', $key)->first();
            return $result ? (string) $result['value'] : null;
    }

    /**
     * Ambang RAM (persen) dari halaman Settings. Selalu 1-100; nilai kosong atau
     * rusak kembali ke default 85.
     */
    public function getRamThreshold(): float
    {
        $value = $this->getSetting('ram_threshold_percent');
        if ($value === null || !is_numeric($value)) {
            return 85.0;
        }
        $threshold = (float) $value;

        return ($threshold >= 1.0 && $threshold <= 100.0) ? $threshold : 85.0;
    }

    public function setSetting($key, $value)
    {
        $existing = $this->where('key', $key)->first();
        if ($existing) {
            return $this->update($existing['id'], ['value' => $value]);
        } else {
            return $this->insert(['key' => $key, 'value' => $value]);
        }
    }
}
