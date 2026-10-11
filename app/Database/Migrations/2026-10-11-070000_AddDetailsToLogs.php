<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDetailsToLogs extends Migration
{
    public function up()
    {
        $fields = [];
        if (!$this->db->fieldExists('details', 'logs')) {
            $fields['details'] = ['type' => 'TEXT', 'null' => true, 'after' => 'status'];
        }
        if (!$this->db->fieldExists('user_id', 'logs')) {
            $fields['user_id'] = ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'details'];
        }
        if (!$this->db->fieldExists('ip_address', 'logs')) {
            $fields['ip_address'] = ['type' => 'VARCHAR', 'constraint' => '45', 'null' => true, 'after' => 'user_id'];
        }
        if ($fields !== []) {
            $this->forge->addColumn('logs', $fields);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('details', 'logs')) {
            $this->forge->dropColumn('logs', 'details');
        }
        if ($this->db->fieldExists('user_id', 'logs')) {
            $this->forge->dropColumn('logs', 'user_id');
        }
        if ($this->db->fieldExists('ip_address', 'logs')) {
            $this->forge->dropColumn('logs', 'ip_address');
        }
    }
}
