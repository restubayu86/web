<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChromiumProfilesTable extends Migration
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
            'profile_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'proxy_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'process_id' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
                'null'       => true,
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
        $this->forge->addKey('proxy_id');
        $this->forge->createTable('chromium_profiles');
    }

    public function down()
    {
        $this->forge->dropTable('chromium_profiles');
    }
}
