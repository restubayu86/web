<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCurrentArtistToStreams extends Migration
{
    public function up()
    {
        $this->forge->addColumn('streams', [
            'current_artist' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'current_song',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('streams', 'current_artist');
    }
}
