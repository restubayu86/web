<?php
$pdo = new PDO('sqlite:C:/Users/zainm/Videos/Web/web/writable/database.sqlite');
$stmt = $pdo->query('SELECT sql FROM sqlite_master WHERE type="table" AND name="logs"');
echo $stmt->fetchColumn() . PHP_EOL . PHP_EOL;
$stmt2 = $pdo->query('SELECT * FROM logs LIMIT 5');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
echo 'count: ' . $pdo->query('SELECT COUNT(*) FROM logs')->fetchColumn() . PHP_EOL;
echo PHP_EOL . '--- users ---' . PHP_EOL;
$stmt3 = $pdo->query('SELECT id, username FROM users LIMIT 5');
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
