<?php
define('ENVIRONMENT', 'development');
require __DIR__ . '/../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

// Manually bootstrap database or load models if possible, or just instantiate directly if db is set up
try {
    $settingsModel = new \App\Models\SettingsModel();
    $token = $settingsModel->getSetting('dolphin_api_token');
} catch (\Throwable $e) {
    echo "Error loading model: " . $e->getMessage() . "\n";
    exit;
}

echo "Token: " . ($token ? 'Found' : 'Not found') . "\n";

if ($token) {
    $ch = curl_init('http://localhost:3001/v1.0/auth/login-with-token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Login HTTP Code: $httpCode\n";
    echo "Login Response: $response\n";
    
    // Now get profiles
    $ch = curl_init('http://localhost:3001/v1.0/browser_profiles');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    // We might need a cookie or something? Let's see what login returns.
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Profiles HTTP Code: $httpCode\n";
    echo "Profiles Response: $response\n";
}

