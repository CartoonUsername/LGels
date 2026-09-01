<?php
/**
 * Liefert eine echte (nicht erfundene) Zählung für den Social-Proof-Hinweis
 * auf der Landingpage. Gibt nur eine Zahl zurück, keine personenbezogenen
 * Daten. Das Frontend blendet den Hinweis aus, solange die Zahl zu klein
 * ist, um nicht leer/unglaubwürdig zu wirken.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['count' => 0]);
    exit;
}
$config = require $configPath;

require __DIR__ . '/../../lib/Database.php';

$pdo = Database::connect($config['db']);
$stmt = $pdo->query(
    'SELECT COUNT(*) FROM leads WHERE qualifiziert = 1 AND erstellt_am >= (NOW() - INTERVAL 30 DAY)'
);

header('Cache-Control: public, max-age=300');
echo json_encode(['count' => (int) $stmt->fetchColumn()]);
