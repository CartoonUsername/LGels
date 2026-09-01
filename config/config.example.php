<?php
// Kopieren nach config.php und mit echten Zugangsdaten füllen.
// config.php NICHT ins Git-Repo committen (siehe .gitignore).

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'u123456789_leads',
        'user' => 'u123456789_leads',
        'pass' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    'mail' => [
        // Ziel-Adresse(n) für Benachrichtigung bei neuem qualifizierten Lead
        'to' => 'vertrieb@example.de',
        'from' => 'noreply@example.de',
        'from_name' => 'Lead-Generator Energie',
        // Optional: SMTP statt PHP mail() nutzen (Hostinger Business Mail)
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
    ],

    'recaptcha' => [
        'site_key' => '',
        'secret_key' => '',
        // Score-Schwelle für reCAPTCHA v3 (0.0 - 1.0)
        'min_score' => 0.5,
    ],

    'tracking' => [
        'meta_pixel_id' => '',
        'google_ads_id' => '',
        // Conversion-Label aus Google Ads (Tools > Conversions > Tag-Einrichtung),
        // nötig zusätzlich zur google_ads_id, um das "Lead"-Ereignis zu melden.
        'google_ads_conversion_label' => '',
    ],

    // Admin-Dashboard (public_html/admin/) zum Ansehen/Exportieren der Leads.
    // Passwort-Hash erzeugen mit: php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"
    'admin' => [
        'username' => 'admin',
        'password_hash' => '',
    ],

    // Aufräum-Skript (cron/cleanup.php): löscht abgebrochene, nie abgeschlossene
    // Sofort-Check-Sessions nach X Tagen (DSGVO-Speicherbegrenzung — diese Zeilen
    // haben ohnehin keine Telefonnummer/Einwilligung).
    'cleanup' => [
        'in_progress_nach_tagen' => 7,
        'rate_limits_nach_tagen' => 30,
    ],

    // WhatsApp-Nummer für den Sofort-Check-Abschluss, international ohne
    // "+" oder führende Nullen (Format wie von wa.me erwartet).
    'whatsapp' => [
        'number' => '4915112345678',
    ],

    'qualifizierung' => [
        'alter_min' => 40,
        'alter_max' => 60,
        'erlaubte_wohnsituation' => ['eigentuemer'],
    ],

    'rate_limit' => [
        'max_pro_ip_pro_tag' => 5,
    ],
];
