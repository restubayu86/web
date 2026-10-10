<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlaylistSongsTable extends Migration
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
            'playlist_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'video_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'song_title' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'artist_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'duration' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'play_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'Menunggu',
            ],
            'position' => [
                'type'       => 'INT',
                'constraint' => 11,
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
        $this->forge->addKey('playlist_id');
        $this->forge->createTable('playlist_songs');
    }

    public function down()
    {
        $this->forge->dropTable('playlist_songs');
    }
}
