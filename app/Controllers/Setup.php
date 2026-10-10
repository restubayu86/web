<?php

namespace App\Controllers;

use CodeIgniter\Database\Config as DatabaseConfig;

class Setup extends BaseController
{
    private $checks = [];
    private $progress = 0;
    private $totalChecks = 0;

    public function check()
    {
        // Check if already logged in
        if (session()->get('logged_in')) {
            return redirect()->to('/');
        }

        // Initialize checks
        $this->initializeChecks();

        return view('setup/check', [
            'checks' => $this->checks,
            'totalChecks' => $this->totalChecks
        ]);
    }

    public function runChecks()
    {
        $results = [];

        // 1. Check PHP Version
        $results['php_version'] = [
            'name' => 'PHP Version',
            'status' => version_compare(phpversion(), '7.4', '>=') ? 'pass' : 'fail',
            'current' => phpversion(),
            'required' => '7.4+',
            'message' => version_compare(phpversion(), '7.4', '>=')
                ? 'PHP version is compatible'
                : 'PHP version must be 7.4 or higher'
        ];

        // 2. Check SQLite3 Extension
        // Try multiple ways to detect SQLite3
        $sqlite3Loaded = extension_loaded('sqlite3') || class_exists('SQLite3');
        $results['sqlite3'] = [
            'name' => 'SQLite3 Extension',
            'status' => $sqlite3Loaded ? 'pass' : 'fail',
            'current' => $sqlite3Loaded ? 'Loaded' : 'Not loaded',
            'required' => 'Loaded',
            'message' => $sqlite3Loaded
                ? 'SQLite3 extension is loaded'
                : 'SQLite3 extension is not loaded'
        ];

        // 3. Check PDO SQLite
        $results['pdo_sqlite'] = [
            'name' => 'PDO SQLite',
            'status' => extension_loaded('pdo_sqlite') ? 'pass' : 'fail',
            'current' => extension_loaded('pdo_sqlite') ? 'Loaded' : 'Not loaded',
            'required' => 'Loaded',
            'message' => extension_loaded('pdo_sqlite')
                ? 'PDO SQLite is loaded'
                : 'PDO SQLite is not loaded'
        ];

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
            $results['writable_' . str_replace('/', '_', $name)] = [
                'name' => ucfirst($name) . ' Directory',
                'status' => $isWritable ? 'pass' : 'fail',
                'current' => $isWritable ? 'Writable' : 'Not writable',
                'required' => 'Writable',
                'message' => $isWritable
                    ? "$name directory is writable"
                    : "$name directory is not writable. Run: chmod -R 777 $path"
            ];
        }

        // 5. Check Database Connection
        try {
            $dbPath = WRITEPATH . 'database.sqlite';
            $pdo = new \PDO('sqlite:' . $dbPath);
            $results['database'] = [
                'name' => 'Database Connection',
                'status' => 'pass',
                'current' => 'Connected',
                'required' => 'Connected',
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            $results['database'] = [
                'name' => 'Database Connection',
                'status' => 'fail',
                'current' => 'Failed',
                'required' => 'Connected',
                'message' => client_error($e, 'Database connection failed')
            ];
        }

        // 6. Check Database Tables
        $results['db_tables'] = $this->checkDatabaseTables();

        // 7. Check Migrations
        $results['migrations'] = $this->checkMigrations();

        return $this->response->setJSON($results);
    }

    public function runMigrations()
    {
        try {
            $migrationRunner = \Config\Services::migrations();
            $migrationRunner->latest();

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Database migrations completed successfully'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => client_error($e, 'Migration failed')
            ])->setStatusCode(500);
        }
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

            // Get existing tables from SQLite
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
            $existingTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $missingTables = array_diff($tables, $existingTables);

            if (empty($missingTables)) {
                return [
                    'name' => 'Database Tables',
                    'status' => 'pass',
                    'current' => count($existingTables) . ' tables',
                    'required' => '8 tables',
                    'message' => 'All required database tables exist'
                ];
            } else {
                return [
                    'name' => 'Database Tables',
                    'status' => 'warning',
                    'current' => count($existingTables) . ' tables',
                    'required' => '8 tables',
                    'message' => 'Missing tables: ' . implode(', ', $missingTables) . '. Run migrations to create them.'
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Database Tables',
                'status' => 'fail',
                'current' => 'Unknown',
                'required' => '8 tables',
                'message' => client_error($e, 'Could not check tables')
            ];
        }
    }

    private function checkMigrations()
    {
        try {
            $dbPath = WRITEPATH . 'database.sqlite';
            $pdo = new \PDO('sqlite:' . $dbPath);

            // Check if migrations table exists
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations';");
            $migrationsTableExists = $stmt->fetch() !== false;

            if (!$migrationsTableExists) {
                return [
                    'name' => 'Database Migrations',
                    'status' => 'warning',
                    'current' => '0 migrations',
                    'required' => '8 migrations',
                    'message' => 'No migrations table found. Click "Run Migrations" to initialize.'
                ];
            }

            // Count completed migrations
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM migrations WHERE batch > 0;");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $migrationCount = $result['count'] ?? 0;

            if ($migrationCount >= 8) {
                return [
                    'name' => 'Database Migrations',
                    'status' => 'pass',
                    'current' => $migrationCount . ' migrations',
                    'required' => '8 migrations',
                    'message' => 'All migrations completed'
                ];
            } else {
                return [
                    'name' => 'Database Migrations',
                    'status' => 'warning',
                    'current' => $migrationCount . ' migrations',
                    'required' => '8 migrations',
                    'message' => 'Pending migrations detected. Click "Run Migrations" to complete setup.'
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Database Migrations',
                'status' => 'warning',
                'current' => 'Unknown',
                'required' => '8 migrations',
                'message' => client_error($e, 'Could not check migrations')
            ];
        }
    }

    private function initializeChecks()
    {
        $this->checks = [
            'PHP Version',
            'SQLite3 Extension',
            'PDO SQLite',
            'Writable Directories',
            'Database Connection',
            'Database Tables',
            'Database Migrations'
        ];

        $this->totalChecks = count($this->checks);
    }
}
