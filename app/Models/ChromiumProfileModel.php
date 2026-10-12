<?php

namespace App\Models;

use App\Models\SettingsModel;
use CodeIgniter\Model;

class ChromiumProfileModel extends Model
{
    protected $table = 'chromium_profiles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'profile_name',
        'proxy_id',
        'process_id',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public const BRIDGE_ID_OFFSET = 1000000000;

    public static function bridgeId(int $id): int
    {
        return self::BRIDGE_ID_OFFSET + $id;
    }

    public static function idFromBridgeId(int $bridgeId): ?int
    {
        $id = $bridgeId - self::BRIDGE_ID_OFFSET;

        return $id > 0 ? $id : null;
    }

    public static function executablePath(): string
    {
        $settings = new SettingsModel();
        $path = (string) $settings->getSetting('chromium_executable');
        if ($path === '') {
            return ROOTPATH . 'chromium' . DIRECTORY_SEPARATOR . 'chromedriver.exe';
        }

        return str_replace('{{ROOTPATH}}', ROOTPATH, $path);
    }
}
