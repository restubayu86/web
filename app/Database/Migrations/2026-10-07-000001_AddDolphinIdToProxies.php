<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDolphinIdToProxies extends Migration
{
    public function up()
    {
        // Kolom id proxy di Dolphin{anty}; dipakai untuk sinkronisasi/hapus di sisi Dolphin.
        if (! $this->db->fieldExists('dolphin_id', 'proxies')) {
            $this->forge->addColumn('proxies', [
                'dolphin_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            ]);
        }

        // Sintaks SQLite (bukan MySQL); aman dijalankan berulang.
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_proxies_dolphin_id ON proxies (dolphin_id)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX IF EXISTS idx_proxies_dolphin_id');
        $this->forge->dropColumn('proxies', 'dolphin_id');
    }
}
