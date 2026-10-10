<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBrowserProfilesCountToProxies extends Migration
{
    public function up()
    {
        $this->forge->addColumn('proxies', [
            'browser_profiles_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
                'after' => 'is_active',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('proxies', 'browser_profiles_count');
    }
}
