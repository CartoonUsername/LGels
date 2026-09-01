<?php
/**
 * Registrierung neuer Vertriebspartner (Schornsteinfeger, Dachdecker,
 * Bank-/Finanzberater, ...), die ihren eigenen Empfehlungslink bekommen.
 * Sehr bewusst niedrigschwellig gehalten (5 Felder), damit die Hürde für
 * eine Partnerschaft möglichst klein ist.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../lib/Database.php';
require __DIR__ . '/../../lib/ReferralCode.php';
require __DIR__ . '/../../lib/SmtpMailer.php';
require __DIR__ . '/../../lib/Mailer.php';

function respond(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    respond(500, ['ok' => false, 'error' => 'server_misconfigured']);
}
$config = require $configPath;

if (!empty($_POST['website'])) {
    respond(200, ['ok' => true]); // Honeypot
}

$name = trim((string) ($_POST['name'] ?? ''));
$betrieb = trim((string) ($_POST['betrieb'] ?? ''));
$partnertyp = trim((string) ($_POST['partnertyp'] ?? ''));
$plzGebiet = preg_replace('/\D/', '', (string) ($_POST['plz_gebiet'] ?? ''));
$telefon = trim((string) ($_POST['telefon'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));

$erlaubtePartnertypen = ['schornsteinfeger', 'dachdecker', 'bank-finanzberater', 'handwerker', 'sonstiges'];

if ($name === '' || $betrieb === '' || !in_array($partnertyp, $erlaubtePartnertypen, true)) {
    respond(422, ['ok' => false, 'error' => 'pflichtfelder_fehlen']);
}
if ($telefon === '' && $email === '') {
    respond(422, ['ok' => false, 'error' => 'kontakt_fehlt']);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['ok' => false, 'error' => 'email_ungueltig']);
}

$pdo = Database::connect($config['db']);

$code = null;
for ($versuch = 0; $versuch < 5; $versuch++) {
    $kandidat = ReferralCode::generiere(5);
    try {
        $pdo->prepare(
            'INSERT INTO partner (code, name, betrieb, partnertyp, plz_gebiet, telefon, email, status)
             VALUES (:code, :name, :betrieb, :partnertyp, :plz_gebiet, :telefon, :email, :status)'
        )->execute([
            'code' => $kandidat,
            'name' => $name,
            'betrieb' => $betrieb,
            'partnertyp' => $partnertyp,
            'plz_gebiet' => $plzGebiet,
            'telefon' => $telefon,
            'email' => $email !== '' ? $email : null,
            'status' => 'neu',
        ]);
        $code = $kandidat;
        break;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            continue; // Code-Kollision -> neuer Versuch
        }
        throw $e;
    }
}

if ($code === null) {
    respond(500, ['ok' => false, 'error' => 'code_generierung_fehlgeschlagen']);
}

if (!empty($config['mail']['to'])) {
    $mailer = new Mailer($config['mail']);
    // Nutzt dieselbe Benachrichtigungsfunktion mit Partner-Daten im Lead-Format,
    // damit kein zweiter Mailer-Pfad gepflegt werden muss.
    $mailer->sendeNeuerLeadBenachrichtigung([
        'vorname' => $name,
        'nachname' => "(Partner-Anmeldung: {$betrieb})",
        'telefon' => $telefon,
        'email' => $email,
        'plz' => $plzGebiet,
        'alter_int' => null,
        'wohnsituation' => $partnertyp,
        'interesse' => 'partner-anmeldung',
        'energiekosten' => null,
        'quelle' => 'partner-anmeldung',
    ]);
}

respond(200, ['ok' => true, 'code' => $code]);
