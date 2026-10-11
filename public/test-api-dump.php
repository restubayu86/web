<?php
require __DIR__ . '/../vendor/autoload.php';
$db = new PDO('sqlite:' . __DIR__ . '/../writable/database.sqlite');
$token = $db->query("SELECT value FROM settings WHERE key='dolphin_api_token'")->fetchColumn();
if (!$token) { echo "No token\n"; exit; }
echo "Token: " . substr($token, 0, 8) . "...\n";
$ch = curl_init('https://anty-api.com/browser_profiles?limit=3');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token],
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "HTTP $code Err: $err\n";
$data = json_decode($res, true);
if (!$data) { echo "Raw: " . substr($res, 0, 2000) . "\n"; exit; }
echo "Keys: " . implode(', ', array_keys($data)) . "\n";
if (isset($data['data'][0])) {
    echo "First profile keys: " . implode(', ', array_keys($data['data'][0])) . "\n";
    echo json_encode($data['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
