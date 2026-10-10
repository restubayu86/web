<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Melindungi endpoint setup/migrasi.
 *
 * Diizinkan hanya jika:
 *  - user sudah login, atau
 *  - instalasi masih baru (tabel users belum ada atau masih kosong).
 */
class SetupFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('logged_in')) {
            return null;
        }

        if (self::isFreshInstall()) {
            return null;
        }

        return redirect()->to('/login');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    /** True jika tabel users belum ada atau masih kosong (instalasi baru). */
    public static function isFreshInstall(): bool
    {
        try {
            $db = \Config\Database::connect();
            if (! $db->tableExists('users')) {
                return true;
            }

            return $db->table('users')->countAllResults() === 0;
        } catch (\Throwable $e) {
            // Database belum bisa diakses = belum disiapkan.
            return true;
        }
    }
}
