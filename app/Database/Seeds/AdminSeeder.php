<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Membuat user admin awal.
 *
 * Password diambil dari env `admin.password`. Jika kosong, dibuat acak
 * dan ditampilkan satu kali di terminal. Tidak ada password bawaan.
 */
class AdminSeeder extends Seeder
{
    public function run()
    {
        $username = env('admin.username', 'admin');
        $password = env('admin.password', '');
        $generated = false;

        if ($password === '' || $password === null) {
            $password  = bin2hex(random_bytes(8));
            $generated = true;
        }

        $this->db->table('users')->where('username', $username)->delete();

        $this->db->table('users')->insert([
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        echo "User '{$username}' dibuat.\n";
        if ($generated) {
            echo "Password sementara: {$password}\nSegera ganti setelah login pertama.\n";
        }
    }
}
