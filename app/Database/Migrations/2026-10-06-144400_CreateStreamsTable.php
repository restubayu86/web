<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStreamsTable extends Migration
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
            'stream_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'profile_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'playlist_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'stopped',
            ],
            'current_song' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'total_plays' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'stream_duration' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'ended_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addKey('profile_id');
        $this->forge->addKey('playlist_id');
        $this->forge->createTable('streams');
    }

    public function down()
    {
        $this->forge->dropTable('streams');
    }
}
