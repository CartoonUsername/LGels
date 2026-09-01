<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../../lib/Database.php';
require __DIR__ . '/../../lib/Auth.php';
require __DIR__ . '/../../lib/Scorer.php';

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Fehlt: config/config.php');
}
$config = require $configPath;
$pdo = Database::connect($config['db']);
$auth = new Auth($config['admin']);
