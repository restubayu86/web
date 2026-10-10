<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCountryToProxies extends Migration
{
    public function up()
    {
        $this->forge->addColumn('proxies', [
            'country' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
                'after' => 'port',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('proxies', 'country');
    }
}
