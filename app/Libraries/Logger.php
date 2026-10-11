<?php

namespace App\Libraries;

class Logger
{
    public static function log($profileId, $action, $status, $details = null)
    {
        $db = \Config\Database::connect();
        $db->table('logs')->insert([
            'profile_id' => $profileId,
            'action'     => $action,
            'status'     => $status,
            'details'    => $details,
            'user_id'    => session()->get('user_id'),
            'ip_address' => service('request')->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
