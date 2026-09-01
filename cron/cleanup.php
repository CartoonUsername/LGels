<?php
/**
 * DSGVO-Speicherbegrenzung: löscht abgebrochene Sofort-Check-Sessions
 * (status = 'in_progress', nie Telefonnummer/Einwilligung erreicht) nach
 * einer konfigurierbaren Frist sowie alte Rate-Limit-Zeilen.
 *
 * Als Hostinger-Cronjob einrichten, z.B. täglich:
 *   php /home/USER/lead-generator-energie/cron/cleanup.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Nur per CLI ausführbar.');
}

require __DIR__ . '/../lib/Database.php';

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Fehlt: config/config.php\n");
    exit(1);
}
$config = require $configPath;
$pdo = Database::connect($config['db']);

$inProgressTage = (int) ($config['cleanup']['in_progress_nach_tagen'] ?? 7);
$rateLimitsTage = (int) ($config['cleanup']['rate_limits_nach_tagen'] ?? 30);

$stmt = $pdo->prepare(
    "DELETE FROM leads WHERE status = 'in_progress' AND erstellt_am < (NOW() - INTERVAL :tage DAY)"
);
$stmt->execute(['tage' => $inProgressTage]);
$geloeschteLeads = $stmt->rowCount();

$stmt = $pdo->prepare('DELETE FROM rate_limits WHERE tag < (CURDATE() - INTERVAL :tage DAY)');
$stmt->execute(['tage' => $rateLimitsTage]);
$geloeschteRateLimits = $stmt->rowCount();

fwrite(STDOUT, "Aufgeräumt: {$geloeschteLeads} abgebrochene Sessions (>{$inProgressTage} Tage), {$geloeschteRateLimits} alte Rate-Limit-Zeilen (>{$rateLimitsTage} Tage).\n");
