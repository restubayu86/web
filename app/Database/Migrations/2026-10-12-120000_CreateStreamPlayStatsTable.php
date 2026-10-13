<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Riwayat putar per stream per hari (dipakai grafik Stats).
 * Diisi oleh Stream::recordPlayDelta() setiap kali total_plays bertambah.
 */
class CreateStreamPlayStatsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'stream_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'stat_date' => [
                'type' => 'DATE',
            ],
            'plays' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['stream_id', 'stat_date']);
        $this->forge->createTable('stream_play_stats', true);

        $this->backfillFromStreams();
    }

    public function down()
    {
        $this->forge->dropTable('stream_play_stats', true);
    }

    /**
     * Data lama hanya punya total_plays per stream (tanpa tanggal per putar), jadi
     * total itu dicatat pada tanggal mulai stream. Perkiraan kasar, hanya untuk
     * riwayat sebelum tabel ini ada.
     */
    private function backfillFromStreams(): void
    {
        if (! $this->db->tableExists('streams')) {
            return;
        }

        $tz  = new \DateTimeZone('Asia/Jakarta');
        $utc = new \DateTimeZone('UTC');
        $now = date('Y-m-d H:i:s');

        $streams = $this->db->table('streams')->where('total_plays >', 0)->get()->getResultArray();
        foreach ($streams as $stream) {
            $source = $stream['started_at'] ?: ($stream['created_at'] ?: null);
            if ($source === null) {
                continue;
            }
            try {
                $date = (new \DateTimeImmutable((string) $source, $utc))->setTimezone($tz)->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
            $this->db->table('stream_play_stats')->insert([
                'stream_id'  => (int) $stream['id'],
                'stat_date'  => $date,
                'plays'      => (int) $stream['total_plays'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
