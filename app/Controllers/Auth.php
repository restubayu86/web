<?php

namespace App\Controllers;

use App\Filters\SetupFilter;
use App\Models\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session()->get('logged_in')) {
            return redirect()->to('/');
        }

        return view('login', [
            'error'       => session()->getFlashdata('error'),
            // Pengecekan setup di halaman login hanya untuk instalasi baru.
            'needs_setup' => SetupFilter::isFreshInstall(),
        ]);
    }

    public function checkSetup()
    {
        // AJAX endpoint to check and auto-setup system
        $results = [
            'all_complete' => true,
            'checks' => [],
            'actions_taken' => []
        ];

        // 1. Check PHP Version
        $phpCheck = version_compare(phpversion(), '7.4', '>=');
        $results['checks']['php_version'] = [
            'name' => 'PHP Version',
            'status' => $phpCheck ? 'pass' : 'fail',
            'current' => phpversion(),
            'required' => '7.4+',
        ];
        if (!$phpCheck) $results['all_complete'] = false;

        // 2. Check SQLite3 Extension
        $sqlite3Check = extension_loaded('sqlite3') || class_exists('SQLite3');
        $results['checks']['sqlite3'] = [
            'name' => 'SQLite3 Extension',
            'status' => $sqlite3Check ? 'pass' : 'fail',
            'current' => $sqlite3Check ? 'Loaded' : 'Not loaded',
        ];
        if (!$sqlite3Check) $results['all_complete'] = false;

        // 3. Check PDO SQLite
        $pdoCheck = extension_loaded('pdo_sqlite');
        $results['checks']['pdo_sqlite'] = [
            'name' => 'PDO SQLite',
            'status' => $pdoCheck ? 'pass' : 'fail',
            'current' => $pdoCheck ? 'Loaded' : 'Not loaded',
        ];
        if (!$pdoCheck) $results['all_complete'] = false;

        // 4. Check Writable Directories
        $writableDirs = [
            'writable' => WRITEPATH,
            'writable/cache' => WRITEPATH . 'cache',
            'writable/logs' => WRITEPATH . 'logs',
            'writable/session' => WRITEPATH . 'session',
            'writable/uploads' => WRITEPATH . 'uploads'
        ];

        foreach ($writableDirs as $name => $path) {
            $isWritable = is_writable($path);
            $results['checks']['writable_' . str_replace('/', '_', $name)] = [
                'name' => ucfirst($name) . ' Directory',
                'status' => $isWritable ? 'pass' : 'fail',
                'current' => $isWritable ? 'Writable' : 'Not writable',
            ];
            if (!$isWritable) $results['all_complete'] = false;
        }

        // 5. Check Database Connection
        try {
            $dbPath = WRITEPATH . 'database.sqlite';
            $pdo = new \PDO('sqlite:' . $dbPath);
            $results['checks']['database'] = [
                'name' => 'Database Connection',
                'status' => 'pass',
                'current' => 'Connected',
            ];
        } catch (\Exception $e) {
            $results['checks']['database'] = [
                'name' => 'Database Connection',
                'status' => 'fail',
                'current' => client_error($e, 'Failed'),
            ];
            $results['all_complete'] = false;
        }

        // 6. Check Database Tables
        $tableCheck = $this->checkDatabaseTables();
        $results['checks']['db_tables'] = $tableCheck;
        if ($tableCheck['status'] !== 'pass') {
            $results['all_complete'] = false;
            // Auto-run migrations if tables missing
            if ($tableCheck['status'] === 'warning') {
                $migrationResult = $this->runMigrations();
                $results['actions_taken'][] = $migrationResult;
                // Re-check tables after migration
                $tableCheck = $this->checkDatabaseTables();
                $results['checks']['db_tables'] = $tableCheck;
                if ($tableCheck['status'] === 'pass') {
                    $results['all_complete'] = true;
                }
            }
        }

        // 7. Check Migrations
        $migrationCheck = $this->checkMigrations();
        $results['checks']['migrations'] = $migrationCheck;
        if ($migrationCheck['status'] !== 'pass') {
            $results['all_complete'] = false;
        }

        return $this->response->setJSON($results);
    }

    private function checkDatabaseTables()
    {
        try {
            $dbPath = WRITEPATH . 'database.sqlite';
            $pdo = new \PDO('sqlite:' . $dbPath);

            $tables = [
                'users',
                'settings',
                'logs',
                'profiles',
                'proxies',
                'playlists',
                'playlist_songs',
                'streams'
            ];

            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
            $existingTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $missingTables = array_diff($tables, $existingTables);

            if (empty($missingTables)) {
                return [
                    'name' => 'Database Tables',
                    'status' => 'pass',
                    'current' => count($existingTables) . ' tables',
                ];
            } else {
                return [
                    'name' => 'Database Tables',
                    'status' => 'warning',
                    'current' => count($existingTables) . ' tables',
                    'missing' => $missingTables
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Database Tables',
                'status' => 'fail',
                'current' => client_error($e, 'Error'),
            ];
        }
    }

    private function checkMigrations()
    {
        try {
            $dbPath = WRITEPATH . 'database.sqlite';
            $pdo = new \PDO('sqlite:' . $dbPath);

            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations';");
            $migrationsTableExists = $stmt->fetch() !== false;

            if (!$migrationsTableExists) {
                return [
                    'name' => 'Database Migrations',
                    'status' => 'warning',
                    'current' => '0 migrations',
                ];
            }

            $stmt = $pdo->query("SELECT COUNT(*) as count FROM migrations WHERE batch > 0;");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $migrationCount = $result['count'] ?? 0;

            if ($migrationCount >= 8) {
                return [
                    'name' => 'Database Migrations',
                    'status' => 'pass',
                    'current' => $migrationCount . ' migrations',
                ];
            } else {
                return [
                    'name' => 'Database Migrations',
                    'status' => 'warning',
                    'current' => $migrationCount . ' migrations',
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Database Migrations',
                'status' => 'warning',
                'current' => client_error($e, 'Error'),
            ];
        }
    }

    private function runMigrations()
    {
        try {
            $migrationRunner = \Config\Services::migrations();
            $migrationRunner->latest();

            return [
                'action' => 'Migrations',
                'status' => 'success',
                'message' => 'Database migrations completed successfully'
            ];
        } catch (\Exception $e) {
            return [
                'action' => 'Migrations',
                'status' => 'error',
                'message' => client_error($e, 'Migration failed')
            ];
        }
    }

    public function attempt()
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();

        if ($user && password_verify($password, $user['password'])) {
            session()->set([
                'logged_in' => true,
                'user_id'   => $user['id'],
                'username'  => $user['username'],
            ]);

            return redirect()->to('/');
        }

        return redirect()->back()->with('error', 'Username atau password salah.');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
