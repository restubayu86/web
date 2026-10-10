<?php

namespace App\Models;

use CodeIgniter\Model;

class ProxyModel extends Model
{
    protected $table      = 'proxies';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'dolphin_id',
        'name',
        'proxy_url',
        'proxy_type',
        'username',
        'password',
        'port',
        'country',
        'browser_profiles_count',
        'is_active',
        'test_status',
        'last_tested',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getActiveProxies()
    {
        return $this->where('is_active', 1)->findAll();
    }

    public function updateTestStatus($id, $status, $lastTested = null)
    {
        $isActive = ($status === 'working') ? 1 : 0;
        return $this->update($id, [
            'test_status' => $status,
            'is_active' => $isActive,
            'last_tested' => $lastTested ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getProxyUrl(int $id): ?string
    {
        $proxy = $this->find($id);
        if (!$proxy) {
            return null;
        }

        // proxy_url already contains host:port format
        $url = (string)$proxy['proxy_url'];

        // Add authentication if available
        if (!empty($proxy['username']) && !empty($proxy['password'])) {
            // Format: http://username:password@host:port
            $proxyType = strtolower($proxy['proxy_type'] ?? 'http');
            $url = $proxyType . '://' . $proxy['username'] . ':' . $proxy['password'] . '@' . $url;
        } else {
            // Format: http://host:port
            $proxyType = strtolower($proxy['proxy_type'] ?? 'http');
            $url = $proxyType . '://' . $url;
        }

        return $url;
    }
}
