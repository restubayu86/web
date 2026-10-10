<?php

namespace App\Models;

use CodeIgniter\Model;

class ProfileModel extends Model
{
    protected $table      = 'profiles';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'profile_id',
        'profile_name',
        'status',
        'browser_type',
        'last_opened',
        'tags',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getByProfileId($profileId)
    {
        return $this->where('profile_id', $profileId)->first();
    }

    public function getActiveProfiles()
    {
        return $this->where('status', 'running')->findAll();
    }

    public function updateStatus($id, $status)
    {
        return $this->update($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
