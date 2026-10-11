<?php
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
$app = Boot::bootWeb($paths);

$settingsModel = new \App\Models\SettingsModel();
$baseUrl = $settingsModel->getSetting('bot_bridge_url');
$token = $settingsModel->getSetting('bot_bridge_token');

echo "Base URL: $baseUrl\n";
echo "Token: " . ($token ? 'Found' : 'Not found') . "\n";

if ($baseUrl && $token) {
    $ch = curl_init($baseUrl . '/api/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Accept: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Health HTTP Code: $httpCode\n";
    echo "Health Response: $response\n";
}


