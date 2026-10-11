<?php

namespace App\Controllers;

class Log extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $logs = $db->table('logs')
            ->orderBy('created_at', 'DESC')
            ->limit(100)
            ->get()
            ->getResultArray();

        return view('log', ['logs' => $logs]);
    }

    public function getAll()
    {
        $db = \Config\Database::connect();
        $logs = $db->table('logs')
            ->orderBy('created_at', 'DESC')
            ->limit(100)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function clear()
    {
        $db = \Config\Database::connect();
        $db->table('logs')->truncate();

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Logs cleared successfully',
        ]);
    }
}
